#!/usr/bin/env bash
#
# install_terminal.sh — sets up the dashboard's web terminal
# (Maintenance > Terminal, super admins only):
#
#   browser --WebSocket /pty--> nginx --> 127.0.0.1:3001 scripts/terminal_server.py --> bash -l
#
# 1. Installs the Python websockets package (apt python3-websockets).
# 2. Adds a random TERMINAL_SHARED_SECRET to scripts/.env if there isn't
#    one (the dashboard signs terminal logins with it; the bridge checks them).
# 3. Installs and starts ems-terminal.service (shell runs as the EMS user).
# 4. Adds the /pty WebSocket route to the ems-dashboard nginx site if missing.
# 5. Refreshes the dashboard's config cache.
#
# Safe to re-run. Called by install_services.sh and update.sh.
#
# Usage: sudo ./install_terminal.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EMS_DIR="$SCRIPT_DIR"
ENV_FILE="${EMS_DIR}/scripts/.env"
LARAVEL_DIR="${LARAVEL_DIR:-${EMS_DIR}/Dashboard}"
TEMPLATE="${EMS_DIR}/template/ems-terminal_service.template"
UNIT_DEST="/etc/systemd/system/ems-terminal.service"
NGINX_SITE="/etc/nginx/sites-available/ems-dashboard"

log()  { echo -e "\033[1;32m[terminal]\033[0m $*"; }
warn() { echo -e "\033[1;33m[terminal][WARN]\033[0m $*"; }
die()  { echo -e "\033[1;31m[terminal][ERROR]\033[0m $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Run this with sudo: sudo ./install_terminal.sh"
[[ -f "${EMS_DIR}/scripts/terminal_server.py" ]] || die "Missing scripts/terminal_server.py."
[[ -f "$TEMPLATE" ]] || die "Missing ${TEMPLATE}."
[[ -f "$ENV_FILE" ]] || die "Missing ${ENV_FILE} (run install.sh / deploy.sh first)."

if [[ -n "${SUDO_USER:-}" && "${SUDO_USER}" != "root" ]]; then
    EMS_USER="$SUDO_USER"
else
    EMS_USER="$(stat -c '%U' "$EMS_DIR")"
fi

# ----------------------------------------------------------------------
# 1. websockets for Python
# ----------------------------------------------------------------------
if ! /usr/bin/python3 -c "import websockets" 2>/dev/null; then
    log "Installing python3-websockets"
    apt-get install -y python3-websockets >/dev/null 2>&1 \
        || pip3 install -q --break-system-packages "websockets>=10.4" \
        || die "Could not install the Python websockets package."
fi

# ----------------------------------------------------------------------
# 2. Shared secret in scripts/.env
# ----------------------------------------------------------------------
if ! grep -qE '^TERMINAL_SHARED_SECRET=.+' "$ENV_FILE"; then
    log "Adding a new TERMINAL_SHARED_SECRET to ${ENV_FILE}"
    sed -i '/^TERMINAL_SHARED_SECRET=/d' "$ENV_FILE"
    printf '\n# ---- Web terminal (dashboard Maintenance > Terminal) ----\nTERMINAL_SHARED_SECRET=%s\n' "$(openssl rand -hex 32)" >> "$ENV_FILE"
fi

# ----------------------------------------------------------------------
# 3. systemd service
# ----------------------------------------------------------------------
log "Installing ${UNIT_DEST} (shell user: ${EMS_USER})"
sed -e "s|__EMS_DIR__|${EMS_DIR}|g" -e "s|__EMS_USER__|${EMS_USER}|g" "$TEMPLATE" > "$UNIT_DEST"
systemctl daemon-reload
systemctl enable ems-terminal.service >/dev/null
systemctl restart ems-terminal.service
sleep 2
if ss -ltn 2>/dev/null | grep -q '127.0.0.1:3001'; then
    log "Terminal bridge is listening on 127.0.0.1:3001"
else
    warn "Terminal bridge isn't listening yet. Check: sudo journalctl -u ems-terminal.service -n 30"
fi

# ----------------------------------------------------------------------
# 4. nginx /pty route (WebSocket upgrade)
# ----------------------------------------------------------------------
if [[ -f "$NGINX_SITE" ]]; then
    if grep -q 'location /pty' "$NGINX_SITE"; then
        grep -q 'Upgrade \$http_upgrade' "$NGINX_SITE" \
            || warn "${NGINX_SITE} has a /pty route without 'proxy_set_header Upgrade \$http_upgrade;' — the terminal needs it."
    else
        log "Adding the /pty WebSocket route to ${NGINX_SITE}"
        cp "$NGINX_SITE" "${NGINX_SITE}.bak"
        snippet="$(mktemp)"
        cat > "$snippet" <<'NGINXEOF'
    # Dashboard web terminal: WebSocket to scripts/terminal_server.py.
    location /pty {
        proxy_pass http://127.0.0.1:3001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
    }

NGINXEOF
        awk -v snip="$snippet" '
            !done && /^[[:space:]]*location \/ \{/ { while ((getline l < snip) > 0) print l; done=1 }
            { print }
        ' "${NGINX_SITE}.bak" > "$NGINX_SITE"
        rm -f "$snippet"
        if nginx -t >/dev/null 2>&1; then
            systemctl reload nginx 2>/dev/null || true
            log "nginx updated (backup: ${NGINX_SITE}.bak)"
        else
            mv "${NGINX_SITE}.bak" "$NGINX_SITE"
            warn "nginx rejected the /pty route — restored the previous config. Check: sudo nginx -t"
        fi
    fi
else
    warn "${NGINX_SITE} not found (run deploy.sh first) — skipping the nginx route."
fi

# ----------------------------------------------------------------------
# 5. Dashboard config cache (picks up the terminal settings)
# ----------------------------------------------------------------------
if [[ -f "${LARAVEL_DIR}/artisan" ]]; then
    ( cd "$LARAVEL_DIR" && sudo -u www-data php artisan config:cache >/dev/null ) \
        || warn "Could not refresh the dashboard config: cd ${LARAVEL_DIR} && sudo -u www-data php artisan config:cache"
fi

log "Done. Open Maintenance > Terminal in the dashboard (super admins). Logs: sudo journalctl -u ems-terminal.service -f"
