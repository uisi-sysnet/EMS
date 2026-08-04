#!/usr/bin/env bash
#
# update.sh — pulls the latest code from GitHub and re-provisions
# everything: the three Python services (air_quality_ingest.py,
# seismic_mqtt.py, api_server.py) AND the Laravel dashboard.
#
# Sequence:
#   1. Stop nginx + ems.target (so nothing is serving/running stale code
#      mid-update, and migrations don't race a live app)
#   2. git pull (single repo — Dashboard/ is a plain subfolder of EMS,
#      not a submodule, so one pull at the repo root updates everything)
#   3. Re-install Python deps (requirements.txt may have changed)
#   4. Re-provision Laravel: composer install, migrate, config/route/view
#      cache, npm build if applicable, fix ownership/permissions
#   5. Start nginx + ems.target again
#
# Assumes deploy.sh and install_services.sh have already been run once
# (this script does NOT create system users, install packages, or write
# .env files — it only updates code and restarts what's already set up).
#
# Usage: sudo ./update.sh
#   Optional overrides (same convention as deploy.sh):
#     LARAVEL_DIR=/path/to/Dashboard sudo -E ./update.sh
#     GIT_BRANCH=develop            sudo -E ./update.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EMS_DIR="$SCRIPT_DIR"
LARAVEL_DIR="${LARAVEL_DIR:-${SCRIPT_DIR}/Dashboard}"
# Repo: https://github.com/uisi-sysnet/EMS — deployed branch is "version5".
# Override if needed: GIT_BRANCH=main sudo -E ./update.sh
GIT_BRANCH="${GIT_BRANCH:-version5}"

log()  { echo -e "\033[1;32m[update]\033[0m $*"; }
warn() { echo -e "\033[1;33m[update][WARN]\033[0m $*"; }
die()  { echo -e "\033[1;31m[update][ERROR]\033[0m $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Run this with sudo: sudo ./update.sh"

# ----------------------------------------------------------------------
# 0. Figure out who should own the git pull + the resulting files
#    (same detection logic as install_services.sh — prefer the user who
#    invoked sudo, so files don't end up root-owned).
# ----------------------------------------------------------------------
if [[ -n "${SUDO_USER:-}" ]]; then
    EMS_USER="$SUDO_USER"
else
    EMS_USER="$(stat -c '%U' "$SCRIPT_DIR")"
    warn "No SUDO_USER detected — using the folder owner '${EMS_USER}' to run git pull."
fi
log "Project directory: ${EMS_DIR}"
log "Running git operations as: ${EMS_USER}"

git_pull() {
    # $1 = directory to pull in
    local dir="$1" current_branch
    [[ -d "${dir}/.git" ]] || { warn "${dir} is not a git repo — skipping pull there."; return 0; }

    # deploy.sh/update.sh chown+chmod the Laravel tree for www-data after every
    # run. Git tracks permission bits by default, so that alone makes tracked
    # files (e.g. storage/**/.gitignore) look "modified" even with identical
    # content — falsely triggering the stash path below on every single run.
    # Turn that off once; it's a per-repo, idempotent config setting.
    sudo -u "$EMS_USER" git -C "$dir" config core.fileMode false

    if ! sudo -u "$EMS_USER" git -C "$dir" diff --quiet --exit-code || \
       ! sudo -u "$EMS_USER" git -C "$dir" diff --cached --quiet --exit-code; then
        warn "${dir} has uncommitted local changes. Stashing them before pulling"
        warn "(recover later with: cd ${dir} && sudo -u ${EMS_USER} git stash pop)"
        sudo -u "$EMS_USER" git -C "$dir" stash push -m "update.sh auto-stash $(date -Iseconds)"
    fi

    log "Fetching from origin in ${dir}"
    sudo -u "$EMS_USER" git -C "$dir" fetch origin || \
        die "git fetch failed in ${dir} — check network access to GitHub / remote 'origin' config."

    current_branch="$(sudo -u "$EMS_USER" git -C "$dir" rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")"
    if [[ -n "$GIT_BRANCH" && "$current_branch" != "$GIT_BRANCH" ]]; then
        log "${dir} is on branch '${current_branch:-detached}', switching to '${GIT_BRANCH}'"
        sudo -u "$EMS_USER" git -C "$dir" checkout "$GIT_BRANCH" 2>/dev/null || \
        sudo -u "$EMS_USER" git -C "$dir" checkout -b "$GIT_BRANCH" "origin/${GIT_BRANCH}" 2>/dev/null || \
            die "Could not switch ${dir} to branch '${GIT_BRANCH}' — does origin/${GIT_BRANCH} exist? Check: git -C ${dir} branch -r"
    fi

    log "Pulling latest '${GIT_BRANCH}' in ${dir}"
    sudo -u "$EMS_USER" git -C "$dir" pull --ff-only origin "$GIT_BRANCH" || \
        die "git pull failed in ${dir} — resolve manually (check for diverged history, local commits, or conflicts), then re-run."
}

# ----------------------------------------------------------------------
# 1. Stop everything before touching any files
# ----------------------------------------------------------------------
log "Stopping services"

if [[ -d "$LARAVEL_DIR" ]] && [[ -f "${LARAVEL_DIR}/artisan" ]]; then
    log "Putting Laravel into maintenance mode"
    ( cd "$LARAVEL_DIR" && sudo -u www-data php artisan down --render="errors::503" ) 2>/dev/null || \
        warn "Could not enable Laravel maintenance mode (non-fatal, continuing)"
fi

if systemctl list-unit-files --no-legend 'nginx.service' | grep -q nginx; then
    log "Stopping nginx"
    systemctl stop nginx || warn "Could not stop nginx (continuing anyway)"
else
    warn "nginx.service not found — skipping (is nginx installed?)"
fi

if systemctl list-unit-files --no-legend 'ems.target' | grep -q ems.target; then
    log "Stopping ems.target (air-quality, seismic, api services)"
    systemctl stop ems.target || warn "Could not stop ems.target (continuing anyway)"
else
    warn "ems.target not found — skipping (has install_services.sh been run?)"
fi

# ----------------------------------------------------------------------
# 2. Pull latest code
# ----------------------------------------------------------------------
# Dashboard/ is currently owned by www-data (set at the end of the last
# deploy/update run, further down). Git runs as EMS_USER, not www-data or
# root, so it needs write access to the whole tree first — reclaim it here,
# then hand storage/bootstrap/cache back to www-data again after Laravel is
# re-provisioned below (same as the existing post-build ownership step).
log "Reclaiming ownership of ${EMS_DIR} as ${EMS_USER} so git can update it"
chown -R "${EMS_USER}:${EMS_USER}" "$EMS_DIR"

git_pull "$EMS_DIR"
# NOTE: Dashboard/ is a plain subfolder of the EMS repo (confirmed: no
# .gitmodules, no nested .git under Dashboard/) — the pull above already
# updates it. No separate pull step needed here.

# ----------------------------------------------------------------------
# 3. Python dependencies (requirements.txt may have changed)
# ----------------------------------------------------------------------
REQ_FILE="${EMS_DIR}/requirements.txt"
if [[ -f "$REQ_FILE" ]]; then
    log "Re-installing Python dependencies from requirements.txt"
    pip3 install -r "$REQ_FILE" -q --break-system-packages --ignore-installed
else
    warn "requirements.txt not found at ${REQ_FILE} — skipping Python dependency update."
fi

# ----------------------------------------------------------------------
# 4. Re-provision Laravel
# ----------------------------------------------------------------------
if [[ ! -d "$LARAVEL_DIR" ]]; then
    warn "Laravel dashboard not found at ${LARAVEL_DIR} — skipping Laravel update."
else
    # Run composer/artisan/npm as EMS_USER, NOT www-data. Dashboard/ is still
    # EMS_USER-owned at this point (from the reclaim-for-git step above), so
    # this avoids two separate problems that come from running build tools
    # as www-data: (1) composer's internal git version-guessing hits git's
    # "dubious ownership" check, since .git lives in EMS_DIR which stays
    # EMS_USER-owned; (2) npm/composer want to write cache files under
    # www-data's home (/var/www), which www-data itself can't write to on
    # Debian/Ubuntu (root:root, 755). Running as EMS_USER sidesteps both —
    # www-data only needs to OWN the files at runtime (serving), not build
    # them. Ownership is handed to www-data in one pass at the very end.
    log "Running composer install (as ${EMS_USER})"
    ( cd "$LARAVEL_DIR" && sudo -u "$EMS_USER" composer install --no-dev --optimize-autoloader --no-interaction ) || \
        die "composer install failed — see error above."

    log "Running Laravel migrations (as ${EMS_USER})"
    ( cd "$LARAVEL_DIR" && sudo -u "$EMS_USER" php artisan migrate --force ) || \
        warn "Migrations failed — check ${LARAVEL_DIR}/.env DB_* values, then re-run manually:"
        warn "  cd ${LARAVEL_DIR} && sudo -u ${EMS_USER} php artisan migrate --force"

    if [[ -f "${LARAVEL_DIR}/package.json" ]] && command -v npm >/dev/null 2>&1; then
        log "Installing frontend deps and rebuilding assets (npm, as ${EMS_USER})"
        ( cd "$LARAVEL_DIR" && sudo -u "$EMS_USER" npm ci && sudo -u "$EMS_USER" npm run build ) || \
            warn "npm build failed — check ${LARAVEL_DIR}/package.json and node -v."
    fi

    log "Refreshing Laravel config/route/view caches (as ${EMS_USER})"
    ( cd "$LARAVEL_DIR" && sudo -u "$EMS_USER" php artisan config:clear \
        && sudo -u "$EMS_USER" php artisan config:cache \
        && sudo -u "$EMS_USER" php artisan route:cache \
        && sudo -u "$EMS_USER" php artisan view:cache ) || \
        warn "artisan cache commands failed — non-fatal, app will still run uncached."

    # ---- Hand off to www-data for runtime serving (single pass, once
    # everything above is done) — same fix as deploy.sh ----
    log "Restoring www-data ownership/permissions on ${LARAVEL_DIR} for runtime"
    chown -R www-data:www-data "$LARAVEL_DIR"
    chmod -R 775 "${LARAVEL_DIR}/storage" "${LARAVEL_DIR}/bootstrap/cache" 2>/dev/null || true

    # Re-check parent-directory traversal permission in case LARAVEL_DIR
    # moved or www-data still can't reach it for any reason (same logic
    # as deploy.sh's fix for the "www-data can't traverse into home dir" 404).
    _check_dir="$(dirname "$LARAVEL_DIR")"
    while [[ "$_check_dir" != "/" && -n "$_check_dir" ]]; do
        _perms="$(stat -c '%A' "$_check_dir" 2>/dev/null || true)"
        if [[ -n "$_perms" && "${_perms:9:1}" != "x" ]]; then
            warn "${_check_dir} lacks traversal permission for other users — adding o+x"
            chmod o+x "$_check_dir" || warn "Could not chmod ${_check_dir} — fix manually: sudo chmod o+x ${_check_dir}"
        fi
        _check_dir="$(dirname "$_check_dir")"
    done

    log "Taking Laravel out of maintenance mode"
    ( cd "$LARAVEL_DIR" && sudo -u www-data php artisan up ) 2>/dev/null || \
        warn "Could not disable maintenance mode automatically — run manually: cd ${LARAVEL_DIR} && sudo -u www-data php artisan up"
fi

# ----------------------------------------------------------------------
# 5. Start everything back up
# ----------------------------------------------------------------------
log "Starting nginx"
systemctl start nginx || die "nginx failed to start — check: sudo systemctl status nginx"

log "Starting ems.target (air-quality, seismic, api services)"
systemctl start ems.target || die "ems.target failed to start — check: sudo systemctl status ems.target"

log "Done. Current status:"
systemctl status nginx ems-air-quality.service ems-seismic.service ems-api.service --no-pager || true

cat <<EOF

Update complete. If service unit files themselves changed (e.g. a new
environment variable in the .template files), re-run install_services.sh
too, since this script only restarts existing units — it doesn't regenerate
them:
  sudo ${EMS_DIR}/install_services.sh

Useful commands:
  sudo systemctl status ems.target
  sudo journalctl -u ems-air-quality.service -f
  sudo journalctl -u ems-seismic.service -f
  sudo journalctl -u ems-api.service -f
  sudo tail -f /var/log/nginx/ems-dashboard.error.log

EOF