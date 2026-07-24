#!/usr/bin/env bash
#
# install_services.sh — installs the EMS systemd units so all three
# Python services run together, auto-restart on failure, and start on boot.
#
# This auto-detects the folder it's run from and the user that owns it,
# so it works correctly no matter where this project lives on disk —
# no manual path editing required.
#
# Usage: sudo ./install_services.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
UNIT_DEST="/etc/systemd/system"

log()  { echo -e "\033[1;32m[install]\033[0m $*"; }
warn() { echo -e "\033[1;33m[install][WARN]\033[0m $*"; }
die()  { echo -e "\033[1;31m[install][ERROR]\033[0m $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Run this with sudo: sudo ./install_services.sh"

for f in ems-air-quality_service.template ems-seismic_service.template ems-api_service.template ems.target; do
    [[ -f "${SCRIPT_DIR}/${f}" ]] || die "Missing ${f} next to this script."
done

# ----------------------------------------------------------------------
# Auto-detect: where this project actually lives, and who owns it.
# ----------------------------------------------------------------------
EMS_DIR="$SCRIPT_DIR"

# Prefer the user who invoked sudo (so services don't end up owned by
# root just because you ran this with sudo). Fall back to the owner of
# this script's folder if SUDO_USER isn't set (e.g. run directly as root).
if [[ -n "${SUDO_USER:-}" ]]; then
    EMS_USER="$SUDO_USER"
else
    EMS_USER="$(stat -c '%U' "$SCRIPT_DIR")"
    warn "No SUDO_USER detected — using the folder owner '${EMS_USER}' as the service user."
fi

for py in air_quality_ingest.py seismic_mqtt.py api_server.py; do
    [[ -f "${EMS_DIR}/${py}" ]] || warn "${py} not found in ${EMS_DIR} — the corresponding service will fail to start until it's there."
done

# db_logging.py is imported (unconditionally, when DB_LOG_ENABLED=true) by
# all three scripts — unlike sim800l.py, that import isn't soft-caught, so
# a missing file here would crash every service, not just seismic's SMS path.
[[ -f "${EMS_DIR}/db_logging.py" ]] || warn "db_logging.py not found in ${EMS_DIR} — all three services will crash on startup if DB_LOG_ENABLED=true (the default)."
[[ -f "${EMS_DIR}/sim800l.py" ]] || warn "sim800l.py not found in ${EMS_DIR} — SMS ingestion will be disabled automatically (MQTT ingestion is unaffected)."

log "Detected project directory: ${EMS_DIR}"
log "Detected service user:      ${EMS_USER}"

# ----------------------------------------------------------------------
# Render templates -> real unit files, substituting the detected values.
# ----------------------------------------------------------------------
render() {
    local template="$1" dest="$2"
    sed \
        -e "s|__EMS_DIR__|${EMS_DIR}|g" \
        -e "s|__EMS_USER__|${EMS_USER}|g" \
        "${SCRIPT_DIR}/${template}" > "${dest}"
}

log "Generating unit files and copying to ${UNIT_DEST}"
render "ems-air-quality_service.template" "${UNIT_DEST}/ems-air-quality.service"
render "ems-seismic_service.template"     "${UNIT_DEST}/ems-seismic.service"
render "ems-api_service.template"         "${UNIT_DEST}/ems-api.service"
cp "${SCRIPT_DIR}/ems.target" "${UNIT_DEST}/"

log "Reloading systemd unit definitions"
systemctl daemon-reload

log "Enabling ems.target (and each service) to start on boot"
systemctl enable ems.target
systemctl enable ems-air-quality.service ems-seismic.service ems-api.service

log "Starting all three services now"
systemctl start ems.target

log "Done. Current status:"
systemctl status ems-air-quality.service ems-seismic.service ems-api.service --no-pager || true

cat <<EOF

Useful commands:
  sudo systemctl status ems.target                # overview of all three
  sudo systemctl restart ems.target               # restart everything together
  sudo systemctl stop ems.target                  # stop everything together
  sudo systemctl status ems-air-quality.service    # check one service individually
  sudo journalctl -u ems-air-quality.service -f    # live logs for one service
  sudo journalctl -u ems-seismic.service -f
  sudo journalctl -u ems-api.service -f

EOF