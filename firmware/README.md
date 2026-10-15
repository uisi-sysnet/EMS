# Water Level GSM Firmware

Two Arduino Nano sketches that let water level sensors report to EMS by SMS, with no internet connection needed at the sensor or the gateway.

```text
Water level station (Nano + SIM800L + URM06-PULSE)
        |  SMS: WL1,<station>,<seq>,<distance_cm>,<battery_mV>,<temp>,<interval>
        v
GSM gateway (Nano + SIM800L)  --USB serial-->  scripts/water_level_gsm.py  -->  IOT_water_level
        ^                                                |
        |  SMS: WLCFG,<minutes>   <--  interval set per station in the dashboard
```

| Sketch | Runs on | Job |
| ------ | ------- | --- |
| `gsm_gateway/gsm_gateway.ino` | Nano + SIM800L, plugged into the EMS server by USB | Passes received SMS to the server; sends the SMS the server asks for. Each SMS stays on the SIM until the server has saved it. |
| `water_level_sensor/water_level_sensor.ino` | Nano + SIM800L + DFRobot URM06-PULSE at each station | Measures distance to the water and texts a reading every interval. Applies interval changes sent from the dashboard and confirms them. |

## Parts (per device)

- Arduino Nano (ATmega328P)
- SIM800L module and a SIM with the PIN lock disabled
- Power for the SIM800L: **3.7–4.2 V, able to deliver 2 A peaks** (an 18650 Li-ion cell, or a buck converter set to 4.0 V) and a 470–1000 µF capacitor across its VCC/GND
- 1 kΩ and 2 kΩ resistors (level shifter for the SIM800L's RX pin)
- Station only: DFRobot **URM06-PULSE** ultrasonic sensor (SEN0151, range 20 cm – 10 m); a **6–12 V supply** for it (e.g. a 2S Li-ion pack or a 12 V battery, which can also feed the Nano's VIN pin); 2 × 100 kΩ resistors for battery monitoring; optional DS18B20 temperature probe with a 4.7 kΩ pull-up

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
| URM06-PULSE Trig / Echo | D5 / D6 (sensor VCC 6–12 V, GND common with the Nano) |
| Battery divider midpoint (battery + → 100 kΩ → A0 → 100 kΩ → GND) | A0 |
| DS18B20 data (optional) | D7 |

Mount the URM06-PULSE pointing straight down at the water, **at least 20 cm above the highest expected water level** (it can't measure closer than 20 cm). Its height above your reference point is the station's **Installation Height** in the dashboard; EMS calculates water level as installation height minus the measured distance.

How the sketch reads it: Trig idles HIGH; a LOW pulse of 60 µs starts one measurement; Echo returns a HIGH pulse of **1 µs per mm**. A 15,000 µs pulse means the sensor couldn't measure. Each reading is the median of 5 measurements. If none succeed, the station sends distance `0`, which EMS stores as "no echo" (no water level) and logs as a Device warning.

## Setup

1. **Install the Arduino IDE** and select **Tools › Board › Arduino Nano**, **Processor › ATmega328P**. (Many clone Nanos need **ATmega328P (Old Bootloader)** — use that if the upload fails.)
2. **Gateway:** open `gsm_gateway/gsm_gateway.ino` and upload it. Nothing to edit.
3. **Sensor:** open `water_level_sensor/water_level_sensor.ino`, set the three `>>>` settings at the top, and upload:
   - `STATION_MN` — the station's Station MN in the dashboard
   - `GATEWAY_NUMBER` — the gateway SIM's number, e.g. `+639171234567`
   - `DEFAULT_INTERVAL` — interval used until the dashboard sends one
   For the DS18B20 probe, set `USE_DS18B20 1` and install the **OneWire** and **DallasTemperature** libraries first.
4. **Dashboard:** in **Stations › Water Level**, add the station with the sensor's **SIM number** (the IP address can stay empty), **Installation Height**, and **Reporting Interval**.
5. **Server:** plug the gateway Nano into a USB port of the EMS server. In **Stations › Water Level**, scroll to **GSM Gateway (USB)**, click **Detect ports**, then **Use this port** on the Nano (a CH340 / FTDI chip is marked as the likely gateway). This saves `WATER_GSM_SERIAL_PORT` (the stable `/dev/serial/by-id/...` name) and `WATER_GSM_ENABLED=true` in `scripts/.env` and restarts `ems-water-level-gsm.service`. The panel also warns if **brltty** (takes over CH340 ports on Ubuntu) or **ModemManager** (disturbs the SIM800L) is present.
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
- **"the sensor got no echo" on the Logs page:** check the URM06 wiring (Trig D5, Echo D6), its 6–12 V supply, and that nothing is within 20 cm of the sensor or blocking its beam.
- **The gateway Nano isn't listed under Detect ports:** try another USB cable (some are charge-only) and run `ls /dev/serial/by-id/`. On Ubuntu, remove brltty (`sudo apt remove brltty`), which takes over CH340 ports.
