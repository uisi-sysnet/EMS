#!/usr/bin/env bash
#
# install.sh — interactive setup wizard for the IoT gateway (Raspberry Pi 4B,
# Raspberry Pi OS Lite / Bookworm, or any Debian/Ubuntu box).
#
# One command does the whole setup:
#   1. Installs the few packages this wizard itself needs (NetworkManager on
#      a Pi, for the network steps below).
#   2. Network mode (WiFi AP / eth0 DHCP or static) — Raspberry Pi OS only.
#   3. Writes scripts/.env (created from scripts/.env.EMS.scripts on a fresh
#      checkout) with every credential the stack needs: database host/port/
#      user/password, database names, the 'postgres' superuser password used
#      by the Dashboard, MQTT host/port/user/password, and the API key.
#      Blank answers generate a strong random secret; template placeholders
#      ("change_me") are never accepted.
#   4. Runs deploy.sh, which installs every required package (PostgreSQL +
#      TimescaleDB, Mosquitto, Python deps, nginx, PHP, Composer, Node) and
#      applies the credentials above (roles, databases, MQTT accounts,
#      Dashboard .env).
#   5. Installs the systemd services and runs check_requirements.sh.
#
# Safe to re-run: existing values are offered as defaults (press Enter to keep).
#
# Usage: sudo ./install.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/scripts/.env"
ENV_TEMPLATE="${SCRIPT_DIR}/scripts/.env.EMS.scripts"
LARAVEL_ENV_FILE="${SCRIPT_DIR}/Dashboard/.env"
DEPLOY_SCRIPT="${SCRIPT_DIR}/deploy.sh"
INSTALL_SERVICES_SCRIPT="${SCRIPT_DIR}/install_services.sh"
CHECK_REQUIREMENTS_SCRIPT="${SCRIPT_DIR}/check_requirements.sh"

# Values in the committed template that must never reach a real install.
PLACEHOLDER_VALUES=("change_me" "replace_with_token:Owner Label")

log()  { echo -e "\033[1;32m[setup]\033[0m $*"; }
warn() { echo -e "\033[1;33m[setup][WARN]\033[0m $*"; }
die()  { echo -e "\033[1;31m[setup][ERROR]\033[0m $*" >&2; exit 1; }

# ----------------------------------------------------------------------
# 0. Preflight
# ----------------------------------------------------------------------
if [[ $EUID -ne 0 ]]; then
    die "Run this with sudo: sudo ./install.sh"
fi

[[ -t 0 ]] || die "install.sh is interactive — run it from a terminal."

[[ -f "$DEPLOY_SCRIPT" ]] || die "deploy.sh not found next to this script (expected at $DEPLOY_SCRIPT)."
[[ -f "$INSTALL_SERVICES_SCRIPT" ]] || die "install_services.sh not found next to this script (expected at $INSTALL_SERVICES_SCRIPT)."
[[ -f "$CHECK_REQUIREMENTS_SCRIPT" ]] || die "check_requirements.sh not found next to this script (expected at $CHECK_REQUIREMENTS_SCRIPT)."

# Archives and Windows file copies can strip executable bits.
chmod +x "$DEPLOY_SCRIPT" "$INSTALL_SERVICES_SCRIPT" "$CHECK_REQUIREMENTS_SCRIPT" \
    || die "Could not make deployment scripts executable."

if [[ ! -f "$ENV_FILE" ]]; then
    [[ -f "$ENV_TEMPLATE" ]] || die "scripts/.env not found and no template at ${ENV_TEMPLATE} to create it from."
    log "No scripts/.env found — creating one from scripts/.env.EMS.scripts"
    cp "$ENV_TEMPLATE" "$ENV_FILE"
fi
# Windows-saved copies break the line-based edits below.
sed -i 's/\r$//' "$ENV_FILE"
chmod 600 "$ENV_FILE"

# ----------------------------------------------------------------------
# Helpers
# ----------------------------------------------------------------------
ask() {
    local prompt="$1" default="$2" __resultvar="$3" input
    read -r -p "${prompt} [${default}]: " input || true
    input="${input:-$default}"
    printf -v "$__resultvar" '%s' "$input"
}

ask_yesno() {
    local prompt="$1" default="$2" __resultvar="$3" input
    read -r -p "${prompt} (y/n) [${default}]: " input || true
    input="${input:-$default}"
    case "$input" in
        y|Y|yes|Yes|YES) printf -v "$__resultvar" 'yes' ;;
        *)               printf -v "$__resultvar" 'no' ;;
    esac
}

# Random alphanumeric string. Reads a fixed number of bytes per round so no
# pipe stage is cut off early (SIGPIPE would abort the script under pipefail).
gen_secret() {
    local length="${1:-24}" out=""
    while (( ${#out} < length )); do
        out+="$(head -c 64 /dev/urandom | LC_ALL=C tr -dc 'A-Za-z0-9')"
    done
    printf '%s' "${out:0:length}"
}

is_placeholder() {
    local value="$1" p
    [[ -z "$value" ]] && return 0
    for p in "${PLACEHOLDER_VALUES[@]}"; do
        [[ "$value" == "$p" ]] && return 0
    done
    return 1
}

# Passwords end up inside SQL string literals, .env files read by bash,
# python-dotenv and Laravel, and mosquitto_passwd — so quotes, backslashes,
# '$', backticks, '#' and whitespace are rejected rather than escaped.
is_safe_secret() {
    [[ "$1" =~ ^[A-Za-z0-9@%+=:,./_~^!*?-]{8,128}$ ]]
}

# Role/user and database names are used as SQL identifiers.
is_safe_identifier() {
    [[ "$1" =~ ^[A-Za-z_][A-Za-z0-9_]{0,62}$ ]]
}

is_valid_port() {
    [[ "$1" =~ ^[0-9]{1,5}$ ]] && (( $1 >= 1 && $1 <= 65535 ))
}

# Validates a dotted IPv4 address.
is_valid_ipv4() {
    local ip="$1" o1 o2 o3 o4
    [[ "$ip" =~ ^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$ ]] || return 1
    IFS=. read -r o1 o2 o3 o4 <<< "$ip"

    for o in "$o1" "$o2" "$o3" "$o4"; do
        [[ "$o" -ge 0 && "$o" -le 255 ]] || return 1
    done
    return 0
}

# Hostname or IPv4.
is_valid_host() {
    is_valid_ipv4 "$1" || [[ "$1" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$ ]]
}

# Reads KEY from a .env file, stripping one layer of surrounding quotes.
get_env_var() {
    local key="$1" file="${2:-$ENV_FILE}" value
    [[ -f "$file" ]] || return 0
    value="$(grep -E "^${key}=" "$file" 2>/dev/null | tail -1 | cut -d= -f2- || true)"
    value="${value%$'\r'}"
    if [[ ${#value} -ge 2 && ( ( "$value" == \"*\" ) || ( "$value" == \'*\' ) ) ]]; then
        value="${value:1:-1}"
    fi
    printf '%s' "$value"
}

# Sets KEY=VALUE in scripts/.env. The value is passed through the
# environment to awk, so no character in it can break the edit.
set_env_var() {
    local key="$1" value="$2" tmp
    if [[ "$value" =~ [[:space:]] ]]; then
        value="\"${value}\""
    fi
    tmp="$(mktemp)"
    ENV_KEY="$key" ENV_VALUE="$value" awk '
        BEGIN { key = ENVIRON["ENV_KEY"]; val = ENVIRON["ENV_VALUE"]; done = 0 }
        index($0, key "=") == 1 { if (!done) { print key "=" val; done = 1 } ; next }
        { print }
        END { if (!done) print key "=" val }
    ' "$ENV_FILE" > "$tmp"
    cat "$tmp" > "$ENV_FILE"   # keep the original file's owner/permissions
    rm -f "$tmp"
}

# Asks for a value until the validator accepts it.
ask_validated() {
    local prompt="$1" default="$2" __resultvar="$3" validator="$4" error="$5" __answer
    while true; do
        ask "$prompt" "$default" __answer
        "$validator" "$__answer" && break
        warn "$error"
    done
    printf -v "$__resultvar" '%s' "$__answer"
}

# Asks for a secret (hidden, confirmed). Enter keeps the current value if it
# is a real one; otherwise Enter generates a new random secret.
GENERATED_SECRETS=()
ask_secret() {
    local label="$1" current="$2" __resultvar="$3" p1 p2 hint
    if is_placeholder "$current"; then
        hint="press Enter to generate one"
    else
        hint="press Enter to keep current"
    fi
    while true; do
        read -r -s -p "${label} [${hint}]: " p1 || true
        echo
        if [[ -z "$p1" ]]; then
            if is_placeholder "$current"; then
                p1="$(gen_secret 24)"
                GENERATED_SECRETS+=("${label}: ${p1}")
                log "Generated a random ${label,,}."
            else
                p1="$current"
            fi
            break
        fi
        if ! is_safe_secret "$p1"; then
            warn "Use 8-128 characters: letters, digits and @%+=:,./_~^!*?- only (no quotes, spaces, \\, \$, #, or backticks)."
            continue
        fi
        if is_placeholder "$p1"; then
            warn "That is the template placeholder — choose a real password."
            continue
        fi
        read -r -s -p "Confirm ${label}: " p2 || true
        echo
        [[ "$p1" == "$p2" ]] && break
        warn "Passwords didn't match — try again."
    done
    printf -v "$__resultvar" '%s' "$p1"
}

# ----------------------------------------------------------------------
# OS detection
# ----------------------------------------------------------------------
# Sets OS_PRETTY_NAME (human-readable string) and returns 0 if the host is
# running Raspberry Pi OS / Raspbian, 1 otherwise.
detect_os() {
    OS_PRETTY_NAME="Unknown"

    if [[ -f /etc/os-release ]]; then
        # shellcheck source=/dev/null
        source /etc/os-release
        OS_PRETTY_NAME="${PRETTY_NAME:-${NAME:-Unknown}}"

        # Raspberry Pi OS reports ID=raspbian (older) or ID=debian with
        # ID_LIKE containing "raspbian" / "debian raspbian" (newer, incl.
        # Bookworm). Check both, plus common vendor markers.
        if [[ "${ID:-}" == "raspbian" ]] || [[ "${ID_LIKE:-}" == *raspbian* ]]; then
            return 0
        fi
    fi

    # Fallback markers used on Raspberry Pi OS regardless of /etc/os-release.
    if [[ -f /etc/rpi-issue ]] || [[ -f /etc/rpi_repo_files ]] \
        || grep -qi "raspberry pi" /proc/cpuinfo 2>/dev/null; then
        return 0
    fi

    return 1
}

command -v apt-get >/dev/null 2>&1 || die "apt-get not found — this installer supports Debian, Ubuntu and Raspberry Pi OS only."

echo "=========================================="
echo " IoT Gateway Setup Wizard"
echo " Raspberry Pi 4B / Raspberry Pi OS Lite"
echo "=========================================="
echo

# ----------------------------------------------------------------------
# OS check
# ----------------------------------------------------------------------
if detect_os; then
    IS_RASPBIAN="yes"
    log "Detected OS: ${OS_PRETTY_NAME} (Raspberry Pi OS / Raspbian)"
else
    IS_RASPBIAN="no"
    warn "Detected OS: ${OS_PRETTY_NAME} — this does not look like Raspberry Pi OS / Raspbian."
    warn "Skipping WiFi Access Point and wired (eth0) network setup; continuing to .env configuration."
fi

if [[ "$IS_RASPBIAN" == "yes" ]]; then

if ! command -v nmcli >/dev/null 2>&1; then
    log "Installing NetworkManager (needed for the network setup below)"
    apt-get update -y && apt-get install -y --no-install-recommends network-manager \
        || die "Could not install NetworkManager — check the network connection and re-run."
    systemctl enable --now NetworkManager || warn "Could not start NetworkManager automatically."
fi

# ----------------------------------------------------------------------
# 1. Network mode
# ----------------------------------------------------------------------
echo
echo "--- Network mode ---"
echo "  1) Standalone — turns wlan0 into a WiFi Access Point."
echo "  2) Stay as-is — no WiFi/AP changes are made."
echo

read -r -p "Select [1/2] (default: 2): " NET_MODE_CHOICE || true
NET_MODE_CHOICE="${NET_MODE_CHOICE:-2}"

if [[ "$NET_MODE_CHOICE" == "1" ]]; then
    ip link show wlan0 >/dev/null 2>&1 \
        || die "No wlan0 interface found."

    echo
    ask "Access Point SSID" "IOT-Gateway" AP_SSID

    while true; do
        read -r -s -p "AP WiFi password (min 8 characters, blank = generate one): " AP_PASSWORD || true
        echo

        if [[ -z "$AP_PASSWORD" ]]; then
            AP_PASSWORD="$(gen_secret 12)"
            GENERATED_SECRETS+=("WiFi AP '${AP_SSID}' password: ${AP_PASSWORD}")
            log "Generated a random AP password."
            break
        elif [[ ${#AP_PASSWORD} -ge 8 && ${#AP_PASSWORD} -le 63 ]]; then
            break
        else
            warn "Password must be 8-63 characters — try again."
        fi
    done

    log "Enabling SSH"
    systemctl enable ssh --now 2>/dev/null \
        || warn "Could not enable SSH automatically."

    log "Creating WiFi Access Point profile '${AP_SSID}' on wlan0"
    nmcli connection delete "${AP_SSID}" >/dev/null 2>&1 || true

    nmcli connection add type wifi ifname wlan0 con-name "${AP_SSID}" \
        autoconnect yes ssid "${AP_SSID}" \
        802-11-wireless.mode ap \
        802-11-wireless.band bg \
        ipv4.method shared \
        wifi-sec.key-mgmt wpa-psk \
        wifi-sec.psk "${AP_PASSWORD}" \
        || die "Failed to create the AP connection profile."

    if nmcli connection up "${AP_SSID}"; then
        log "AP is up."
    else
        warn "AP profile created but could not bring it up now."
    fi
else
    log "Staying as-is — no WiFi/AP changes made."
fi

# ----------------------------------------------------------------------
# 2. IP configuration for ethernet (DHCP or Static)
# ----------------------------------------------------------------------
echo
echo "--- Wired (eth0) IP configuration ---"
echo "  1) DHCP — obtain an IP address automatically."
echo "  2) Static — manually set IP, gateway, and DNS."
echo "  3) Leave eth0 unchanged."
warn "Changing eth0 network settings while connected through eth0 will disconnect SSH."
echo

read -r -p "Select [1/2/3] (default: 3): " ETH_MODE_CHOICE || true
ETH_MODE_CHOICE="${ETH_MODE_CHOICE:-3}"

if [[ "$ETH_MODE_CHOICE" == "1" || "$ETH_MODE_CHOICE" == "2" ]]; then
    ETH_CON_NAME="$(nmcli -t -f NAME,DEVICE connection show 2>/dev/null \
        | awk -F: '$2=="eth0"{print $1; exit}')"

    if [[ -z "$ETH_CON_NAME" ]]; then
        ETH_CON_NAME="Wired connection eth0"
        log "Creating '${ETH_CON_NAME}'"
        nmcli connection add type ethernet ifname eth0 con-name "$ETH_CON_NAME" \
            || die "Could not create ethernet connection profile."
    fi
fi

if [[ "$ETH_MODE_CHOICE" == "2" ]]; then
    ask_validated "Static IP address for eth0" "192.168.1.10" ETH_IP is_valid_ipv4 \
        "That doesn't look like a valid IPv4 address — try again."

    while true; do
        ask "Subnet prefix length (e.g. 24)" "24" ETH_PREFIX
        [[ "$ETH_PREFIX" =~ ^[0-9]{1,2}$ ]] && (( ETH_PREFIX >= 1 && ETH_PREFIX <= 32 )) && break
        warn "Prefix must be a number from 1 to 32 — try again."
    done

    ask_validated "Gateway" "192.168.1.1" ETH_GATEWAY is_valid_ipv4 \
        "That doesn't look like a valid IPv4 address — try again."
    ask_validated "DNS server" "192.168.1.1" ETH_DNS is_valid_ipv4 \
        "That doesn't look like a valid IPv4 address — try again."

    ask_yesno "Apply this static config to eth0 now?" "y" CONFIRM_ETH

    if [[ "$CONFIRM_ETH" == "yes" ]]; then
        log "Setting eth0 to ${ETH_IP}/${ETH_PREFIX}, gateway ${ETH_GATEWAY}, DNS ${ETH_DNS}"

        nmcli connection modify "$ETH_CON_NAME" \
            ipv4.addresses "${ETH_IP}/${ETH_PREFIX}" \
            ipv4.gateway "${ETH_GATEWAY}" \
            ipv4.dns "${ETH_DNS}" \
            ipv4.method manual \
            || die "Failed to modify the eth0 connection profile."

        if nmcli connection up "$ETH_CON_NAME" 2>/dev/null; then
            log "eth0 is now static at ${ETH_IP}/${ETH_PREFIX}."
        else
            warn "Config saved but eth0 could not be brought up immediately."
        fi
    else
        log "Skipped applying eth0 static configuration."
    fi
elif [[ "$ETH_MODE_CHOICE" == "1" ]]; then
    ask_yesno "Apply DHCP config to eth0 now?" "y" CONFIRM_ETH

    if [[ "$CONFIRM_ETH" == "yes" ]]; then
        log "Setting eth0 to DHCP"

        nmcli connection modify "$ETH_CON_NAME" \
            ipv4.method auto \
            ipv4.addresses "" \
            ipv4.gateway "" \
            ipv4.dns "" \
            || die "Failed to modify the eth0 connection profile."

        if nmcli connection up "$ETH_CON_NAME" 2>/dev/null; then
            log "eth0 is now set to DHCP."
        else
            warn "Config saved but eth0 could not be brought up immediately."
        fi
    else
        log "Skipped applying eth0 DHCP configuration."
    fi
else
    log "Leaving eth0 unchanged."
fi

fi # IS_RASPBIAN

# ----------------------------------------------------------------------
# 3. Database server and credentials
# ----------------------------------------------------------------------
echo
echo "--- Database (PostgreSQL + TimescaleDB) ---"
CURRENT_DB_HOST="$(get_env_var SYSTEM_DB_HOST)"
DB_LOCAL_DEFAULT="y"
[[ -n "$CURRENT_DB_HOST" && "$CURRENT_DB_HOST" != "127.0.0.1" && "$CURRENT_DB_HOST" != "localhost" ]] && DB_LOCAL_DEFAULT="n"
ask_yesno "Run the database locally on this machine?" "$DB_LOCAL_DEFAULT" USE_LOCAL_DB

if [[ "$USE_LOCAL_DB" == "yes" ]]; then
    DB_HOST_VAL="127.0.0.1"
else
    [[ "$CURRENT_DB_HOST" == "127.0.0.1" || "$CURRENT_DB_HOST" == "localhost" ]] && CURRENT_DB_HOST=""
    ask_validated "Remote database server address" "$CURRENT_DB_HOST" DB_HOST_VAL is_valid_host \
        "Enter a valid hostname or IPv4 address."
fi

CURRENT_DB_PORT="$(get_env_var SYSTEM_DB_PORT)"
ask_validated "Database port" "${CURRENT_DB_PORT:-5432}" DB_PORT_VAL is_valid_port \
    "Port must be a number from 1 to 65535."

CURRENT_DB_USER="$(get_env_var SYSTEM_DB_USER)"
ask_validated "Database username for the EMS services" "${CURRENT_DB_USER:-iot_user}" DB_USER_VAL is_safe_identifier \
    "Use letters, digits and underscores only, starting with a letter or underscore."
[[ "$DB_USER_VAL" == "postgres" ]] && warn "Using the 'postgres' superuser for the services is not recommended — a dedicated role (e.g. iot_user) is safer."

ask_secret "Database password for '${DB_USER_VAL}'" "$(get_env_var SYSTEM_DB_PASSWORD)" DB_PASS_VAL

# The Dashboard connects as the 'postgres' superuser (see deploy.sh). On a
# local database deploy.sh sets this password; on a remote one it must
# already match that server.
CURRENT_PG_PASS="$(get_env_var DB_PASSWORD "$LARAVEL_ENV_FILE")"
if [[ "$USE_LOCAL_DB" == "yes" ]]; then
    ask_secret "PostgreSQL 'postgres' superuser password (used by the Dashboard)" "$CURRENT_PG_PASS" PG_SUPER_PASS_VAL
else
    ask_secret "Existing 'postgres' password on ${DB_HOST_VAL} (used by the Dashboard)" "$CURRENT_PG_PASS" PG_SUPER_PASS_VAL
fi

ask_yesno "Customize database names? (defaults: IOT_aq_sensor_data, IOT_seismic_sensor_data, IOT_sms_telemetry, IOT_api, IOT_service_logs)" "n" CUSTOM_DB_NAMES
declare -A DB_NAME_DEFAULTS=(
    [AQ_DB_NAME]="IOT_aq_sensor_data"
    [SEISMIC_DB_NAME]="IOT_seismic_sensor_data"
    [SMS_DB_NAME]="IOT_sms_telemetry"
    [API_DB_NAME]="IOT_api"
    [LOG_DB_NAME]="IOT_service_logs"
)
for key in AQ_DB_NAME SEISMIC_DB_NAME SMS_DB_NAME API_DB_NAME LOG_DB_NAME; do
    current="$(get_env_var "$key")"
    current="${current:-${DB_NAME_DEFAULTS[$key]}}"
    if [[ "$CUSTOM_DB_NAMES" == "yes" ]]; then
        ask_validated "  ${key}" "$current" value is_safe_identifier \
            "Use letters, digits and underscores only, starting with a letter or underscore."
    else
        value="$current"
        is_safe_identifier "$value" || die "${key}='${value}' in scripts/.env is not a valid database name — fix it or re-run and choose to customize database names."
    fi
    set_env_var "$key" "$value"
done

set_env_var SYSTEM_DB_HOST "$DB_HOST_VAL"
set_env_var SYSTEM_DB_PORT "$DB_PORT_VAL"
set_env_var SYSTEM_DB_USER "$DB_USER_VAL"
set_env_var SYSTEM_DB_PASSWORD "$DB_PASS_VAL"

# ----------------------------------------------------------------------
# 4. MQTT broker and credentials
# ----------------------------------------------------------------------
echo
echo "--- MQTT broker (Mosquitto) ---"
CURRENT_MQTT_HOST="$(get_env_var MQTT_BROKER_HOST)"
MQTT_LOCAL_DEFAULT="y"
[[ -n "$CURRENT_MQTT_HOST" && "$CURRENT_MQTT_HOST" != "127.0.0.1" && "$CURRENT_MQTT_HOST" != "localhost" ]] && MQTT_LOCAL_DEFAULT="n"
ask_yesno "Run the MQTT broker locally on this machine?" "$MQTT_LOCAL_DEFAULT" USE_LOCAL_MQTT

if [[ "$USE_LOCAL_MQTT" == "yes" ]]; then
    MQTT_HOST_VAL="localhost"
else
    [[ "$CURRENT_MQTT_HOST" == "127.0.0.1" || "$CURRENT_MQTT_HOST" == "localhost" ]] && CURRENT_MQTT_HOST=""
    [[ -z "$CURRENT_MQTT_HOST" && "$USE_LOCAL_DB" == "no" ]] && CURRENT_MQTT_HOST="$DB_HOST_VAL"
    ask_validated "Remote MQTT broker address" "$CURRENT_MQTT_HOST" MQTT_HOST_VAL is_valid_host \
        "Enter a valid hostname or IPv4 address."
fi

CURRENT_MQTT_PORT="$(get_env_var MQTT_BROKER_PORT)"
ask_validated "MQTT broker port" "${CURRENT_MQTT_PORT:-1883}" MQTT_PORT_VAL is_valid_port \
    "Port must be a number from 1 to 65535."

CURRENT_MQTT_USER="$(get_env_var MQTT_USER)"
ask_validated "MQTT username" "${CURRENT_MQTT_USER:-mqtt_user_seismic}" MQTT_USER_VAL is_safe_identifier \
    "Use letters, digits and underscores only, starting with a letter or underscore."
ask_secret "MQTT password for '${MQTT_USER_VAL}'" "$(get_env_var MQTT_PASSWORD)" MQTT_PASS_VAL

set_env_var MQTT_BROKER_HOST "$MQTT_HOST_VAL"
set_env_var MQTT_BROKER_PORT "$MQTT_PORT_VAL"
set_env_var MQTT_USER "$MQTT_USER_VAL"
set_env_var MQTT_PASSWORD "$MQTT_PASS_VAL"

# ----------------------------------------------------------------------
# 5. API key
# ----------------------------------------------------------------------
# api_server.py imports API_KEYS into the api_keys table on first start
# (only while that table is empty); after that, keys are managed in the
# Dashboard.
echo
echo "--- REST API ---"
CURRENT_API_KEYS="$(get_env_var API_KEYS)"
if is_placeholder "$CURRENT_API_KEYS"; then
    ask "Label for the first API key" "Admin" API_KEY_LABEL
    API_KEY_LABEL="${API_KEY_LABEL//,/ }"
    API_KEY_LABEL="${API_KEY_LABEL//:/ }"
    API_TOKEN="$(gen_secret 40)"
    set_env_var API_KEYS "${API_TOKEN}:${API_KEY_LABEL}"
    GENERATED_SECRETS+=("API key (${API_KEY_LABEL}): ${API_TOKEN}")
    log "Generated an API key for '${API_KEY_LABEL}'."
else
    log "Keeping the existing API_KEYS (manage further keys in the Dashboard)."
fi

# ----------------------------------------------------------------------
# 6. SMS ingestion
# ----------------------------------------------------------------------
echo
echo "--- SMS ingestion (SIM800L) ---"
SMS_DEFAULT="n"
[[ "$(get_env_var SMS_INGESTION_ENABLED)" == "true" ]] && SMS_DEFAULT="y"
ask_yesno "Enable SMS ingestion?" "$SMS_DEFAULT" SMS_ENABLED_CHOICE

if [[ "$SMS_ENABLED_CHOICE" == "yes" ]]; then
    set_env_var SMS_INGESTION_ENABLED "true"
    log "SMS ingestion: enabled"
else
    set_env_var SMS_INGESTION_ENABLED "false"
    log "SMS ingestion: disabled"
fi

# Last guard: nothing from the committed template may survive into a real install.
for key in SYSTEM_DB_PASSWORD MQTT_PASSWORD API_KEYS; do
    is_placeholder "$(get_env_var "$key")" && die "${key} in ${ENV_FILE} is still empty or a template placeholder."
done

log "Configuration saved to ${ENV_FILE}."

# ----------------------------------------------------------------------
# 7. Install packages and apply the configuration
# ----------------------------------------------------------------------
# deploy.sh is idempotent, so it always runs: it installs anything missing
# AND applies the credentials above (Postgres roles/passwords, databases,
# Mosquitto account, Dashboard .env). Skipping it when packages already
# exist would leave changed passwords unapplied.
echo
log "Running deploy.sh — installs all required packages and applies the configuration"
POSTGRES_PASSWORD="$PG_SUPER_PASS_VAL" "$DEPLOY_SCRIPT" \
    || die "deploy.sh failed — fix the error above, then re-run: sudo ./install.sh (your answers are saved as defaults)."

# ----------------------------------------------------------------------
# 8. Services and health check
# ----------------------------------------------------------------------
echo
ask_yesno "Install and start the EMS services now (start on boot)?" "y" INSTALL_SERVICES
if [[ "$INSTALL_SERVICES" == "yes" ]]; then
    "$INSTALL_SERVICES_SCRIPT" || warn "install_services.sh failed — see the error above, then run: sudo ./install_services.sh"
else
    log "Skipped. Run later with: sudo ./install_services.sh"
fi

echo
log "Checking the installation"
CHECK_STATUS=0
"$CHECK_REQUIREMENTS_SCRIPT" || CHECK_STATUS=$?

if (( ${#GENERATED_SECRETS[@]} > 0 )); then
    echo
    warn "Generated credentials — record these now, they are not shown again:"
    for s in "${GENERATED_SECRETS[@]}"; do
        echo "    $s"
    done
    echo "    (database and MQTT passwords and the API key are also stored in ${ENV_FILE})"
fi

echo
if (( CHECK_STATUS == 0 )); then
    log "Setup complete."
else
    warn "Setup finished, but check_requirements.sh reported problems (see above)."
    exit "$CHECK_STATUS"
fi
