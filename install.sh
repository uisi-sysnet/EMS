#!/usr/bin/env bash
#
# configure.sh — interactive setup wizard for the IoT gateway (Raspberry Pi 4B,
# Raspberry Pi OS Lite / Bookworm). Run this FIRST, before deploy.sh.
#
# It asks you:
#   1. Standalone (turn wlan0 into a WiFi Access Point + ensure SSH is on)
#      or Stay as-is (make no WiFi/network changes at all)
#   2. Static IP / subnet / gateway / DNS for the wired (eth0) port
#   3. Whether the Database + MQTT broker are local or on another server
#   4. Database + MQTT username/password
#   5. Whether SMS (SIM800L) ingestion should be enabled
#
# It writes the answers into .env (creating it from _env if needed) and into
# NetworkManager connection profiles, then hands off to deploy.sh to do the
# actual package installation.
#
# ASSUMPTION: Raspberry Pi OS Bookworm (or newer), which uses NetworkManager
# (nmcli) for networking by default — this matches what deploy.sh already
# assumes elsewhere (it looks for /boot/firmware/config.txt, the Bookworm
# path). If you're on an older dhcpcd-based image, the network-related steps
# below (AP mode, static eth0) won't apply and you'll need to configure those
# manually via dhcpcd.conf/hostapd instead.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env"
ENV_TEMPLATE="${SCRIPT_DIR}/_env"
DEPLOY_SCRIPT="${SCRIPT_DIR}/deploy.sh"

log()  { echo -e "\033[1;32m[setup]\033[0m $*"; }
warn() { echo -e "\033[1;33m[setup][WARN]\033[0m $*"; }
die()  { echo -e "\033[1;31m[setup][ERROR]\033[0m $*" >&2; exit 1; }

# ----------------------------------------------------------------------
# 0. Preflight
# ----------------------------------------------------------------------
if [[ $EUID -ne 0 ]]; then
    die "Run this with sudo: sudo ./configure.sh"
fi

[[ -f "$DEPLOY_SCRIPT" ]] || die "deploy.sh not found next to this script (expected at $DEPLOY_SCRIPT)."

if [[ ! -f "$ENV_FILE" ]]; then
    if [[ -f "$ENV_TEMPLATE" ]]; then
        log "No .env found — creating one from _env template"
        cp "$ENV_TEMPLATE" "$ENV_FILE"
    else
        die ".env not found and no _env template to copy from. Place one of these at $SCRIPT_DIR first."
    fi
fi

# ----------------------------------------------------------------------
# Prompt helpers
# ----------------------------------------------------------------------
# ask "Prompt text" "default" VARNAME  — shows the default, Enter keeps it.
ask() {
    local prompt="$1" default="$2" __resultvar="$3" input
    read -r -p "${prompt} [${default}]: " input || true
    input="${input:-$default}"
    printf -v "$__resultvar" '%s' "$input"
}

# ask_password "Prompt text" "current value" VARNAME — hidden input, Enter keeps current.
ask_password() {
    local prompt="$1" default="$2" __resultvar="$3" input
    read -r -s -p "${prompt} [press Enter to keep current]: " input || true
    echo
    input="${input:-$default}"
    printf -v "$__resultvar" '%s' "$input"
}

# ask_yesno "Prompt text" "y|n default" VARNAME — normalizes to yes/no.
ask_yesno() {
    local prompt="$1" default="$2" __resultvar="$3" input
    read -r -p "${prompt} (y/n) [${default}]: " input || true
    input="${input:-$default}"
    case "$input" in
        y|Y|yes|Yes|YES) printf -v "$__resultvar" 'yes' ;;
        *)               printf -v "$__resultvar" 'no' ;;
    esac
}

# get_env_var KEY — prints the current value of KEY from .env (empty if unset).
get_env_var() {
    local key="$1"
    grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- || true
}

# set_env_var KEY VALUE — replaces an existing 'KEY=...' line in .env, or
# appends one. Same logic deploy.sh already uses for its own .env edits.
set_env_var() {
    local key="$1" value="$2"
    local escaped_value="${value//&/\\&}"
    if grep -qE "^${key}=" "$ENV_FILE"; then
        sed -i -E "s|^${key}=.*|${key}=${escaped_value}|" "$ENV_FILE"
    else
        printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
    fi
}

# Basic sanity check for a dotted-quad IPv4 address (not exhaustive, just
# catches obvious typos before we hand it to NetworkManager).
is_valid_ipv4() {
    local ip="$1" IFS=. o1 o2 o3 o4
    [[ "$ip" =~ ^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$ ]] || return 1
    read -r o1 o2 o3 o4 <<< "${ip//./ }"
    for o in "$o1" "$o2" "$o3" "$o4"; do
        [[ "$o" -ge 0 && "$o" -le 255 ]] || return 1
    done
    return 0
}

echo "=========================================="
echo " IoT Gateway Setup Wizard"
echo " Raspberry Pi 4B / Raspberry Pi OS Lite"
echo "=========================================="
echo

# ----------------------------------------------------------------------
# 1. Standalone vs Stay as-is
# ----------------------------------------------------------------------
echo "--- Network mode ---"
echo "  1) Standalone — turns wlan0 into a WiFi Access Point so you can connect"
echo "     directly to this Pi's own WiFi and SSH into it. Useful with no"
echo "     existing network available."
echo "  2) Stay as-is — no WiFi/AP changes are made at all."
echo
read -r -p "Select [1/2] (default: 2): " NET_MODE_CHOICE || true
NET_MODE_CHOICE="${NET_MODE_CHOICE:-2}"

if [[ "$NET_MODE_CHOICE" == "1" ]]; then
    command -v nmcli >/dev/null 2>&1 || die "NetworkManager (nmcli) not found. This wizard's AP setup assumes Raspberry Pi OS Bookworm+. On an older dhcpcd-based image, configure the AP manually with hostapd/dnsmasq instead."
    ip link show wlan0 >/dev/null 2>&1 || die "No wlan0 interface found — is the Pi's onboard WiFi present/enabled?"

    echo
    ask "Access Point SSID (WiFi network name)" "IOT-Gateway" AP_SSID
    while true; do
        ask_password "AP WiFi password (min 8 characters, blank = generate one)" "" AP_PASSWORD
        if [[ -z "$AP_PASSWORD" ]]; then
            AP_PASSWORD="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 12)"
            log "Generated AP password: ${AP_PASSWORD}  (write this down)"
            break
        elif [[ ${#AP_PASSWORD} -ge 8 ]]; then
            break
        else
            warn "Password must be at least 8 characters — try again."
        fi
    done

    log "Enabling SSH"
    systemctl enable ssh --now 2>/dev/null || warn "Could not enable ssh automatically — enable it manually with: sudo raspi-config -> Interface Options -> SSH"

    log "Creating WiFi Access Point profile '${AP_SSID}' on wlan0"
    nmcli connection delete "${AP_SSID}" >/dev/null 2>&1 || true
    nmcli connection add type wifi ifname wlan0 con-name "${AP_SSID}" autoconnect yes ssid "${AP_SSID}" \
        802-11-wireless.mode ap 802-11-wireless.band bg \
        ipv4.method shared \
        wifi-sec.key-mgmt wpa-psk wifi-sec.psk "${AP_PASSWORD}" \
        || die "Failed to create the AP connection profile."

    if nmcli connection up "${AP_SSID}"; then
        log "AP is up. Connect to WiFi '${AP_SSID}' and SSH to this Pi (AP gateway IP is typically 10.42.0.1)."
    else
        warn "AP profile created but couldn't bring it up right now (maybe already using wlan0 for something else). It should activate on next boot."
    fi
else
    log "Staying as-is — no WiFi/AP changes made."
fi

# ----------------------------------------------------------------------
# 2. Static IP for the ethernet port
# ----------------------------------------------------------------------
echo
echo "--- Wired (eth0) static IP configuration ---"
warn "If you are connected over SSH via eth0 right now, changing its IP will drop your session — reconnect using the new address afterward."
echo

while true; do
    ask "Static IP address for eth0" "192.168.1.10" ETH_IP
    is_valid_ipv4 "$ETH_IP" && break
    warn "That doesn't look like a valid IPv4 address — try again."
done
ask "Subnet prefix length (e.g. 24 = 255.255.255.0)" "24" ETH_PREFIX
while true; do
    ask "Gateway" "192.168.1.1" ETH_GATEWAY
    is_valid_ipv4 "$ETH_GATEWAY" && break
    warn "That doesn't look like a valid IPv4 address — try again."
done
while true; do
    ask "DNS server" "192.168.1.1" ETH_DNS
    is_valid_ipv4 "$ETH_DNS" && break
    warn "That doesn't look like a valid IPv4 address — try again."
done

read -r -p "Apply this static config to eth0 now? (y/n) [y]: " CONFIRM_ETH || true
CONFIRM_ETH="${CONFIRM_ETH:-y}"
if [[ "$CONFIRM_ETH" == "y" || "$CONFIRM_ETH" == "Y" ]]; then
    command -v nmcli >/dev/null 2>&1 || die "NetworkManager (nmcli) not found — cannot set a static IP automatically. Configure eth0 manually instead."

    ETH_CON_NAME="$(nmcli -t -f NAME,DEVICE connection show 2>/dev/null | awk -F: '$2=="eth0"{print $1; exit}')"
    if [[ -z "$ETH_CON_NAME" ]]; then
        ETH_CON_NAME="Wired connection eth0"
        log "No existing eth0 connection profile found — creating '${ETH_CON_NAME}'"
        nmcli connection add type ethernet ifname eth0 con-name "$ETH_CON_NAME" || die "Could not create an ethernet connection profile for eth0."
    fi

    log "Setting eth0 to static ${ETH_IP}/${ETH_PREFIX}, gateway ${ETH_GATEWAY}, DNS ${ETH_DNS}"
    nmcli connection modify "$ETH_CON_NAME" \
        ipv4.addresses "${ETH_IP}/${ETH_PREFIX}" \
        ipv4.gateway "${ETH_GATEWAY}" \
        ipv4.dns "${ETH_DNS}" \
        ipv4.method manual \
        || die "Failed to modify the eth0 connection profile."

    if nmcli connection up "$ETH_CON_NAME" 2>/dev/null; then
        log "eth0 is now static at ${ETH_IP}/${ETH_PREFIX}."
    else
        warn "Config saved but couldn't bring eth0 up immediately (likely because you're using it right now). It will apply on next boot/reconnect."
    fi
else
    log "Skipped applying eth0 static config."
fi

# ----------------------------------------------------------------------
# 3. Database / MQTT location
# ----------------------------------------------------------------------
echo
echo "--- Database & MQTT server location ---"
ask_yesno "Run Database + MQTT locally on this Pi?" "y" USE_LOCAL_DB

if [[ "$USE_LOCAL_DB" == "yes" ]]; then
    DB_HOST_VAL="127.0.0.1"
    MQTT_HOST_VAL="localhost"
else
    ask "Remote Database server address" "$(get_env_var SYSTEM_DB_HOST)" DB_HOST_VAL
    ask "Remote MQTT broker address" "$DB_HOST_VAL" MQTT_HOST_VAL
fi

set_env_var SYSTEM_DB_HOST "$DB_HOST_VAL"
set_env_var MQTT_BROKER_HOST "$MQTT_HOST_VAL"

# ----------------------------------------------------------------------
# 4. Database / MQTT credentials (default = whatever is already in .env)
# ----------------------------------------------------------------------
echo
echo "--- Database credentials ---"
ask "Database username" "$(get_env_var SYSTEM_DB_USER)" DB_USER_VAL
ask_password "Database password" "$(get_env_var SYSTEM_DB_PASSWORD)" DB_PASS_VAL

echo
echo "--- MQTT credentials ---"
ask "MQTT username" "$(get_env_var MQTT_USER)" MQTT_USER_VAL
ask_password "MQTT password" "$(get_env_var MQTT_PASSWORD)" MQTT_PASS_VAL

set_env_var SYSTEM_DB_USER "$DB_USER_VAL"
set_env_var SYSTEM_DB_PASSWORD "$DB_PASS_VAL"
set_env_var MQTT_USER "$MQTT_USER_VAL"
set_env_var MQTT_PASSWORD "$MQTT_PASS_VAL"

# ----------------------------------------------------------------------
# 5. SMS ingestion
# ----------------------------------------------------------------------
echo
echo "--- SMS ingestion (SIM800L) ---"
ask_yesno "Enable SMS ingestion?" "n" SMS_ENABLED_CHOICE
if [[ "$SMS_ENABLED_CHOICE" == "yes" ]]; then
    set_env_var SMS_INGESTION_ENABLED "true"
    log "SMS ingestion: enabled"
else
    set_env_var SMS_INGESTION_ENABLED "false"
    log "SMS ingestion: disabled"
fi

chmod 600 "$ENV_FILE" || warn "Could not chmod .env — check permissions manually"

# ----------------------------------------------------------------------
# 6. Hand off to deploy.sh
# ----------------------------------------------------------------------
echo
log "Configuration saved to .env. Starting installation via deploy.sh..."
echo
exec "$DEPLOY_SCRIPT"