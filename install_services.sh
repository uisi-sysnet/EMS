#!/usr/bin/env bash
#
# install_services.sh — installs the EMS systemd units so all three
# Python services run together, auto-restart on failure, and start on boot.
#
# Usage: sudo ./install_services.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
UNIT_DEST="/etc/systemd/system"

log()  { echo -e "\033[1;32m[install]\033[0m $*"; }
die()  { echo -e "\033[1;31m[install][ERROR]\033[0m $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Run this with sudo: sudo ./install_services.sh"

for f in ems-air-quality.service ems-seismic.service ems-api.service ems.target; do
    [[ -f "${SCRIPT_DIR}/${f}" ]] || die "Missing ${f} next to this script."
done

log "Copying unit files to ${UNIT_DEST}"
cp "${SCRIPT_DIR}/ems-air-quality.service" "${UNIT_DEST}/"
cp "${SCRIPT_DIR}/ems-seismic.service" "${UNIT_DEST}/"
cp "${SCRIPT_DIR}/ems-api.service" "${UNIT_DEST}/"
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
  sudo systemctl status ems.target              # overview of all three
  sudo systemctl restart ems.target              # restart everything together
  sudo systemctl stop ems.target                 # stop everything together
  sudo systemctl status ems-air-quality.service   # check one service individually
  sudo journalctl -u ems-air-quality.service -f   # live logs for one service
  sudo journalctl -u ems-seismic.service -f
  sudo journalctl -u ems-api.service -f

EOF