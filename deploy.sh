#!/usr/bin/env bash
#
# deploy.sh — sets up everything needed to run air_quality_ingest.py,
# seismic_mqtt.py, and api_server.py on a fresh Ubuntu server.
#
# Installs / configures:
#   1. System packages (Python, PostgreSQL, TimescaleDB, Mosquitto MQTT broker)
#   2. A Python virtual environment with requirements.txt
#   3. Postgres roles + CREATEDB privilege for AQ_DB_USER / SEISMIC_DB_USER
#      (the apps create their own databases/tables on first run)
#   4. Mosquitto authentication (password file for MQTT_USER)
#
# ASSUMPTIONS (adjust the variables below if these don't match your box):
#   - Ubuntu 22.04 or 24.04 LTS
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
apt-get install -y \
    python3 python3-pip \
    curl gnupg lsb-release ca-certificates apt-transport-https wget

# ---- PostgreSQL + TimescaleDB -----------------------------------------
if ! command -v psql >/dev/null 2>&1; then
    log "Installing PostgreSQL ${PG_VERSION} via the official PGDG repo"
    apt-get install -y postgresql-common
    /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
    apt-get install -y "postgresql-${PG_VERSION}"
else
    log "PostgreSQL already installed, skipping"
fi

if ! dpkg -l | grep -q "timescaledb-2-postgresql-${PG_VERSION}"; then
    log "Installing TimescaleDB extension for PostgreSQL ${PG_VERSION}"
    echo "deb https://packagecloud.io/timescale/timescaledb/ubuntu/ $(lsb_release -c -s) main" \
        > /etc/apt/sources.list.d/timescaledb.list
    wget --quiet -O - https://packagecloud.io/timescale/timescaledb/gpgkey | apt-key add -
    apt-get update -y
    apt-get install -y "timescaledb-2-postgresql-${PG_VERSION}"
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
    apt-get install -y mosquitto mosquitto-clients
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
# 3. Python dependencies (installed system-wide, no venv)
# ----------------------------------------------------------------------
log "Installing Python dependencies system-wide from requirements.txt"
# --break-system-packages is required on Ubuntu 23.04+ (PEP 668) since pip
# otherwise refuses to install into the system-managed Python environment.
# This is intentional here — the project deliberately runs without a venv.
#
# NOTE: we deliberately do NOT run `pip3 install --upgrade pip` here. On
# Debian/Ubuntu, pip itself is installed via apt (python3-pip), not pip.
# Asking pip to upgrade itself makes it try to uninstall the apt-installed
# copy first, which has no RECORD file (apt doesn't write one) — pip
# refuses with "uninstall-no-record-file" and the whole script aborts.
# The apt-provided pip is new enough to install our requirements as-is.
pip3 install -r "$REQ_FILE" -q --break-system-packages

# ----------------------------------------------------------------------
# 4. Wrap up
# ----------------------------------------------------------------------
chmod 600 "$ENV_FILE" || warn "Could not chmod .env — check permissions manually"

log "Done."
cat <<EOF

Next steps:
  1. Review aq_stations.json for correct station config.
  2. Run each service directly with system python3, e.g.:
       python3 ${SCRIPT_DIR}/air_quality_ingest.py
       python3 ${SCRIPT_DIR}/seismic_mqtt.py
       python3 ${SCRIPT_DIR}/api_server.py
  3. For always-on deployment, wrap each in a systemd service
     (ask if you'd like these generated).

EOF