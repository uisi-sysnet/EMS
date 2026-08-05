#!/usr/bin/env bash
#
# deploy.sh — sets up everything needed to run air_quality_ingest.py,
# seismic_mqtt.py, and api_server.py on a fresh Ubuntu OR Raspberry Pi OS
# (Raspbian) server. Both are Debian-based/apt/systemd, so the same script
# covers both — it auto-detects distro/codename/CPU architecture and adjusts
# where it matters (see detect_platform() below).
#
# Installs / configures:
#   1. System packages (Python, PostgreSQL, TimescaleDB, Mosquitto MQTT broker)
#   2. Python dependencies from requirements.txt (system-wide, no venv)
#   3. Postgres role + CREATEDB privilege for SYSTEM_DB_USER (shared by
#      both the AQ and Seismic databases; apps create their own
#      databases/tables on first run)
#   4. Mosquitto authentication (password file for MQTT_USER)
#
# ASSUMPTIONS (adjust the variables below if these don't match your box):
#   - Ubuntu 22.04/24.04 LTS, OR Raspberry Pi OS (Debian bookworm or newer),
#     64-bit (arm64) STRONGLY recommended on a Pi — see detect_platform().
#   - PostgreSQL 16 (auto-detected where possible, override with PG_VERSION)
#   - This script, .env, requirements.txt, and the three *.py files all
#     live in the same directory. If .env is missing, this script asks a
#     few questions on the terminal (DB/MQTT: local or remote IP, host,
#     port, credentials) and generates one — see run_env_wizard() below.
#     This needs an interactive terminal; non-interactive runs must supply
#     .env themselves.
#   - If you point SYSTEM_DB_HOST / MQTT_BROKER_HOST at a host OTHER than
#     this machine (i.e. not 127.0.0.1/localhost), the local Postgres +
#     TimescaleDB / Mosquitto install and account-creation steps below are
#     skipped — that remote server is assumed to already be set up.
#   - You run this with sudo: `sudo ./deploy.sh`
#
# This script is idempotent where practical — safe to re-run.

set -euo pipefail

# ----------------------------------------------------------------------
# 0. Preflight
# ----------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/scripts/.env"
REQ_FILE="${SCRIPT_DIR}/requirements.txt"
PG_VERSION="${PG_VERSION:-16}"   # override: PG_VERSION=15 sudo -E ./deploy.sh
LARAVEL_DIR="${LARAVEL_DIR:-${SCRIPT_DIR}/Dashboard}"   # override: LARAVEL_DIR=/path/to/Dashboard sudo -E ./deploy.sh

log()  { echo -e "\033[1;32m[deploy]\033[0m $*"; }
warn() { echo -e "\033[1;33m[deploy][WARN]\033[0m $*"; }
die()  { echo -e "\033[1;31m[deploy][ERROR]\033[0m $*" >&2; exit 1; }

if [[ $EUID -ne 0 ]]; then
    die "Run this with sudo: sudo ./deploy.sh"
fi

# ----------------------------------------------------------------------
# 0b. Platform detection (Ubuntu vs Raspberry Pi OS, and CPU architecture)
# ----------------------------------------------------------------------
# TimescaleDB's official apt packages are published for amd64 and arm64
# only — there are no armhf (32-bit Raspberry Pi OS) builds. Everything
# else in this script (Postgres itself, Mosquitto, ntpsec, Python deps) is
# fine on 32-bit, so we only hard-warn about that one piece here rather
# than refusing to run.
ARCH="$(dpkg --print-architecture)"
OS_ID="unknown"
OS_CODENAME="unknown"
if [[ -f /etc/os-release ]]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    OS_ID="${ID:-unknown}"
    OS_CODENAME="${VERSION_CODENAME:-unknown}"
fi

log "Detected platform: ID=${OS_ID} codename=${OS_CODENAME} arch=${ARCH}"

TIMESCALEDB_ARCH_SUPPORTED=true
if [[ "$ARCH" != "amd64" && "$ARCH" != "arm64" ]]; then
    TIMESCALEDB_ARCH_SUPPORTED=false
    warn "Architecture '${ARCH}' detected (looks like 32-bit Raspberry Pi OS/armhf)."
    warn "TimescaleDB does not publish official apt packages for this architecture."
    warn "The TimescaleDB install step below will likely fail. Recommended fix:"
    warn "  re-flash with 64-bit Raspberry Pi OS (arm64) — it runs fine on the same"
    warn "  Pi hardware and is what this project is tested against."
    warn "Continuing anyway in case you have your own TimescaleDB build available..."
fi

if [[ "$OS_ID" == "raspbian" || "$OS_ID" == "debian" ]] && [[ "$ARCH" == "arm64" || "$ARCH" == "armhf" ]]; then
    log "Raspberry Pi OS detected — applying SD-card-friendly install options (--no-install-recommends, etc.)"
    ON_RASPBERRY_PI=true
else
    ON_RASPBERRY_PI=false
fi

# A Pi with 1-2GB RAM can run out of memory during `apt-get install` /
# `pip install` builds if it has little/no swap. This is informational
# only — we don't modify swap automatically since dphys-swapfile config
# varies by image and isn't ours to rewrite silently.
if [[ "$ON_RASPBERRY_PI" == true ]] && command -v free >/dev/null 2>&1; then
    SWAP_TOTAL_MB=$(free -m | awk '/^Swap:/{print $2}')
    MEM_TOTAL_MB=$(free -m | awk '/^Mem:/{print $2}')
    if [[ "${SWAP_TOTAL_MB:-0}" -lt 512 && "${MEM_TOTAL_MB:-0}" -lt 2048 ]]; then
        warn "Low RAM (${MEM_TOTAL_MB}MB) and little/no swap (${SWAP_TOTAL_MB}MB) detected."
        warn "If package installs below get killed (OOM), increase swap first, e.g.:"
        warn "  sudo dphys-swapfile swapoff; sudo sed -i 's/CONF_SWAPSIZE=.*/CONF_SWAPSIZE=1024/' /etc/dphys-swapfile; sudo dphys-swapfile setup; sudo dphys-swapfile swapon"
    fi
fi

[[ -f "$REQ_FILE" ]] || die "requirements.txt not found at $REQ_FILE."

# ----------------------------------------------------------------------
# 0c. .env — generate one interactively if it doesn't exist yet
# ----------------------------------------------------------------------
ask_yes_no() {
    # $1=prompt  $2=default (Y or N). Sets ASK_YES_NO_RESULT to true/false.
    local prompt="$1" default="${2:-Y}" ans hint
    [[ "${default^^}" == "Y" ]] && hint="[Y/n]" || hint="[y/N]"
    read -rp "${prompt} ${hint}: " ans
    ans="${ans:-$default}"
    if [[ "${ans,,}" == y || "${ans,,}" == yes ]]; then
        ASK_YES_NO_RESULT=true
    else
        ASK_YES_NO_RESULT=false
    fi
}

prompt_password() {
    # $1=label. Sets REPLY_PASSWORD. Re-prompts on blank/mismatched entries.
    local label="$1" p1 p2
    while true; do
        read -rsp "${label}: " p1; echo
        read -rsp "Confirm ${label}: " p2; echo
        if [[ -z "$p1" ]]; then
            echo "  Password cannot be blank — try again."
        elif [[ "$p1" != "$p2" ]]; then
            echo "  Passwords didn't match — try again."
        else
            REPLY_PASSWORD="$p1"
            break
        fi
    done
}

run_env_wizard() {
    echo
    log "No .env found at ${ENV_FILE} — let's generate one."
    echo "Answer the questions below (press Enter to accept a [default] where shown)."
    echo

    # ---- Database ----
    local db_host db_port db_user db_pass
    ask_yes_no "Is the PostgreSQL database running on THIS machine?" Y
    if [[ "$ASK_YES_NO_RESULT" == true ]]; then
        db_host="127.0.0.1"
        log "Database: local (${db_host}) — deploy.sh will install/configure Postgres + TimescaleDB below."
    else
        read -rp "Database host or IP: " db_host
        while [[ -z "$db_host" ]]; do
            read -rp "  (required) Database host or IP: " db_host
        done
        log "Database: remote (${db_host}) — deploy.sh will skip the local Postgres/TimescaleDB install"
        log "and role creation; that server must already have the role/DB set up and reachable from here."
    fi
    read -rp "Database port [5432]: " db_port
    db_port="${db_port:-5432}"
    read -rp "Database username [iot_user]: " db_user
    db_user="${db_user:-iot_user}"
    prompt_password "Database password for '${db_user}'"
    db_pass="$REPLY_PASSWORD"
    echo

    # ---- MQTT ----
    local mqtt_host mqtt_port mqtt_user mqtt_pass
    ask_yes_no "Is the MQTT broker (Mosquitto) running on THIS machine?" Y
    if [[ "$ASK_YES_NO_RESULT" == true ]]; then
        mqtt_host="localhost"
        log "MQTT: local (${mqtt_host}) — deploy.sh will install/configure Mosquitto below."
    else
        read -rp "MQTT broker host or IP: " mqtt_host
        while [[ -z "$mqtt_host" ]]; do
            read -rp "  (required) MQTT broker host or IP: " mqtt_host
        done
        log "MQTT: remote (${mqtt_host}) — deploy.sh will skip the local Mosquitto install/config;"
        log "that broker must already have the matching user account created."
    fi
    read -rp "MQTT broker port [1883]: " mqtt_port
    mqtt_port="${mqtt_port:-1883}"
    read -rp "MQTT username [mqtt_user_seismic]: " mqtt_user
    mqtt_user="${mqtt_user:-mqtt_user_seismic}"
    prompt_password "MQTT password for '${mqtt_user}'"
    mqtt_pass="$REPLY_PASSWORD"
    echo

    log "Writing ${ENV_FILE}"
    cat > "$ENV_FILE" <<ENVEOF
# =========================================================
# SHARED ENVIRONMENT CONFIG
# Generated by deploy.sh's setup wizard on $(date -Iseconds)
# Used by: air_quality_ingest.py, seismic_mqtt.py, api_server.py
# =========================================================

# ---- Database connection ----
SYSTEM_DB_HOST=${db_host}
SYSTEM_DB_PORT=${db_port}
SYSTEM_DB_USER=${db_user}
SYSTEM_DB_PASSWORD=${db_pass}
SYSTEM_DB_POOL_MIN=2
SYSTEM_DB_POOL_MAX=10
DB_LOG_ENABLED=true
DB_LOG_TABLE=service_logs

# --- Database names ----
AQ_DB_NAME=IOT_aq_sensor_data
SEISMIC_DB_NAME=IOT_seismic_sensor_data
SMS_DB_NAME=IOT_sms_telemetry
API_DB_NAME=IOT_api
LOG_DB_NAME=IOT_service_logs

# ---- Air Quality Ingestion (TCP / HJ212) ----
AQ_SERVER_HOST=0.0.0.0
AQ_SERVER_PORT=1935
AQ_LEAD_POLL_INTERVAL=30
AQ_STATIONS_REFRESH_INTERVAL_SEC=300

# ---- MQTT (Seismic) ----
MQTT_BROKER_HOST=${mqtt_host}
MQTT_BROKER_PORT=${mqtt_port}
MQTT_TIMEOUT_SEC=60
MQTT_TOPIC=seismic/stations/+/telemetry
MQTT_USER=${mqtt_user}
MQTT_PASSWORD=${mqtt_pass}

# ---- SMS (Seismic, second channel via SIM800L) ----
SMS_INGESTION_ENABLED=true
SIM800_SERIAL_PORT=/dev/serial0
SIM800_BAUDRATE=115200
SMS_POLL_INTERVAL_SEC=30
SMS_ALLOWED_SENDERS=

# ---- API Server ----
API_PORT=8000
# Format: token:owner_label,token:owner_label,...
# Not asked by the wizard — set at least one token before running api_server.py.
API_KEYS=
ENVEOF

    chmod 600 "$ENV_FILE"
    log ".env written to ${ENV_FILE} (mode 600)."
    warn "API_KEYS was left blank (the wizard doesn't ask for it) — set at least one token there before running api_server.py."
}

if [[ ! -f "$ENV_FILE" ]]; then
    [[ -t 0 ]] || die ".env not found at $ENV_FILE, and no terminal is attached to answer setup questions. Run deploy.sh interactively, or copy a .env here first."
    run_env_wizard
else
    log ".env already exists at $ENV_FILE — skipping the setup wizard (delete/rename it and re-run to regenerate)."
fi

log "Loading configuration from .env"

# NOTE: we deliberately do NOT `source` .env. Sourcing treats the file as
# executable bash, so any value containing spaces, semicolons, $(...), or
# backticks (e.g. API_KEYS="token:Some Label, other:Another Label") either
# breaks parsing or — worse — gets executed as a shell command. Instead we
# parse it as plain KEY=VALUE data, one line at a time.
load_env_file() {
    local file="$1" line key value
    while IFS= read -r line || [[ -n "$line" ]]; do
        line="${line%$'\r'}"                          # strip trailing CR (Windows-saved files)
        [[ -z "${line//[[:space:]]/}" ]] && continue   # skip blank lines
        [[ "$line" =~ ^[[:space:]]*# ]] && continue    # skip comment lines
        [[ "$line" == *"="* ]] || continue              # must look like KEY=VALUE

        key="${line%%=*}"
        value="${line#*=}"
        key="$(echo -n "$key" | xargs)"                # trim whitespace around key

        # strip one layer of matching surrounding quotes, if present
        if [[ "$value" == \"*\" && "$value" == *\" ]]; then
            value="${value:1:-1}"
        elif [[ "$value" == \'*\' && "$value" == *\' ]]; then
            value="${value:1:-1}"
        fi

        [[ -n "$key" ]] || continue
        export "$key=$value"
    done < "$file"
}

load_env_file "$ENV_FILE"

for v in SYSTEM_DB_USER SYSTEM_DB_PASSWORD AQ_DB_NAME SEISMIC_DB_NAME MQTT_USER MQTT_PASSWORD; do
    [[ -n "${!v:-}" ]] || die "Missing required variable '$v' in .env"
done

# Whether the DB/MQTT broker live on this box, derived from .env — works
# whether .env was just generated above or already existed. Drives whether
# the local install/account-creation steps further down run at all.
DB_IS_LOCAL=true
[[ "${SYSTEM_DB_HOST:-127.0.0.1}" == "127.0.0.1" || "${SYSTEM_DB_HOST:-}" == "localhost" ]] || DB_IS_LOCAL=false
MQTT_IS_LOCAL=true
[[ "${MQTT_BROKER_HOST:-localhost}" == "127.0.0.1" || "${MQTT_BROKER_HOST:-}" == "localhost" ]] || MQTT_IS_LOCAL=false
log "Database host: ${SYSTEM_DB_HOST:-127.0.0.1} (local install: ${DB_IS_LOCAL})"
log "MQTT host: ${MQTT_BROKER_HOST:-localhost} (local install: ${MQTT_IS_LOCAL})"

# ----------------------------------------------------------------------
# 1. System packages
# ----------------------------------------------------------------------
log "Updating apt package lists"
if ! apt-get update -y; then
    # If a TimescaleDB repo file is already sitting here (e.g. left over
    # from an earlier/interrupted attempt) and isn't marked [trusted=yes]
    # yet, this is almost certainly the same packagecloud/sqv signature
    # issue on Debian trixie documented in the TimescaleDB section below —
    # see github.com/timescale/timescaledb/issues/8871 — just hit here
    # instead of there, because the repo file already existed before this
    # script's own TimescaleDB step got a chance to add/fix it. Apply the
    # identical [trusted=yes] workaround here and retry. Any OTHER cause of
    # apt-get update failing (network issue, a different broken repo, etc.)
    # still dies below rather than being silently swallowed.
    TIMESCALEDB_LIST="/etc/apt/sources.list.d/timescaledb.list"
    if [[ -f "$TIMESCALEDB_LIST" ]] && ! grep -q '\[trusted=yes\]' "$TIMESCALEDB_LIST"; then
        warn "Initial apt-get update failed, and a pre-existing TimescaleDB repo file not"
        warn "marked [trusted=yes] is present — applying the sqv/trixie signature workaround"
        warn "(see the TimescaleDB section below for the full explanation) and retrying."
        sed -i -E 's#^deb \[signed-by=[^]]*\]#deb [trusted=yes]#' "$TIMESCALEDB_LIST"
        grep -q '\[trusted=yes\]' "$TIMESCALEDB_LIST" || rm -f "$TIMESCALEDB_LIST"
        apt-get update -y || die "apt-get update still failing after the TimescaleDB repo workaround — see the error above, likely a different repo/cause."
    else
        die "apt-get update failed — see the error above."
    fi
fi

log "Installing base tools (python3, pip, curl, gnupg)"
# --no-install-recommends keeps the footprint (disk + install time) down —
# worth doing on a Pi's SD card / slower storage, harmless on Ubuntu.
apt-get install -y --no-install-recommends \
    python3 python3-pip python3-dev \
    curl gnupg lsb-release ca-certificates apt-transport-https wget \
    build-essential libpq-dev

# python3-dev/build-essential/libpq-dev let pip fall back to building
# psycopg2-binary/psycopg from source if a precompiled wheel isn't available
# for this exact Python/architecture combo. On Raspberry Pi OS, pip is
# normally already pointed at piwheels.org (prebuilt ARM wheels) via
# /etc/pip.conf, so this is a safety net rather than the common path.

# ---- PostgreSQL + TimescaleDB -----------------------------------------
if [[ "$DB_IS_LOCAL" != true ]]; then
    warn "SYSTEM_DB_HOST=${SYSTEM_DB_HOST} is not this machine — skipping local Postgres/TimescaleDB install."
    warn "Make sure that server already has PostgreSQL + the TimescaleDB extension installed and reachable."
else

if ! command -v psql >/dev/null 2>&1; then
    log "Installing PostgreSQL ${PG_VERSION} via the official PGDG repo"
    apt-get install -y --no-install-recommends postgresql-common
    /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
    apt-get install -y --no-install-recommends "postgresql-${PG_VERSION}"
else
    log "PostgreSQL already installed, skipping"
fi

if [[ "$TIMESCALEDB_ARCH_SUPPORTED" == false ]]; then
    warn "Skipping TimescaleDB install — unsupported architecture (${ARCH}). See warning above."
    warn "Postgres itself is installed and usable, but the apps' create_hypertable() calls will fail"
    warn "until TimescaleDB is available (i.e. until you're on amd64 or arm64)."
elif ! dpkg -l | grep -q "timescaledb-2-postgresql-${PG_VERSION}"; then
    log "Installing TimescaleDB extension for PostgreSQL ${PG_VERSION}"
    # packagecloud's repo keys off the OS codename; Raspberry Pi OS reports
    # its Debian codename (e.g. bookworm) via lsb_release/os-release just
    # like Debian itself, so the debian path below covers both.
    if [[ "$OS_ID" == "ubuntu" ]]; then
        TIMESCALE_REPO_OS="ubuntu"
    else
        TIMESCALE_REPO_OS="debian"
    fi
    # NOTE: we deliberately do NOT use `apt-key add` here. apt-key is
    # deprecated and has been removed outright on newer Ubuntu (24.04+)
    # and recent Raspberry Pi OS/Debian bookworm images ("apt-key: command
    # not found"). The modern equivalent is to dearmor the key into its own
    # file under /etc/apt/keyrings and reference it explicitly via
    # `signed-by` in the repo line, instead of dropping it into apt's
    # global trusted-key ring.
    install -d -m 0755 /etc/apt/keyrings
    wget --quiet -O - https://packagecloud.io/timescale/timescaledb/gpgkey | \
        gpg --batch --yes --dearmor -o /etc/apt/keyrings/timescaledb.gpg
    echo "deb [signed-by=/etc/apt/keyrings/timescaledb.gpg] https://packagecloud.io/timescale/timescaledb/${TIMESCALE_REPO_OS}/ $(lsb_release -c -s) main" \
        > /etc/apt/sources.list.d/timescaledb.list

    if ! apt-get update -y; then
        # Known issue as of Debian 13 "trixie": apt's newer sqv (Sequoia-PGP)
        # signature backend rejects packagecloud's InRelease signature for
        # this repo ("Missing key ... which is needed to verify signature")
        # even with a correctly imported, correctly referenced key. This is
        # a documented packagecloud/sqv incompatibility affecting multiple
        # unrelated packagecloud-hosted repos on trixie, not an error in how
        # the key was imported above — see
        # https://github.com/timescale/timescaledb/issues/8871
        warn "apt-get update failed for the TimescaleDB repo — this matches a known"
        warn "packagecloud/sqv signature-verification issue on Debian trixie"
        warn "(github.com/timescale/timescaledb/issues/8871), not a real key/network problem."
        warn "Falling back to [trusted=yes] for the TimescaleDB repo ONLY — this skips GPG"
        warn "verification for that one repo (packages still arrive over HTTPS). Every other"
        warn "repo on this system keeps full signature verification."
        echo "deb [trusted=yes] https://packagecloud.io/timescale/timescaledb/${TIMESCALE_REPO_OS}/ $(lsb_release -c -s) main" \
            > /etc/apt/sources.list.d/timescaledb.list
        apt-get update -y || die "apt-get update still failing after the [trusted=yes] fallback — check connectivity to packagecloud.io."
    fi
    if ! apt-get install -y --no-install-recommends "timescaledb-2-postgresql-${PG_VERSION}"; then
        die "TimescaleDB package install failed. If you're on Raspberry Pi OS, confirm you're running" \
            "the 64-bit (arm64) image and that '$(lsb_release -c -s)' is a codename TimescaleDB has" \
            "published packages for yet — check https://docs.timescale.com/self-hosted/latest/install/installation-debian/"
    fi
    # timescaledb-tune sizes shared_buffers/work_mem/etc. from detected system
    # RAM, which works in the Pi's favor automatically (a 1-2GB Pi gets much
    # smaller settings than a 16GB server) — no separate low-memory branch needed.
    # It ships in the timescaledb-tools package, which is only a "Recommended"
    # (not required) dependency of timescaledb-2-postgresql-${PG_VERSION} — so
    # --no-install-recommends above means apt does NOT pull it in automatically,
    # and the call below would silently no-op with "command not found". Install
    # it explicitly so the tune step actually runs.
    if ! command -v timescaledb-tune >/dev/null 2>&1; then
        apt-get install -y --no-install-recommends timescaledb-tools || \
            warn "Could not install timescaledb-tools — falling back to a minimal" \
                 "shared_preload_libraries fix below (without the RAM-based tuning)."
    fi
    if command -v timescaledb-tune >/dev/null 2>&1; then
        timescaledb-tune --quiet --yes --pg-config="/usr/lib/postgresql/${PG_VERSION}/bin/pg_config" || \
            warn "timescaledb-tune ran but reported an error — check config manually if needed"
    else
        warn "timescaledb-tune still unavailable — applying a minimal fix below so the"
        warn "extension at least loads (skips the RAM-based buffer/memory tuning)."
    fi
    # Whichever path was taken above, make sure 'timescaledb' actually ends up
    # in shared_preload_libraries — the extension refuses to load without it.
    # timescaledb-tune normally sets this as part of its tuning; this is the
    # safety net for when it couldn't run. Idempotent and additive — only
    # touches the setting if timescaledb isn't already listed, and preserves
    # anything else already configured there.
    CURRENT_PRELOAD="$(sudo -u postgres psql -tAc 'SHOW shared_preload_libraries;' 2>/dev/null || true)"
    if [[ "$CURRENT_PRELOAD" != *timescaledb* ]]; then
        log "Adding timescaledb to shared_preload_libraries"
        NEW_PRELOAD="timescaledb"
        [[ -n "$CURRENT_PRELOAD" ]] && NEW_PRELOAD="${CURRENT_PRELOAD},timescaledb"
        sudo -u postgres psql -v ON_ERROR_STOP=1 -q -c "ALTER SYSTEM SET shared_preload_libraries = '${NEW_PRELOAD}';" || \
            warn "Could not set shared_preload_libraries automatically — add 'timescaledb' to it" \
                 "in postgresql.conf manually (comma-separated if other libraries are listed)" \
                 "and restart postgresql before running the ingest services."
    fi
else
    log "TimescaleDB already installed, skipping"
fi

log "Restarting PostgreSQL"
systemctl restart postgresql
systemctl enable postgresql

fi  # DB_IS_LOCAL

# ---- Mosquitto MQTT broker --------------------------------------------
if [[ "$MQTT_IS_LOCAL" != true ]]; then
    warn "MQTT_BROKER_HOST=${MQTT_BROKER_HOST} is not this machine — skipping local Mosquitto install."
    warn "Make sure that broker already has the '${MQTT_USER}' account created with the matching password."
else

if ! command -v mosquitto >/dev/null 2>&1; then
    log "Installing Mosquitto MQTT broker"
    apt-get install -y --no-install-recommends mosquitto mosquitto-clients
else
    log "Mosquitto already installed, skipping"
fi

log "Configuring Mosquitto authentication for user '${MQTT_USER}'"

# Mosquitto only loads files in conf.d/ if `include_dir` is active in the
# main mosquitto.conf. If it's missing or commented out, our app.conf
# below is silently ignored and mosquitto falls back to its built-in
# default: listening on localhost ONLY (a Mosquitto 2.x security default),
# which looks like a working service but refuses all external connections.
MOSQ_MAIN_CONF="/etc/mosquitto/mosquitto.conf"
if grep -qE '^\s*#\s*include_dir\s+/etc/mosquitto/conf\.d' "$MOSQ_MAIN_CONF"; then
    log "include_dir is commented out in mosquitto.conf — enabling it so conf.d/ is actually loaded"
    sed -i 's/^\s*#\s*include_dir\s\+\/etc\/mosquitto\/conf\.d/include_dir \/etc\/mosquitto\/conf.d/' "$MOSQ_MAIN_CONF"
elif ! grep -qE '^\s*include_dir\s+/etc/mosquitto/conf\.d' "$MOSQ_MAIN_CONF"; then
    log "include_dir not found in mosquitto.conf — adding it so conf.d/ is loaded"
    echo "include_dir /etc/mosquitto/conf.d" >> "$MOSQ_MAIN_CONF"
fi

touch /etc/mosquitto/passwd
mosquitto_passwd -b /etc/mosquitto/passwd "${MQTT_USER}" "${MQTT_PASSWORD}"
# Newer mosquitto builds refuse to load the password file unless it's
# root-owned. But the mosquitto *service* runs as the unprivileged
# `mosquitto` user, so a strict root:root 600 file (no group/other read)
# would let the file exist but be unreadable by the running service,
# causing a silent-looking exit code 13 (permission denied) on start.
# root:mosquitto + 640 satisfies both constraints.
chown root:mosquitto /etc/mosquitto/passwd
chmod 640 /etc/mosquitto/passwd

# If /etc/mosquitto/mosquitto.conf already defines its own `listener`
# directive, our conf.d/app.conf below would define a second listener on
# the same port and mosquitto will fail to bind. Warn instead of silently
# fighting an existing config.
if grep -qE '^\s*listener\b' /etc/mosquitto/mosquitto.conf 2>/dev/null; then
    warn "/etc/mosquitto/mosquitto.conf already defines a 'listener' directive."
    warn "This may conflict with conf.d/app.conf below (duplicate listener on the same port)."
    warn "If mosquitto fails to start, check: sudo journalctl -xeu mosquitto.service"
fi

cat > /etc/mosquitto/conf.d/app.conf <<EOF
listener ${MQTT_BROKER_PORT:-1883} 0.0.0.0
allow_anonymous false
password_file /etc/mosquitto/passwd
EOF

# Open the MQTT port to all networks if ufw is installed and active.
# NOTE: authentication (allow_anonymous false, above) stays ON — exposing
# this port without auth would let anyone reach the broker.
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
    log "ufw is active — opening port ${MQTT_BROKER_PORT:-1883}/tcp for MQTT"
    ufw allow "${MQTT_BROKER_PORT:-1883}/tcp" comment "MQTT broker"
else
    log "ufw not active/installed — skipping firewall rule (nothing to open at the OS level)"
fi
warn "If this server sits behind a cloud provider (AWS/GCP/Azure/etc.), also open port ${MQTT_BROKER_PORT:-1883}/tcp in its security group / firewall rules — ufw alone won't cover that."

if ! systemctl restart mosquitto; then
    warn "mosquitto failed to (re)start. Diagnose with:"
    warn "  sudo systemctl status mosquitto.service"
    warn "  sudo journalctl -xeu mosquitto.service --no-pager | tail -30"
    warn "Continuing with the rest of deploy.sh — fix mosquitto separately once you see the real error."
else
    systemctl enable mosquitto
fi

fi  # MQTT_IS_LOCAL

# ---- ntpsec (NTP time sync, reachable from all networks) --------------
# Accurate, synced clocks matter here: seismic_mqtt.py timestamps events
# from an MQTT feed and air_quality_ingest.py timestamps sensor readings,
# so we install ntpsec as the system time daemon and open it up so other
# hosts on any network can query this server for time too (not just sync
# it locally).
if ! command -v ntpd >/dev/null 2>&1 && ! dpkg -l | grep -q '^ii\s\+ntpsec\s'; then
    log "Installing ntpsec"
    apt-get install -y --no-install-recommends ntpsec
else
    log "ntpsec already installed, skipping"
fi

NTP_CONF="/etc/ntpsec/ntp.conf"
if [[ -f "$NTP_CONF" ]]; then
    cp "$NTP_CONF" "${NTP_CONF}.bak.$(date +%s)" 2>/dev/null || true

    # By default ntpsec's "restrict default ... noquery" lines let this
    # host sync outbound but refuse time *queries* from anyone else. Strip
    # `noquery` from the default restrict lines (both IPv4 and IPv6) so
    # any network can ask this server for the time, while leaving
    # nomodify/notrap/nopeer in place so nobody can reconfigure it or use
    # it as a peer.
    log "Opening ntpsec to time queries from all networks (removing 'noquery' from default restrict rules)"
    sed -i -E 's/^(restrict[[:space:]]+(default|-6[[:space:]]+default)[[:space:]]+.*)\bnoquery[[:space:]]*/\1/' "$NTP_CONF"

    # If a prior config explicitly limits ntpd to specific interfaces
    # (e.g. "interface ignore wildcard" / "interface listen 127.0.0.1"),
    # that silently blocks queries from other networks even though the
    # service looks like it's running fine. Comment those out so it binds
    # to all interfaces (the ntpsec default).
    if grep -qE '^\s*interface\s+(ignore\s+wildcard|listen\s+127\.0\.0\.1)' "$NTP_CONF"; then
        log "Found an 'interface' restriction in ntp.conf pinning ntpsec to localhost — commenting it out"
        sed -i -E 's/^(\s*interface\s+(ignore\s+wildcard|listen\s+127\.0\.0\.1).*)/# \1  # commented out by deploy.sh so all networks can reach ntpsec/' "$NTP_CONF"
    fi
else
    warn "Expected ntpsec config at $NTP_CONF but it wasn't found — check the package installed correctly."
fi

if ! systemctl restart ntpsec; then
    warn "ntpsec failed to (re)start. Diagnose with:"
    warn "  sudo systemctl status ntpsec.service"
    warn "  sudo journalctl -xeu ntpsec.service --no-pager | tail -30"
    warn "Continuing with the rest of deploy.sh — fix ntpsec separately once you see the real error."
else
    systemctl enable ntpsec
fi

# NTP queries are UDP/123. Open it the same way we opened the MQTT port.
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
    log "ufw is active — opening port 123/udp for NTP"
    ufw allow 123/udp comment "NTP (ntpsec)"
else
    log "ufw not active/installed — skipping firewall rule (nothing to open at the OS level)"
fi
warn "If this server sits behind a cloud provider (AWS/GCP/Azure/etc.), also open port 123/udp (inbound AND outbound) in its security group / firewall rules — ufw alone won't cover that."

# ----------------------------------------------------------------------
# 2. Postgres role (apps create their own DBs/tables on first run — this
#    role just needs to exist with CREATEDB privilege). SYSTEM_DB_USER is
#    the single shared credential used for BOTH the AQ and Seismic
#    databases (and any others under this deployment) — there is
#    intentionally no separate per-service role.
# ----------------------------------------------------------------------
create_role() {
    local role="$1" pass="$2"
    log "Ensuring Postgres role '${role}' exists with CREATEDB"
    sudo -u postgres psql -v ON_ERROR_STOP=1 -q <<SQL
DO \$\$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = '${role}') THEN
      CREATE ROLE ${role} LOGIN PASSWORD '${pass}' CREATEDB;
   ELSE
      ALTER ROLE ${role} WITH PASSWORD '${pass}' CREATEDB;
   END IF;
END
\$\$;
SQL
}

if [[ "$DB_IS_LOCAL" == true ]]; then
    create_role "${SYSTEM_DB_USER}" "${SYSTEM_DB_PASSWORD}"

    # Allow password auth for these roles over TCP (local dev-friendly default;
    # tighten this to specific hosts / scram-sha-256 for production).
    PG_HBA="/etc/postgresql/${PG_VERSION}/main/pg_hba.conf"
    if [[ -f "$PG_HBA" ]] && ! grep -q "# added by deploy.sh" "$PG_HBA"; then
        log "Adding password-auth rule to pg_hba.conf"
        {
            echo "# added by deploy.sh"
            echo "host    all             all             127.0.0.1/32            scram-sha-256"
        } >> "$PG_HBA"
        systemctl restart postgresql
    fi
else
    warn "SYSTEM_DB_HOST=${SYSTEM_DB_HOST} is remote — skipping role creation here. Create the role"
    warn "'${SYSTEM_DB_USER}' on that server yourself (LOGIN PASSWORD '<matches .env>' CREATEDB) and make"
    warn "sure it accepts password auth from this host's IP in its pg_hba.conf."
fi

# ----------------------------------------------------------------------
# 2b. Raspberry Pi UART enablement (for the SIM800L SMS ingestion channel)
# ----------------------------------------------------------------------
# By default, Raspberry Pi OS uses the primary UART as a serial login
# console — which fights over the same pins/device with anything else
# (like a SIM800L) trying to send it AT commands. raspi-config's
# non-interactive mode disables the console and enables the UART hardware.
# This only applies on an actual Pi (raspi-config doesn't exist on Ubuntu).
#
# This project wires the SIM800L to the PRIMARY UART — GPIO14/TXD (pin 8)
# and GPIO15/RXD (pin 10) — not one of the Pi 4's secondary UARTs (uart2-5,
# on GPIO0/1, 4/5, 8/9, 12/13). On boards with onboard Bluetooth (Pi 3/4/5,
# Zero W/2 W), GPIO14/15 default to the "mini-UART", whose baud clock is
# tied to the CPU's variable core frequency — this causes baud-rate drift
# and garbled AT command responses under load. So below we also disable
# Bluetooth's claim on the UART, which frees the full, stable PL011 UART
# for GPIO14/15 instead (matches sim800l.py's docstring wiring notes).
set_env_var() {
    # Sets KEY=VALUE in $ENV_FILE — replaces an existing uncommented
    # 'KEY=...' line if present, appends a new 'KEY=VALUE' line otherwise.
    local key="$1" value="$2"
    local escaped_value="${value//&/\\&}"   # '&' is special to sed's replacement text
    if grep -qE "^${key}=" "$ENV_FILE"; then
        sed -i -E "s|^${key}=.*|${key}=${escaped_value}|" "$ENV_FILE"
    else
        printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
    fi
}

if command -v raspi-config >/dev/null 2>&1; then
    log "Raspberry Pi detected — configuring UART for SIM800L SMS ingestion (GPIO14/GPIO15)"

    # Disable the serial console (frees the UART for our own use)...
    raspi-config nonint do_serial_cons 1 2>/dev/null || \
        raspi-config nonint do_serial 1 2>/dev/null || \
        warn "Could not disable the serial console automatically — if SMS ingestion doesn't work, run 'sudo raspi-config' -> Interface Options -> Serial Port -> 'login shell over serial: No'."
    # ...and enable the UART hardware itself.
    raspi-config nonint do_serial_hw 0 2>/dev/null || \
        warn "Could not enable UART hardware automatically — if SMS ingestion doesn't work, run 'sudo raspi-config' -> Interface Options -> Serial Port -> 'serial port hardware: Yes', then reboot."

    # Belt-and-suspenders: confirm the settings raspi-config should have
    # just made actually landed in config.txt (Bookworm+ moved this under
    # /boot/firmware/), and fix anything that's still off.
    BOOT_CONFIG=""
    if [[ -f /boot/firmware/config.txt ]]; then
        BOOT_CONFIG="/boot/firmware/config.txt"
    elif [[ -f /boot/config.txt ]]; then
        BOOT_CONFIG="/boot/config.txt"
    fi

    if [[ -n "$BOOT_CONFIG" ]]; then
        if ! grep -qE '^\s*enable_uart=1\s*$' "$BOOT_CONFIG"; then
            log "Adding 'enable_uart=1' to $BOOT_CONFIG"
            echo "enable_uart=1" >> "$BOOT_CONFIG"
        else
            log "UART already enabled in $BOOT_CONFIG (enable_uart=1 present)"
        fi

        if ! grep -qE '^\s*dtoverlay=disable-bt\s*$' "$BOOT_CONFIG"; then
            log "Adding 'dtoverlay=disable-bt' to $BOOT_CONFIG — frees the full PL011 UART for GPIO14/15"
            echo "dtoverlay=disable-bt" >> "$BOOT_CONFIG"
        else
            log "Bluetooth already disabled on the UART in $BOOT_CONFIG (dtoverlay=disable-bt present)"
        fi
        systemctl disable hciuart 2>/dev/null || true

        # Flag+neutralize a leftover secondary UART overlay (uart2-5) — it
        # doesn't conflict with GPIO14/15 itself, but it needlessly reserves
        # other GPIO pins (0/1, 4/5, 8/9, or 12/13) this project doesn't use.
        if grep -qE '^\s*dtoverlay=uart[2-5]\s*$' "$BOOT_CONFIG"; then
            warn "Found a secondary UART overlay (dtoverlay=uart2/3/4/5) in $BOOT_CONFIG — commenting it out, since this project only uses the primary UART on GPIO14/15."
            sed -i -E 's/^(\s*dtoverlay=uart[2-5]\s*)$/# \1  # commented out by deploy.sh -- SIM800L uses the primary UART on GPIO14\/15/' "$BOOT_CONFIG"
        fi
    else
        warn "Could not find config.txt (checked /boot/firmware/config.txt and /boot/config.txt) — could not verify enable_uart/disable-bt. Set these manually if SMS ingestion doesn't work."
    fi

    warn "UART settings only take effect after a reboot. Run 'sudo reboot' once deploy.sh finishes, before starting the seismic service."

    # Keep .env in sync with the wiring above. GPIO14/15 always surfaces as
    # /dev/serial0 on Raspberry Pi OS (a symlink to ttyAMA0 or ttyS0
    # depending on the Bluetooth setting above) — so /dev/serial0 is always
    # the right value here, regardless of which underlying device it
    # resolves to, and regardless of whatever port an earlier experiment
    # (e.g. a secondary UART overlay) may have left behind in .env.
    CURRENT_SIM800_PORT="$(grep -E '^SIM800_SERIAL_PORT=' "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- || true)"
    if [[ "$CURRENT_SIM800_PORT" != "/dev/serial0" ]]; then
        log "Setting SIM800_SERIAL_PORT=/dev/serial0 in .env (was: '${CURRENT_SIM800_PORT:-<unset>}')"
        set_env_var "SIM800_SERIAL_PORT" "/dev/serial0"
    fi

    # seismic_mqtt.py reads SIM800_BAUDRATE from .env (defaults to 9600 if
    # absent, matching the SIM800L's factory default baud). Make the value
    # explicit in .env rather than relying on that silent default, and
    # reuse whatever value is actually there below instead of hardcoding.
    if ! grep -qE '^SIM800_BAUDRATE=' "$ENV_FILE"; then
        log "Adding SIM800_BAUDRATE=9600 to .env (not present — this is the SIM800L's factory default baud rate)"
        set_env_var "SIM800_BAUDRATE" "9600"
    fi
    SIM800_BAUDRATE="$(grep -E '^SIM800_BAUDRATE=' "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- || true)"
    log "After reboot, verify the modem responds with: minicom -D /dev/serial0 -b ${SIM800_BAUDRATE:-9600}"
else
    log "raspi-config not found — skipping UART enablement (not a Raspberry Pi, or SIM800L not in use)."
fi

# ----------------------------------------------------------------------
# 3. Python dependencies (installed system-wide, no venv)
# ----------------------------------------------------------------------
log "Installing Python dependencies system-wide from requirements.txt"
if [[ "$ON_RASPBERRY_PI" == true ]]; then
    log "Raspberry Pi OS detected — pip should already be pointed at piwheels.org (prebuilt ARM wheels) via"
    log "/etc/pip.conf on the standard Raspberry Pi OS image, so this should be quick. If a package has no"
    log "piwheels build yet, pip falls back to compiling from source using the build-essential/libpq-dev/"
    log "python3-dev packages installed above — that step is much slower on a Pi, but it will work."
fi
# --break-system-packages is required on Ubuntu 23.04+ / Debian 12+ (PEP 668)
# since pip otherwise refuses to install into the system-managed Python
# environment. This is intentional here — the project deliberately runs
# without a venv.
#
# NOTE: we deliberately do NOT run `pip3 install --upgrade pip` here. On
# Debian/Ubuntu, pip itself is installed via apt (python3-pip), not pip.
# Asking pip to upgrade itself makes it try to uninstall the apt-installed
# copy first, which has no RECORD file (apt doesn't write one) — pip
# refuses with "uninstall-no-record-file" and the whole script aborts.
# The apt-provided pip is new enough to install our requirements as-is.
#
# --ignore-installed is needed for the same reason, one level down: several
# of our requirements.txt deps (e.g. fastapi -> starlette) overlap with
# packages Ubuntu also ships via apt (python3-starlette, python3-requests,
# python3-jinja2, etc.), which likewise have no RECORD file. Without this
# flag, pip tries to uninstall the apt-owned package before installing the
# version we asked for and hits the same "uninstall-no-record-file" error.
# --ignore-installed tells pip to install our versions fresh instead of
# trying to remove the apt ones first.
pip3 install -r "$REQ_FILE" -q --break-system-packages --ignore-installed

# ----------------------------------------------------------------------
# 3b. Nginx + PHP-FPM + Laravel Dashboard (EMS/Dashboard)
#     Nginx becomes the single public entry point on :80. It serves the
#     Laravel GUI directly and reverse-proxies /api/* to api_server.py
#     (uvicorn), which is now bound to 127.0.0.1 only (see API_BIND_HOST
#     in api_server.py) — port 8000 is no longer exposed to the outside
#     world at all, nginx is the only thing that talks to it.
# ----------------------------------------------------------------------
if [[ ! -d "$LARAVEL_DIR" ]]; then
    warn "Laravel dashboard not found at ${LARAVEL_DIR} — skipping nginx/PHP/Laravel setup."
    warn "If it lives somewhere else, re-run as: LARAVEL_DIR=/path/to/Dashboard sudo -E ./deploy.sh"
else

log "Installing nginx, PHP-FPM, and Composer"
apt-get install -y --no-install-recommends \
    nginx \
    php-fpm php-cli php-pgsql php-mbstring php-xml php-curl php-zip php-bcmath php-gd \
    composer unzip

# Debian/Ubuntu package the FPM pool as php<major>.<minor>-fpm — detect the
# installed version rather than hardcoding it, since it differs between
# Ubuntu 22.04 (8.1) and 24.04 (8.3).
PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_FPM_SERVICE="php${PHP_VER}-fpm"
PHP_FPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"
if [[ ! -S "$PHP_FPM_SOCK" ]]; then
    # Fall back to whatever socket actually exists in case PHP_VER above
    # doesn't match the real package/service name for some reason.
    PHP_FPM_SOCK="$(find /run/php -maxdepth 1 -name '*-fpm.sock' 2>/dev/null | head -1)"
fi
systemctl enable --now "$PHP_FPM_SERVICE" 2>/dev/null || \
    warn "Could not enable/start ${PHP_FPM_SERVICE} — check: sudo systemctl status ${PHP_FPM_SERVICE}"

if php -r 'exit(version_compare(PHP_VERSION, "8.2.0", "<") ? 1 : 0);' 2>/dev/null; then
    :
else
    warn "PHP ${PHP_VER} detected. Laravel 10.x needs 8.1+, Laravel 11.x/12.x needs 8.2+."
    if [[ "$OS_ID" == "ubuntu" ]]; then
        warn "If 'composer install' below fails on a platform requirement, add a newer PHP via Ondrej Sury's Ubuntu PPA:"
        warn "  sudo apt-get install -y software-properties-common"
        warn "  sudo add-apt-repository -y ppa:ondrej/php && sudo apt-get update"
        warn "  sudo apt-get install -y php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd"
    else
        # add-apt-repository/PPAs are a Launchpad (Ubuntu-only) concept and
        # do not work on Debian or Raspberry Pi OS. deb.sury.org is the
        # same maintainer's equivalent repo for Debian-based systems.
        warn "If 'composer install' below fails on a platform requirement, add a newer PHP via the deb.sury.org repo"
        warn "(NOTE: 'ppa:ondrej/php' is Ubuntu-only and won't work here — this is the Debian/Raspberry Pi OS equivalent):"
        warn "  sudo apt-get install -y apt-transport-https lsb-release ca-certificates"
        warn "  sudo install -d -m 0755 /etc/apt/keyrings"
        warn "  sudo curl -fsSL https://packages.sury.org/php/apt.gpg -o /etc/apt/keyrings/deb.sury.org.gpg"
        warn "  echo \"deb [signed-by=/etc/apt/keyrings/deb.sury.org.gpg] https://packages.sury.org/php/ \$(lsb_release -sc) main\" | sudo tee /etc/apt/sources.list.d/php.list"
        warn "  sudo apt-get update && sudo apt-get install -y php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd"
    fi
fi

# Node/npm — only needed if the app has a frontend build step (Vite, etc.)
if [[ -f "${LARAVEL_DIR}/package.json" ]] && ! command -v npm >/dev/null 2>&1; then
    log "Installing Node.js/npm (package.json present in Dashboard/)"
    apt-get install -y --no-install-recommends nodejs npm
fi

if [[ -f "${LARAVEL_DIR}/package.json" ]] && command -v node >/dev/null 2>&1; then
    # Ubuntu 22.04's apt-packaged Node.js (v12) is far too old for any
    # current Vite-based Laravel build; Debian 12/bookworm-based Raspberry
    # Pi OS ships v18 which is usually fine, but check generically either way.
    NODE_MAJOR_INSTALLED="$(node -v | sed -E 's/^v([0-9]+).*/\1/')"
    if [[ "${NODE_MAJOR_INSTALLED:-0}" -lt 18 ]]; then
        warn "Node.js v${NODE_MAJOR_INSTALLED} detected from apt — too old for most current Vite-based frontends (need 18+)."
        warn "NodeSource's setup script works identically on Ubuntu and Debian/Raspberry Pi OS (amd64/arm64/armhf all supported):"
        warn "  curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && apt-get install -y nodejs"
        warn "  then re-run this script, or manually: cd ${LARAVEL_DIR} && npm ci && npm run build"
    fi
fi

# ---- Dedicated Postgres database + role for Laravel ----
# Deliberately separate from SYSTEM_DB_USER (used by the Python services):
# its own database, its own login, so the dashboard only ever has access
# to its own data, not the AQ/seismic/log databases.
DASHBOARD_DB_NAME="${DASHBOARD_DB_NAME:-ems_dashboard}"
DASHBOARD_DB_USER="${DASHBOARD_DB_USER:-ems_dashboard_user}"

if [[ "$DB_IS_LOCAL" == true ]]; then
    if [[ -f "${LARAVEL_DIR}/.env" ]] && grep -qE '^DB_PASSWORD=.+' "${LARAVEL_DIR}/.env"; then
        # Reuse whatever password is already sitting in Dashboard/.env from
        # a previous run, instead of generating (and orphaning) a new one.
        DASHBOARD_DB_PASSWORD="$(grep -E '^DB_PASSWORD=' "${LARAVEL_DIR}/.env" | tail -1 | cut -d= -f2-)"
    else
        DASHBOARD_DB_PASSWORD="$(openssl rand -hex 24)"
    fi

    log "Ensuring Postgres role '${DASHBOARD_DB_USER}' exists"
    sudo -u postgres psql -v ON_ERROR_STOP=1 -q <<SQL
DO \$\$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = '${DASHBOARD_DB_USER}') THEN
      CREATE ROLE ${DASHBOARD_DB_USER} LOGIN PASSWORD '${DASHBOARD_DB_PASSWORD}';
   ELSE
      ALTER ROLE ${DASHBOARD_DB_USER} WITH PASSWORD '${DASHBOARD_DB_PASSWORD}';
   END IF;
END
\$\$;
SQL

    log "Ensuring Postgres database '${DASHBOARD_DB_NAME}' exists (owned by ${DASHBOARD_DB_USER})"
    if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname = '${DASHBOARD_DB_NAME}'" | grep -q 1; then
        sudo -u postgres psql -v ON_ERROR_STOP=1 -q -c "CREATE DATABASE ${DASHBOARD_DB_NAME} OWNER ${DASHBOARD_DB_USER};"
    else
        log "Database '${DASHBOARD_DB_NAME}' already exists, skipping creation"
    fi
else
    warn "SYSTEM_DB_HOST is remote — skipping local Postgres role/DB creation for Laravel."
    warn "Create database '${DASHBOARD_DB_NAME}' + role '${DASHBOARD_DB_USER}' on that server yourself,"
    warn "then set DB_* in ${LARAVEL_DIR}/.env to match before running migrations."
    DASHBOARD_DB_PASSWORD="${DASHBOARD_DB_PASSWORD:-CHANGE_ME}"
fi

# ---- Laravel .env ----
SERVER_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
if [[ ! -f "${LARAVEL_DIR}/.env" ]]; then
    if [[ -f "${LARAVEL_DIR}/.env.example" ]]; then
        cp "${LARAVEL_DIR}/.env.example" "${LARAVEL_DIR}/.env"
    else
        touch "${LARAVEL_DIR}/.env"
    fi
    log "Writing database + app config to ${LARAVEL_DIR}/.env"
    for pair in \
        "APP_ENV=production" \
        "APP_DEBUG=false" \
        "APP_URL=http://${SERVER_IP:-localhost}" \
        "DB_CONNECTION=pgsql" \
        "DB_HOST=${SYSTEM_DB_HOST:-127.0.0.1}" \
        "DB_PORT=${SYSTEM_DB_PORT:-5432}" \
        "DB_DATABASE=${DASHBOARD_DB_NAME}" \
        "DB_USERNAME=${DASHBOARD_DB_USER}" \
        "DB_PASSWORD=${DASHBOARD_DB_PASSWORD}"; do
        key="${pair%%=*}"; value="${pair#*=}"
        escaped_value="${value//&/\\&}"
        if grep -qE "^${key}=" "${LARAVEL_DIR}/.env"; then
            sed -i -E "s|^${key}=.*|${key}=${escaped_value}|" "${LARAVEL_DIR}/.env"
        else
            printf '%s=%s\n' "$key" "$value" >> "${LARAVEL_DIR}/.env"
        fi
    done
    chmod 600 "${LARAVEL_DIR}/.env"
else
    log "${LARAVEL_DIR}/.env already exists — leaving it as-is (delete it and re-run to regenerate)."
fi

# ---- Composer / artisan / npm build ----
log "Running composer install"
( cd "$LARAVEL_DIR" && composer install --no-dev --optimize-autoloader --no-interaction ) || \
    die "composer install failed — see the error above (often a PHP version/extension mismatch, see the PHP version warning above if shown)."

if ! grep -qE '^APP_KEY=base64:' "${LARAVEL_DIR}/.env" 2>/dev/null; then
    log "Generating Laravel APP_KEY"
    ( cd "$LARAVEL_DIR" && php artisan key:generate --force )
fi

log "Running Laravel migrations"
( cd "$LARAVEL_DIR" && php artisan migrate --force ) || \
    warn "Migrations failed — check ${LARAVEL_DIR}/.env DB_* values, then re-run: cd ${LARAVEL_DIR} && php artisan migrate --force"

( cd "$LARAVEL_DIR" && php artisan storage:link ) 2>/dev/null || true

if [[ -f "${LARAVEL_DIR}/package.json" ]] && command -v npm >/dev/null 2>&1; then
    log "Installing frontend deps and building assets (npm)"
    ( cd "$LARAVEL_DIR" && npm ci && npm run build ) || \
        warn "npm build failed — check ${LARAVEL_DIR}/package.json and your Node version (node -v)."
fi

log "Caching Laravel config/routes/views for production"
( cd "$LARAVEL_DIR" && php artisan config:cache && php artisan route:cache && php artisan view:cache ) || \
    warn "artisan cache commands failed — non-fatal, the app will still run uncached."

# ---- Ownership / permissions ----
# nginx + php-fpm run as www-data on Debian/Ubuntu. storage/ and
# bootstrap/cache/ must be writable by that user; the rest just needs to
# be readable by it.
chown -R www-data:www-data "$LARAVEL_DIR"
chmod -R 775 "${LARAVEL_DIR}/storage" "${LARAVEL_DIR}/bootstrap/cache" 2>/dev/null || true

# nginx/php-fpm run as www-data, which needs execute ("traversal") permission
# on EVERY parent directory of LARAVEL_DIR to reach public/index.php at all —
# not just LARAVEL_DIR itself. This is easy to miss when Dashboard/ lives
# under a user's home directory, since home dirs are commonly created
# `700`/`750` (owner-only), which silently blocks www-data before nginx even
# gets to check the file — surfacing as a plain 404 with no obvious cause.
# We only ADD the "execute" (traversal) bit for "other" where it's missing;
# we never touch read/write bits or ownership on these directories, so this
# doesn't expose file contents or change who owns anything outside LARAVEL_DIR.
log "Checking that www-data can traverse into ${LARAVEL_DIR} (parent directory permissions)"
_check_dir="$(dirname "$LARAVEL_DIR")"
while [[ "$_check_dir" != "/" && -n "$_check_dir" ]]; do
    _perms="$(stat -c '%A' "$_check_dir" 2>/dev/null || true)"
    # 10-char perms string, e.g. drwxr-x---: char 10 is "other execute".
    if [[ -n "$_perms" && "${_perms:9:1}" != "x" ]]; then
        warn "${_check_dir} lacks traversal (execute) permission for other users —"
        warn "this would block www-data from reaching ${LARAVEL_DIR}/public/index.php,"
        warn "causing a plain 404 with no obvious error. Adding execute-only (o+x);"
        warn "this does NOT grant read access to files inside ${_check_dir} itself."
        chmod o+x "$_check_dir" || warn "Could not chmod ${_check_dir} — fix manually: sudo chmod o+x ${_check_dir}"
    fi
    _check_dir="$(dirname "$_check_dir")"
done

# ---- Nginx site ----
log "Writing nginx site config"
cat > /etc/nginx/sites-available/ems-dashboard <<NGINXEOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    root ${LARAVEL_DIR}/public;
    index index.php;

    client_max_body_size 20m;

    access_log /var/log/nginx/ems-dashboard.access.log;
    error_log  /var/log/nginx/ems-dashboard.error.log;

    # Python API (api_server.py / uvicorn, bound to 127.0.0.1:${API_PORT:-8000}
    # only — see API_BIND_HOST in api_server.py). Every route in api_server.py
    # already starts with /api/, so this forwards the path through unchanged.
    location /api/ {
        proxy_pass http://127.0.0.1:${API_PORT:-8000};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINXEOF

ln -sf /etc/nginx/sites-available/ems-dashboard /etc/nginx/sites-enabled/ems-dashboard
rm -f /etc/nginx/sites-enabled/default

if ! nginx -t; then
    die "nginx config test failed — see the error above. Fix /etc/nginx/sites-available/ems-dashboard, then: sudo systemctl reload nginx"
fi
systemctl enable --now nginx
systemctl reload nginx

# Open 80/443 now (443 pre-opened for whenever you point a domain at this
# box and add TLS via certbot — see the note printed at the end of this
# script).
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
    log "ufw is active — opening ports 80/tcp and 443/tcp"
    ufw allow 80/tcp comment "nginx HTTP"
    ufw allow 443/tcp comment "nginx HTTPS (future)"
    if ufw status | grep -qE '^8000\b'; then
        log "Removing old direct-access ufw rule for 8000/tcp — the API is now only reachable through nginx"
        ufw delete allow 8000/tcp 2>/dev/null || true
    fi
else
    log "ufw not active/installed — skipping firewall rule"
fi
warn "If this server sits behind a cloud provider (AWS/GCP/Azure/etc.), also CLOSE port 8000 in its"
warn "security group / firewall rules now (it should only be reachable at 127.0.0.1, via nginx, from now on)"
warn "and make sure 80/tcp (and 443/tcp once you add a domain) is open there."

log "Laravel dashboard: http://${SERVER_IP:-<this-server-ip>}/"
log "API via nginx:      http://${SERVER_IP:-<this-server-ip>}/api/..."
warn "Dashboard DB credentials are in ${LARAVEL_DIR}/.env (DB_DATABASE=${DASHBOARD_DB_NAME}, DB_USERNAME=${DASHBOARD_DB_USER}) — back that file up, it's not stored anywhere else."

fi  # LARAVEL_DIR exists

# ----------------------------------------------------------------------
# 4. Wrap up
# ----------------------------------------------------------------------
chmod 600 "$ENV_FILE" || warn "Could not chmod .env — check permissions manually"

[[ "$DB_IS_LOCAL" == true ]] || warn "Reminder: DB is remote (${SYSTEM_DB_HOST}) — confirm the role/DB/TimescaleDB extension are already set up there."
[[ "$MQTT_IS_LOCAL" == true ]] || warn "Reminder: MQTT broker is remote (${MQTT_BROKER_HOST}) — confirm the '${MQTT_USER}' account is already set up there."

log "Done."
cat <<EOF

Next steps:
  1. Review stations.json for correct station config. air_quality_ingest.py
     imports it into the database automatically on its first run (only if
     the 'stations' table is empty). To (re-)apply stations.json later —
     e.g. after editing it, or on an already-running deployment — run:
       python3 ${SCRIPT_DIR}/import_stations.py
  2. Run each service directly with system python3, e.g.:
       python3 ${SCRIPT_DIR}/air_quality_ingest.py
       python3 ${SCRIPT_DIR}/seismic_mqtt.py
       python3 ${SCRIPT_DIR}/api_server.py
     api_server.py now binds to 127.0.0.1:${API_PORT:-8000} by default (not
     0.0.0.0) since nginx is the public entry point — set API_BIND_HOST in
     .env if something needs to reach it directly without going through nginx.
  3. For always-on deployment, run: sudo ./install_services.sh
     (installs+starts the ems.target systemd unit for all three services).
$([[ -d "$LARAVEL_DIR" ]] && cat <<DASHEOF
  4. Dashboard is live at http://${SERVER_IP:-<this-server-ip>}/ (nginx +
     PHP-FPM), with the API reachable at the same host under /api/*.
     No domain yet, so this is HTTP only. Once you point a domain at this
     server, switch to HTTPS with:
       sudo apt-get install certbot python3-certbot-nginx
       sudo certbot --nginx -d your-domain.example
     (certbot edits the ems-dashboard nginx site in place to add TLS).
DASHEOF
)

EOF