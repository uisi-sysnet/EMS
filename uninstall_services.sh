#!/usr/bin/env bash
#
# uninstall_services.sh — stops, disables, and removes the EMS systemd units
# (ems-air-quality.service, ems-seismic.service, ems-api.service, ems.target)
# that install_services.sh deploys from the *.template files.
#
# This only touches systemd registration — it does NOT remove the Python
# scripts, .env, the PostgreSQL/TimescaleDB data, or the Mosquitto config.
#
# Usage:
#   sudo ./uninstall_services.sh        # asks for confirmation first
#   sudo ./uninstall_services.sh -y     # skip the confirmation prompt

set -euo pipefail

UNIT_DIR="/etc/systemd/system"
SERVICES=(ems-air-quality.service ems-seismic.service ems-api.service)
TARGET="ems.target"

log()  { echo -e "\033[1;32m[uninstall]\033[0m $*"; }
warn() { echo -e "\033[1;33m[uninstall][WARN]\033[0m $*"; }
die()  { echo -e "\033[1;31m[uninstall][ERROR]\033[0m $*" >&2; exit 1; }

if [[ $EUID -ne 0 ]]; then
    die "Run this with sudo: sudo ./uninstall_services.sh"
fi

FORCE=false
for arg in "$@"; do
    case "$arg" in
        -y|--yes) FORCE=true ;;
        *) die "Unknown option: $arg (supported: -y / --yes)" ;;
    esac
done

# ----------------------------------------------------------------------
# 1. Find what's actually installed
# ----------------------------------------------------------------------
FOUND=()
for u in "${SERVICES[@]}" "$TARGET"; do
    [[ -f "${UNIT_DIR}/${u}" ]] && FOUND+=("$u")
done

if [[ ${#FOUND[@]} -eq 0 ]]; then
    log "No EMS systemd units found under ${UNIT_DIR} — nothing to remove."
    exit 0
fi

echo "The following systemd units will be stopped, disabled, and removed:"
for u in "${FOUND[@]}"; do
    echo "  - ${UNIT_DIR}/${u}"
done
echo

if [[ "$FORCE" != true ]]; then
    read -r -p "Proceed? (y/n) [n]: " CONFIRM || true
    if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then
        log "Aborted — nothing changed."
        exit 0
    fi
fi

# ----------------------------------------------------------------------
# 2. Stop (target first — PartOf= means stopping it stops the services too,
#    but we stop each service individually as well as a belt-and-suspenders
#    in case it was ever started/enabled independently of the target)
# ----------------------------------------------------------------------
log "Stopping units"
systemctl stop "$TARGET" 2>/dev/null || true
for s in "${SERVICES[@]}"; do
    systemctl stop "$s" 2>/dev/null || true
done

# ----------------------------------------------------------------------
# 3. Disable (removes the WantedBy= symlinks so they won't come back on boot)
# ----------------------------------------------------------------------
log "Disabling units"
systemctl disable "$TARGET" 2>/dev/null || true
for s in "${SERVICES[@]}"; do
    systemctl disable "$s" 2>/dev/null || true
done

# ----------------------------------------------------------------------
# 4. Remove the unit files themselves
# ----------------------------------------------------------------------
log "Removing unit files"
for u in "${FOUND[@]}"; do
    rm -f "${UNIT_DIR}/${u}"
    log "Removed ${UNIT_DIR}/${u}"
done

# ----------------------------------------------------------------------
# 5. Tell systemd to forget about them
# ----------------------------------------------------------------------
log "Reloading systemd"
systemctl daemon-reload
systemctl reset-failed 2>/dev/null || true

log "Done. EMS services and target have been removed from systemd."
log "Note: the Python scripts, .env, PostgreSQL/TimescaleDB data, and Mosquitto config were left untouched."