#!/usr/bin/env bash
#
# install_mediamtx.sh — sets up the CCTV stream server (MediaMTX) that the
# dashboard's camera live view and CityWatch camera popup play through:
#
#   camera --RTSP--> MediaMTX --WebRTC--> browser
#
# 1. Makes sure mediamtx/mediamtx is a binary for this CPU (the bundled one
#    is x86_64; on a Raspberry Pi the matching release is downloaded).
# 2. Installs and starts ems-mediamtx.service (part of ems.target).
# 3. Routes the browser's stream requests (/cctv-stream/<camera>/whep) in
#    the ems-dashboard nginx site to MediaMTX. Camera PTZ
#    (/cctv-stream/<camera>/ptz) stays with the dashboard.
# 4. Opens the WebRTC media port (8189/udp) if ufw is active.
# 5. Pushes every enabled camera to MediaMTX (php artisan cameras:resync).
#
# Without this, every live view shows "Unable to connect to this camera".
# Safe to re-run. Called by install.sh (via install_services.sh) and
# update.sh.
#
# Usage: sudo ./install_mediamtx.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EMS_DIR="$SCRIPT_DIR"
MTX_DIR="${EMS_DIR}/mediamtx"
LARAVEL_DIR="${LARAVEL_DIR:-${EMS_DIR}/Dashboard}"
MTX_VERSION="v1.20.1"
UNIT_DEST="/etc/systemd/system/ems-mediamtx.service"
NGINX_SITE="/etc/nginx/sites-available/ems-dashboard"

log()  { echo -e "\033[1;32m[mediamtx]\033[0m $*"; }
warn() { echo -e "\033[1;33m[mediamtx][WARN]\033[0m $*"; }
die()  { echo -e "\033[1;31m[mediamtx][ERROR]\033[0m $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Run this with sudo: sudo ./install_mediamtx.sh"
[[ -f "${MTX_DIR}/mediamtx.yml" ]] || die "Missing ${MTX_DIR}/mediamtx.yml."
[[ -f "${MTX_DIR}/ems-mediamtx.service" ]] || die "Missing ${MTX_DIR}/ems-mediamtx.service."

if [[ -n "${SUDO_USER:-}" && "${SUDO_USER}" != "root" ]]; then
    EMS_USER="$SUDO_USER"
else
    EMS_USER="$(stat -c '%U' "$EMS_DIR")"
fi

# ----------------------------------------------------------------------
# 1. Binary for this CPU
# ----------------------------------------------------------------------
case "$(uname -m)" in
    x86_64|amd64)   MTX_ARCH="linux_amd64" ;;
    aarch64|arm64)  MTX_ARCH="linux_arm64" ;;
    armv7l|armv7*)  MTX_ARCH="linux_armv7" ;;
    armv6l)         MTX_ARCH="linux_armv6" ;;
    *) die "Unsupported CPU $(uname -m) — install MediaMTX manually into ${MTX_DIR}/mediamtx." ;;
esac

binary_runs() { [[ -f "${MTX_DIR}/mediamtx" ]] && chmod +x "${MTX_DIR}/mediamtx" && "${MTX_DIR}/mediamtx" --version >/dev/null 2>&1; }

if binary_runs; then
    log "MediaMTX binary OK ($("${MTX_DIR}/mediamtx" --version 2>&1 | head -1))"
else
    url="https://github.com/bluenviron/mediamtx/releases/download/${MTX_VERSION}/mediamtx_${MTX_VERSION}_${MTX_ARCH}.tar.gz"
    log "Bundled MediaMTX doesn't run on this CPU — downloading ${MTX_ARCH} build"
    tmp="$(mktemp -d)"
    trap 'rm -rf "$tmp"' EXIT
    curl -fsSL "$url" -o "${tmp}/mediamtx.tar.gz" || die "Download failed: ${url}"
    tar -xzf "${tmp}/mediamtx.tar.gz" -C "$tmp" mediamtx || die "Archive has no mediamtx binary: ${url}"
    install -m 755 "${tmp}/mediamtx" "${MTX_DIR}/mediamtx"
    binary_runs || die "Downloaded MediaMTX still doesn't run — check: ${MTX_DIR}/mediamtx --version"
    log "Installed MediaMTX ${MTX_VERSION} (${MTX_ARCH})"
fi
chown "${EMS_USER}:" "${MTX_DIR}/mediamtx"

# ----------------------------------------------------------------------
# 2. systemd service
# ----------------------------------------------------------------------
log "Installing ${UNIT_DEST} (runs as ${EMS_USER})"
sed -e "s|__EMS_DIR__|${EMS_DIR}|g" -e "s|__EMS_USER__|${EMS_USER}|g" \
    "${MTX_DIR}/ems-mediamtx.service" > "$UNIT_DEST"
systemctl daemon-reload
systemctl enable ems-mediamtx.service >/dev/null
systemctl restart ems-mediamtx.service

for _ in 1 2 3 4 5 6 7 8 9 10; do
    curl -fs http://127.0.0.1:9997/v3/config/global/get >/dev/null 2>&1 && break
    sleep 1
done
if curl -fs http://127.0.0.1:9997/v3/config/global/get >/dev/null 2>&1; then
    log "MediaMTX is running (API on 127.0.0.1:9997)"
else
    warn "MediaMTX API isn't answering — check: sudo journalctl -u ems-mediamtx.service -n 50"
fi

# ----------------------------------------------------------------------
# 3. nginx route for the browser's WebRTC (WHEP) requests
# ----------------------------------------------------------------------
if [[ -f "$NGINX_SITE" ]]; then
    if grep -q 'cctv-stream' "$NGINX_SITE"; then
        log "nginx already routes /cctv-stream/ — leaving ${NGINX_SITE} as is"
    else
        log "Adding the /cctv-stream/ WebRTC route to ${NGINX_SITE}"
        cp "$NGINX_SITE" "${NGINX_SITE}.bak"
        snippet="$(mktemp)"
        cat > "$snippet" <<'NGINXEOF'
    # CCTV live view: WebRTC signaling (WHEP) to MediaMTX on 127.0.0.1:8889.
    # Only .../whep goes there; /cctv-stream/<camera>/ptz is a dashboard route.
    location ~ ^/cctv-stream/([^/]+/whep(/.*)?)$ {
        proxy_pass http://127.0.0.1:8889/$1$is_args$args;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

NGINXEOF
        # Insert before the first "location / {" (in the dashboard server block).
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
            warn "nginx rejected the new route — restored the previous config. Check: sudo nginx -t"
        fi
    fi
else
    warn "${NGINX_SITE} not found (run deploy.sh first) — skipping the nginx route."
fi

# ----------------------------------------------------------------------
# 4. Firewall: WebRTC media goes straight to MediaMTX over UDP 8189
# ----------------------------------------------------------------------
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
    ufw allow 8189/udp comment "MediaMTX WebRTC (CCTV live view)" >/dev/null
    log "ufw: opened 8189/udp for the CCTV live view"
fi

# ----------------------------------------------------------------------
# 5. Push the cameras to MediaMTX
# ----------------------------------------------------------------------
if [[ -f "${LARAVEL_DIR}/artisan" ]]; then
    log "Registering the enabled cameras with MediaMTX (php artisan cameras:resync)"
    ( cd "$LARAVEL_DIR" && sudo -u www-data php artisan cameras:resync ) || \
        warn "Some cameras could not be set up — the reasons are listed above (also shown on CCTV inventory)."
fi

log "Done. Logs: sudo journalctl -u ems-mediamtx.service -f"
