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
#   3. Postgres roles + CREATEDB privilege for AQ_DB_USER / SEISMIC_DB_USER
#      (the apps create their own databases/tables on first run)
#   4. Mosquitto authentication (password file for MQTT_USER)
#
# ASSUMPTIONS (adjust the variables below if these don't match your box):
#   - Ubuntu 22.04/24.04 LTS, OR Raspberry Pi OS (Debian bookworm or newer),
#     64-bit (arm64) STRONGLY recommended on a Pi — see detect_platform().
#   - PostgreSQL 16 (auto-detected where possible, override with PG_VERSION)
#   - This script, .env, requirements.txt, and the three *.py files all
#     live in the same directory
#   - You run this with sudo: `sudo ./deploy.sh`
#
# This script is idempotent where practical — safe to re-run.

set -euo pipefail

# ----------------------------------------------------------------------
# 0. Preflight
# ----------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env"
REQ_FILE="${SCRIPT_DIR}/requirements.txt"
PG_VERSION="${PG_VERSION:-16}"   # override: PG_VERSION=15 sudo -E ./deploy.sh

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

[[ -f "$ENV_FILE" ]] || die ".env not found at $ENV_FILE — copy it here first."
[[ -f "$REQ_FILE" ]] || die "requirements.txt not found at $REQ_FILE."

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

for v in AQ_DB_USER AQ_DB_PASSWORD AQ_DB_NAME SEISMIC_DB_USER SEISMIC_DB_PASSWORD SEISMIC_DB_NAME MQTT_USER MQTT_PASSWORD; do
    [[ -n "${!v:-}" ]] || die "Missing required variable '$v' in .env"
done

# ----------------------------------------------------------------------
# 1. System packages
# ----------------------------------------------------------------------
log "Updating apt package lists"
apt-get update -y

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
    echo "deb https://packagecloud.io/timescale/timescaledb/${TIMESCALE_REPO_OS}/ $(lsb_release -c -s) main" \
        > /etc/apt/sources.list.d/timescaledb.list
    wget --quiet -O - https://packagecloud.io/timescale/timescaledb/gpgkey | apt-key add -
    apt-get update -y
    if ! apt-get install -y --no-install-recommends "timescaledb-2-postgresql-${PG_VERSION}"; then
        die "TimescaleDB package install failed. If you're on Raspberry Pi OS, confirm you're running" \
            "the 64-bit (arm64) image and that '$(lsb_release -c -s)' is a codename TimescaleDB has" \
            "published packages for yet — check https://docs.timescale.com/self-hosted/latest/install/installation-debian/"
    fi
    # timescaledb-tune sizes shared_buffers/work_mem/etc. from detected system
    # RAM, which works in the Pi's favor automatically (a 1-2GB Pi gets much
    # smaller settings than a 16GB server) — no separate low-memory branch needed.
    timescaledb-tune --quiet --yes --pg-config="/usr/lib/postgresql/${PG_VERSION}/bin/pg_config" || \
        warn "timescaledb-tune failed/skipped — check config manually if needed"
else
    log "TimescaleDB already installed, skipping"
fi

log "Restarting PostgreSQL"
systemctl restart postgresql
systemctl enable postgresql

# ---- Mosquitto MQTT broker --------------------------------------------
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
# 2. Postgres roles (apps create their own DBs/tables on first run —
#    these roles just need to exist with CREATEDB privilege)
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

create_role "${AQ_DB_USER}" "${AQ_DB_PASSWORD}"
create_role "${SEISMIC_DB_USER}" "${SEISMIC_DB_PASSWORD}"

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

# ----------------------------------------------------------------------
# 2b. Raspberry Pi UART enablement (for the SIM800L SMS ingestion channel)
# ----------------------------------------------------------------------
# By default, Raspberry Pi OS uses the primary UART as a serial login
# console — which fights over the same pins/device with anything else
# (like a SIM800L) trying to send it AT commands. raspi-config's
# non-interactive mode disables the console and enables the UART hardware.
# This only applies on an actual Pi (raspi-config doesn't exist on Ubuntu).
if command -v raspi-config >/dev/null 2>&1; then
    log "Raspberry Pi detected — configuring UART for SIM800L SMS ingestion"
    # Disable the serial console (frees the UART for our own use)...
    raspi-config nonint do_serial_cons 1 2>/dev/null || \
        raspi-config nonint do_serial 1 2>/dev/null || \
        warn "Could not disable the serial console automatically — if SMS ingestion doesn't work, run 'sudo raspi-config' -> Interface Options -> Serial Port -> 'login shell over serial: No'."
    # ...and enable the UART hardware itself.
    raspi-config nonint do_serial_hw 0 2>/dev/null || \
        warn "Could not enable UART hardware automatically — if SMS ingestion doesn't work, run 'sudo raspi-config' -> Interface Options -> Serial Port -> 'serial port hardware: Yes', then reboot."
    warn "UART settings only take effect after a reboot. Run 'sudo reboot' once deploy.sh finishes, before starting the seismic service."
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
# 4. Wrap up
# ----------------------------------------------------------------------
chmod 600 "$ENV_FILE" || warn "Could not chmod .env — check permissions manually"

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
  3. For always-on deployment, run: sudo ./install_services.sh
     (installs+starts the ems.target systemd unit for all three services).

EOF