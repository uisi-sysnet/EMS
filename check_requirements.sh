#!/usr/bin/env bash
#
# check_requirements.sh — verifies the IoT gateway stack is actually usable:
#   1. Python packages from requirements.txt
#   2. PostgreSQL (installed, service active, and a live login using the
#      SYSTEM_DB_* credentials from .env, if present)
#   3. MQTT broker / Mosquitto (installed, service active, and a live publish
#      using the MQTT_* credentials from .env, if present)
#   4. NTP / time sync (installed, service active, sync status, and the
#      current date/time/timezone)
#
# On any failure or missing piece, it prints what to do about it right below
# the failure — this is meant to be read top to bottom after a run, not just
# grepped for a pass/fail line.
#
# Exit code: 0 if every check passed, 1 if anything failed/was missing,
# 2 if a hard precondition (python3, requirements.txt) is missing entirely.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REQ_FILE="${SCRIPT_DIR}/requirements.txt"
ENV_FILE="${SCRIPT_DIR}/.env"
PYTHON_BIN="${PYTHON_BIN:-python3}"

OVERALL_STATUS=0

log()  { echo -e "\033[1;32m[check]\033[0m $*"; }
warn() { echo -e "\033[1;33m[check][WARN]\033[0m $*"; }
fail() { echo -e "\033[1;31m[check][FAIL]\033[0m $*"; OVERALL_STATUS=1; }
todo() { echo -e "         \033[1;36m->\033[0m $*"; }

# ----------------------------------------------------------------------
# 0. Load .env (best-effort). Parsed as plain KEY=VALUE data, not sourced —
#    same approach deploy.sh uses, so values with spaces/quotes/$(...) can't
#    be mis-parsed or accidentally executed.
# ----------------------------------------------------------------------
load_env_file() {
    local file="$1" line key value
    while IFS= read -r line || [[ -n "$line" ]]; do
        line="${line%$'\r'}"
        [[ -z "${line//[[:space:]]/}" ]] && continue
        [[ "$line" =~ ^[[:space:]]*# ]] && continue
        [[ "$line" == *"="* ]] || continue

        key="${line%%=*}"
        value="${line#*=}"
        key="$(echo -n "$key" | xargs)"

        if [[ "$value" == \"*\" && "$value" == *\" ]]; then
            value="${value:1:-1}"
        elif [[ "$value" == \'*\' && "$value" == *\' ]]; then
            value="${value:1:-1}"
        fi

        [[ -n "$key" ]] || continue
        export "$key=$value"
    done < "$file"
}

if [[ -f "$ENV_FILE" ]]; then
    load_env_file "$ENV_FILE"
    log "Loaded credentials from $ENV_FILE"
else
    warn ".env not found at $ENV_FILE — PostgreSQL/MQTT live login tests will be skipped."
fi

# ----------------------------------------------------------------------
# 1. Python packages
# ----------------------------------------------------------------------
echo
echo "=== Python packages ==="

if ! command -v "${PYTHON_BIN}" >/dev/null 2>&1; then
    echo "[check] ${PYTHON_BIN} not found" >&2
    exit 2
fi

if [[ ! -f "$REQ_FILE" ]]; then
    echo "[check] requirements file not found: $REQ_FILE" >&2
    exit 2
fi

log "Checking Python packages from $REQ_FILE"

if ! "${PYTHON_BIN}" - <<'PY' "$REQ_FILE"
import importlib.metadata as im
import re
import sys
from pathlib import Path

req_file = Path(sys.argv[1])
missing = []
installed = []

for raw_line in req_file.read_text(encoding="utf-8").splitlines():
    line = raw_line.split("#", 1)[0].strip()
    if not line:
        continue

    # Keep only the package name before version specifiers/extras
    name = re.split(r"[<>=!~\[]", line, 1)[0].strip()
    name = name.split(";", 1)[0].strip()
    if not name:
        continue

    # Normalize common pip naming differences
    name = name.replace("_", "-")

    try:
        version = im.version(name)
    except im.PackageNotFoundError:
        missing.append(name)
    else:
        installed.append((name, version))

for pkg_name, version in sorted(installed):
    print(f"OK {pkg_name} {version}")

if missing:
    print("MISSING:")
    for pkg_name in missing:
        print(f" MISSING {pkg_name}")
    sys.exit(1)

print("All required Python packages are installed.")
PY
then
    fail "One or more Python packages from requirements.txt are missing (see MISSING list above)."
    todo "pip3 install -r requirements.txt --break-system-packages --ignore-installed"
    todo "or re-run the full installer: sudo ./deploy.sh"
fi

# ----------------------------------------------------------------------
# 2. PostgreSQL
# ----------------------------------------------------------------------
echo
echo "=== PostgreSQL ==="

if ! command -v psql >/dev/null 2>&1; then
    fail "psql not found — PostgreSQL is not installed."
    todo "sudo ./deploy.sh   (installs PostgreSQL + TimescaleDB)"
else
    log "psql found: $(command -v psql)"

    if command -v systemctl >/dev/null 2>&1; then
        if systemctl is-active --quiet postgresql; then
            log "postgresql service is active"
        else
            fail "postgresql service is not active"
            todo "sudo systemctl start postgresql"
            todo "check logs: sudo journalctl -xeu postgresql --no-pager | tail -30"
        fi
    fi

    if [[ -n "${SYSTEM_DB_HOST:-}" && -n "${SYSTEM_DB_USER:-}" ]]; then
        DB_PORT="${SYSTEM_DB_PORT:-5432}"
        log "Testing login to postgresql://${SYSTEM_DB_USER}@${SYSTEM_DB_HOST}:${DB_PORT} using credentials from .env"
        if PGCONNECT_TIMEOUT=5 PGPASSWORD="${SYSTEM_DB_PASSWORD:-}" psql -h "$SYSTEM_DB_HOST" -p "$DB_PORT" -U "$SYSTEM_DB_USER" -d postgres -tAc "SELECT 1;" >/dev/null 2>&1; then
            log "Connected successfully as '${SYSTEM_DB_USER}' with the .env credentials"
        else
            fail "Could not log in to PostgreSQL as '${SYSTEM_DB_USER}'@'${SYSTEM_DB_HOST}:${DB_PORT}' using the .env credentials"
            todo "check it's reachable at all: pg_isready -h ${SYSTEM_DB_HOST} -p ${DB_PORT}"
            todo "if SYSTEM_DB_USER/SYSTEM_DB_PASSWORD in .env are wrong, re-run: sudo ./configure.sh"
            todo "if connecting remotely, confirm pg_hba.conf/listen_addresses allow it on the DB server"
        fi
    else
        warn "SYSTEM_DB_HOST/SYSTEM_DB_USER not set in .env — skipping live login test"
    fi
fi

# ----------------------------------------------------------------------
# 3. MQTT broker (Mosquitto)
# ----------------------------------------------------------------------
echo
echo "=== MQTT broker (Mosquitto) ==="

if ! command -v mosquitto_pub >/dev/null 2>&1; then
    fail "mosquitto_pub not found — mosquitto-clients is not installed."
    todo "sudo ./deploy.sh   (installs Mosquitto + clients)"
else
    log "mosquitto_pub found: $(command -v mosquitto_pub)"

    if command -v systemctl >/dev/null 2>&1; then
        if systemctl is-active --quiet mosquitto; then
            log "mosquitto service is active"
        else
            fail "mosquitto service is not active"
            todo "sudo systemctl start mosquitto"
            todo "check logs: sudo journalctl -xeu mosquitto --no-pager | tail -30"
        fi
    fi

    if [[ -n "${MQTT_BROKER_HOST:-}" && -n "${MQTT_USER:-}" ]]; then
        MQTT_PORT="${MQTT_BROKER_PORT:-1883}"
        TEST_TOPIC="healthcheck/check_requirements"
        log "Testing publish to mqtt://${MQTT_USER}@${MQTT_BROKER_HOST}:${MQTT_PORT} using credentials from .env"
        if timeout 5 mosquitto_pub -h "$MQTT_BROKER_HOST" -p "$MQTT_PORT" -u "$MQTT_USER" -P "${MQTT_PASSWORD:-}" -t "$TEST_TOPIC" -m "check_requirements ping" -q 0 >/dev/null 2>&1; then
            log "Published a test message successfully as '${MQTT_USER}' with the .env credentials"
        else
            fail "Could not publish to the MQTT broker as '${MQTT_USER}'@'${MQTT_BROKER_HOST}:${MQTT_PORT}' using the .env credentials"
            todo "check it's reachable at all: mosquitto_sub -h ${MQTT_BROKER_HOST} -p ${MQTT_PORT} -t '\$SYS/#' -C 1 -u ${MQTT_USER} -P '<password>'"
            todo "if MQTT_USER/MQTT_PASSWORD in .env are wrong, re-run: sudo ./configure.sh"
            todo "check broker-side logs: sudo journalctl -xeu mosquitto --no-pager | tail -30"
        fi
    else
        warn "MQTT_BROKER_HOST/MQTT_USER not set in .env — skipping live publish test"
    fi
fi

# ----------------------------------------------------------------------
# 4. NTP / time sync
# ----------------------------------------------------------------------
echo
echo "=== NTP (time sync) ==="

NTP_CLIENT_FOUND=false
if command -v ntpq >/dev/null 2>&1; then
    log "ntpq found: $(command -v ntpq)"
    NTP_CLIENT_FOUND=true
elif command -v chronyc >/dev/null 2>&1; then
    log "chronyc found: $(command -v chronyc)"
    NTP_CLIENT_FOUND=true
else
    fail "No NTP client (ntpq/chronyc) found — ntpsec is probably not installed."
    todo "sudo ./deploy.sh   (installs ntpsec)"
fi

if command -v systemctl >/dev/null 2>&1; then
    if systemctl is-active --quiet ntpsec 2>/dev/null; then
        log "ntpsec service is active"
    elif systemctl is-active --quiet ntp 2>/dev/null; then
        log "ntp service is active"
    elif systemctl is-active --quiet chrony 2>/dev/null; then
        log "chrony service is active"
    else
        fail "No active NTP service found (checked ntpsec/ntp/chrony)"
        todo "sudo systemctl start ntpsec"
        todo "check logs: sudo journalctl -xeu ntpsec --no-pager | tail -30"
    fi
fi

if command -v timedatectl >/dev/null 2>&1; then
    SYNCED="$(timedatectl show --property=NTPSynchronized --value 2>/dev/null || true)"
    if [[ "$SYNCED" == "yes" ]]; then
        log "Clock is NTP-synchronized"
    else
        fail "Clock is NOT reported as NTP-synchronized"
        todo "sudo systemctl restart ntpsec, then wait ~1 minute and re-check: timedatectl status"
        todo "if this box has no internet access, NTP has nothing to sync against — check connectivity"
    fi
else
    warn "timedatectl not found — cannot verify sync status"
fi

echo
log "Current date/time : $(date '+%Y-%m-%d %H:%M:%S %Z')"
if command -v timedatectl >/dev/null 2>&1; then
    TZ_NAME="$(timedatectl show --property=Timezone --value 2>/dev/null || true)"
    log "Timezone/location : ${TZ_NAME:-unknown}"
    if [[ -z "$TZ_NAME" || "$TZ_NAME" == "Etc/UTC" ]]; then
        warn "Timezone looks like the default (Etc/UTC) — set the real location if that's not correct:"
        todo "sudo raspi-config   -> Localisation Options -> Timezone"
    fi
fi

if [[ "$NTP_CLIENT_FOUND" == true ]] && command -v ntpq >/dev/null 2>&1; then
    echo
    log "NTP peer status (ntpq -p):"
    ntpq -p 2>/dev/null || warn "Could not query ntpq -p — service may not be reachable yet."
fi

# ----------------------------------------------------------------------
# Summary
# ----------------------------------------------------------------------
echo
echo "=========================================="
if [[ $OVERALL_STATUS -eq 0 ]]; then
    log "All checks passed."
else
    fail "One or more checks failed — see the -> lines above for what to do about each."
fi
echo "=========================================="

exit $OVERALL_STATUS