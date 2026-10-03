# Water Level GSM Firmware

Two Arduino Nano sketches that let water level sensors report to EMS by SMS, with no internet connection needed at the sensor or the gateway.

```text
Water level sensor (Nano + SIM800L + JSN-SR04T)
        |  SMS: WL1,<station>,<seq>,<distance_cm>,<battery_mV>,<temp>,<interval>
        v
GSM gateway (Nano + SIM800L)  --USB serial-->  scripts/water_level_gsm.py  -->  IOT_water_level
        ^                                                |
        |  SMS: WLCFG,<minutes>   <--  interval set per station in the dashboard
```

| Sketch | Runs on | Job |
| ------ | ------- | --- |
| `gsm_gateway/gsm_gateway.ino` | Nano + SIM800L, plugged into the EMS server by USB | Passes received SMS to the server; sends the SMS the server asks for. Each SMS stays on the SIM until the server has saved it. |
| `water_level_sensor/water_level_sensor.ino` | Nano + SIM800L + JSN-SR04T at each station | Measures distance to the water and texts a reading every interval. Applies interval changes sent from the dashboard and confirms them. |

## Parts (per device)

- Arduino Nano (ATmega328P)
- SIM800L module and a SIM with the PIN lock disabled
- Power for the SIM800L: **3.7–4.2 V, able to deliver 2 A peaks** (an 18650 Li-ion cell, or a buck converter set to 4.0 V) and a 470–1000 µF capacitor across its VCC/GND
- 1 kΩ and 2 kΩ resistors (level shifter for the SIM800L's RX pin)
- Sensor only: JSN-SR04T waterproof ultrasonic sensor; 2 × 100 kΩ resistors for battery monitoring; optional DS18B20 temperature probe with a 4.7 kΩ pull-up

## Wiring

Both devices connect the SIM800L the same way:

| SIM800L | Nano | Notes |
| ------- | ---- | ----- |
| TXD | D2 | |
| RXD | D3 | Through a divider: D3 → 1 kΩ → RXD, and RXD → 2 kΩ → GND |
| RST | D4 | Lets the Nano restart a hung modem |
| VCC | — | Separate 3.7–4.2 V / 2 A supply, **not** the Nano's 5V or 3V3 pin |
| GND | GND | Common ground with the Nano and the supply |

Sensor only:

| Part | Nano |
| ---- | ---- |
| JSN-SR04T TRIG / ECHO | D5 / D6 (VCC 5 V) |
| Battery divider midpoint (battery + → 100 kΩ → A0 → 100 kΩ → GND) | A0 |
| DS18B20 data (optional) | D7 |

Mount the JSN-SR04T pointing straight down at the water. Its height above your reference point is the station's **Installation Height** in the dashboard; EMS calculates water level as installation height minus the measured distance.

## Setup

1. **Install the Arduino IDE** and select **Tools › Board › Arduino Nano**, **Processor › ATmega328P**. (Many clone Nanos need **ATmega328P (Old Bootloader)** — use that if the upload fails.)
2. **Gateway:** open `gsm_gateway/gsm_gateway.ino` and upload it. Nothing to edit.
3. **Sensor:** open `water_level_sensor/water_level_sensor.ino`, set the three `>>>` settings at the top, and upload:
   - `STATION_MN` — the station's Station MN in the dashboard
   - `GATEWAY_NUMBER` — the gateway SIM's number, e.g. `+639171234567`
   - `DEFAULT_INTERVAL` — interval used until the dashboard sends one
   For the DS18B20 probe, set `USE_DS18B20 1` and install the **OneWire** and **DallasTemperature** libraries first.
4. **Dashboard:** in **Stations › Water Level**, add the station with the sensor's **SIM number** (the IP address can stay empty), **Installation Height**, and **Reporting Interval**.
5. **Server:** plug the gateway Nano into the EMS server, then in `scripts/.env` set:
   ```text
   WATER_GSM_ENABLED=true
   WATER_GSM_SERIAL_PORT=/dev/serial/by-id/<your Nano>   (see: ls /dev/serial/by-id/)
   ```
   and restart the service: `sudo systemctl restart ems-water-level-gsm.service`.
6. **Check:** `sudo journalctl -u ems-water-level-gsm.service -f` should show `GSM gateway ready`, a signal/registration line every minute, and the interval being sent to the sensor. The sensor sends its first reading one minute after power-up.

## Changing the interval

Edit the station in **Stations › Water Level** and change **Reporting Interval**. The Interval column shows:

- **Pending** — waiting to be sent
- **Sent** — texted to the sensor, waiting for its confirmation
- **Applied** — the sensor confirmed it (it replies `WLACK` immediately and reports its interval in every reading)

Unconfirmed settings are re-sent every `WATER_GSM_RESEND_MINUTES` (default 30). The sensor saves the interval, so it survives power loss. It only accepts settings from `GATEWAY_NUMBER`.

On the dashboard, a water level station counts as online when it has reported within its interval plus 2 minutes, and idle until it misses a second interval.

## Cost

Receiving SMS is free, so the gateway SIM only pays for interval changes. Each sensor SIM pays for its readings:

| Interval | SMS per sensor per day |
| -------- | ---------------------- |
| 5 min | 288 |
| 15 min | 96 |
| 30 min | 48 |
| 60 min | 24 |

Use an unlimited-text promo on the sensor SIMs. Keep the gateway and sensor SIMs on the same network if the promo only covers same-network texts.

## Testing without a sensor

Text the gateway SIM from any phone registered as a station's SIM number, for example:

```text
WL1,WLS001,1,150,3900,NA,15
```

It appears in the dashboard within seconds. Every received SMS, including rejected ones (unknown sender, wrong format), is logged in the `gsm_messages` table of `IOT_water_level`.

To watch the gateway directly, stop the service and open the Nano's port in the Arduino Serial Monitor at **57600 baud**, newline line endings. Type `PING` (expect `+PONG`); received SMS appear as `+SMS|...` lines.

## Troubleshooting

- **Modem keeps resetting / `+ERR|modem not responding`:** the power supply can't deliver 2 A peaks. Use a Li-ion cell or a stronger buck converter, add the capacitor, and keep the power wires short.
- **`network NOT registered`:** check the antenna, the SIM (PIN lock off, active, has load), and signal (CSQ below 10 is weak; 2G coverage is required by the SIM800L).
- **Readings logged as "no station has SIM number":** the station's SIM number in the dashboard doesn't match the sender. Numbers are compared in `+63...` form; `09...` is converted automatically.
- **Water level shows "—" but distance is stored:** set the station's Installation Height.
