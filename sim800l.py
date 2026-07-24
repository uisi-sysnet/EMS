#!/usr/bin/env python3
"""
sim800l.py
Minimal AT-command driver for a SIM800L GSM/GPRS module, used by
seismic_mqtt.py to receive seismic telemetry via SMS as a second,
MQTT-independent ingestion channel — useful for stations with cellular
coverage but no data/WiFi network, or as a fallback when the MQTT link
is down.

WIRING
------
The SIM800L talks over UART (TX/RX), not GPIO in the digital-pin sense —
this driver only opens a serial device path; it never touches GPIO pins
directly. On a Raspberry Pi, the hardware UART is fixed to specific pins
by the SoC:

    Pi GPIO14 (TXD, physical header pin 8)  -> SIM800L RXD
    Pi GPIO15 (RXD, physical header pin 10) -> SIM800L TXD
    Pi GND (any GND pin)                     -> SIM800L GND

If you're using a USB-TTL adapter, or a Pi model/overlay that exposes the
UART on different pins, just point SIM800_SERIAL_PORT (in .env) at
whatever device Linux exposes for it (e.g. /dev/serial0, /dev/ttyAMA0,
/dev/ttyUSB0) — no code changes needed.

IMPORTANT: power the SIM800L from its own regulated ~4V supply, not the
Pi's 3V3/5V rail — it can pull up to ~2A in short bursts while
transmitting, which will brown out the Pi.

Before wiring into this script, it's worth confirming your module responds
to plain AT commands first with a serial terminal (e.g. `screen
/dev/serial0 9600` or `minicom -D /dev/serial0 -b 9600`, then type `AT`
and expect `OK`). Exact response formatting/timing can vary slightly by
SIM800L firmware/clone; the parsing in this module was written against the
documented AT command set (SIMCom SIM800 Series AT Command Manual) — if
your specific module's +CMGL/+CMGR responses look different, adjust
_parse_cmgl()'s regex accordingly.
"""

import logging
import re
import threading
import time

import serial

logger = logging.getLogger("sim800l")

_CMGL_HEADER_RE = re.compile(
    r'^\+CM(?:GL|GR):\s*(?:(\d+),)?"([^"]*)","([^"]*)",[^,]*,"([^"]*)"'
)
_CMTI_RE = re.compile(r'\+CMTI:\s*"[^"]*",(\d+)')


class SIM800LError(Exception):
    pass


class SIM800L:
    def __init__(self, port="/dev/serial0", baudrate=9600, timeout=5):
        self.port = port
        self.baudrate = baudrate
        self.timeout = timeout
        self._ser = None
        self._lock = threading.RLock()

    # ---- connection lifecycle ----

    def open(self):
        with self._lock:
            if self._ser and self._ser.is_open:
                return
            self._ser = serial.Serial(self.port, self.baudrate, timeout=self.timeout)
            time.sleep(1)  # let the module settle after (re)opening the port

    def close(self):
        with self._lock:
            if self._ser:
                try:
                    self._ser.close()
                except Exception:
                    pass
                self._ser = None

    @property
    def is_open(self):
        return self._ser is not None and self._ser.is_open

    def initialize(self):
        """Bring the modem to a known state: echo off, text-mode SMS, GSM
        charset, and unsolicited '+CMTI' notifications on new SMS."""
        self.open()
        self.send_at("AT")                  # basic liveness check
        self.send_at("ATE0")                # echo off — keeps response parsing simple
        self.send_at('AT+CMGF=1')           # text-mode SMS (not PDU mode)
        self.send_at('AT+CSCS="GSM"')       # GSM 7-bit charset
        self.send_at('AT+CNMI=2,1,0,0,0')   # new SMS -> unsolicited +CMTI:"SM",<index>
        logger.info(f"SIM800L initialized on {self.port} @ {self.baudrate} baud")

    # ---- low-level AT command I/O ----

    def _write_line(self, line):
        self._ser.write((line + "\r\n").encode("ascii", errors="ignore"))

    def send_at(self, command, expect="OK", timeout=None, retries=2):
        """Sends an AT command and waits for a line equal to/starting with
        `expect` (default 'OK'). Raises SIM800LError on 'ERROR'/'+CME
        ERROR'/'+CMS ERROR' or on timeout, after `retries` attempts."""
        with self._lock:
            last_exc = None
            for attempt in range(retries + 1):
                try:
                    self._ser.reset_input_buffer()
                    self._write_line(command)
                    return self._read_until(expect, timeout or self.timeout)
                except SIM800LError as e:
                    last_exc = e
                    time.sleep(0.5)
            raise last_exc

    def _read_until(self, expect, timeout):
        deadline = time.time() + timeout
        buf = ""
        lines = []
        while time.time() < deadline:
            waiting = self._ser.in_waiting
            chunk = self._ser.read(waiting or 1)
            if chunk:
                buf += chunk.decode(errors="ignore")
                while "\r\n" in buf:
                    line, buf = buf.split("\r\n", 1)
                    line = line.strip()
                    if not line:
                        continue
                    lines.append(line)
                    if line == "ERROR" or line.startswith("+CME ERROR") or line.startswith("+CMS ERROR"):
                        raise SIM800LError(f"Modem returned error for command: {line}")
                    if line == expect or line.startswith(expect):
                        return lines
            else:
                time.sleep(0.05)
        raise SIM800LError(f"Timed out waiting for '{expect}' — got: {lines}")

    # ---- SMS operations ----

    def list_unread_messages(self):
        """Full-inbox sweep: returns every stored SMS (any status). Used as
        a periodic safety net in case a +CMTI notification was ever
        missed (e.g. the process wasn't running when it arrived)."""
        with self._lock:
            self._ser.reset_input_buffer()
            self._write_line('AT+CMGL="ALL"')
            lines = self._read_until("OK", self.timeout)
        return self._parse_cmgl(lines)

    def read_message(self, index):
        with self._lock:
            self._ser.reset_input_buffer()
            self._write_line(f"AT+CMGR={index}")
            lines = self._read_until("OK", self.timeout)
        parsed = self._parse_cmgl(lines, single_index=index)
        return parsed[0] if parsed else None

    def delete_message(self, index):
        self.send_at(f"AT+CMGD={index}")

    @staticmethod
    def _parse_cmgl(lines, single_index=None):
        """Parses +CMGL/+CMGR response lines into
        [{index, status, sender, timestamp, body}, ...]."""
        results = []
        i = 0
        while i < len(lines):
            line = lines[i]
            if line.startswith("+CMGL:") or line.startswith("+CMGR:"):
                m = _CMGL_HEADER_RE.match(line)
                body = lines[i + 1] if i + 1 < len(lines) else ""
                if m:
                    idx = m.group(1) or single_index
                    results.append({
                        "index": int(idx) if idx is not None else None,
                        "status": m.group(2),
                        "sender": m.group(3),
                        "timestamp": m.group(4),
                        "body": body.strip(),
                    })
                else:
                    logger.warning(f"Could not parse SMS header line, skipping: {line!r}")
                i += 2
            else:
                i += 1
        return results

    def wait_for_notification(self, timeout=1.0):
        """Reads any pending unsolicited '+CMTI:' lines for up to `timeout`
        seconds without sending a command. Returns a list of newly-arrived
        message indices (possibly empty)."""
        indices = []
        with self._lock:
            deadline = time.time() + timeout
            buf = ""
            while time.time() < deadline:
                waiting = self._ser.in_waiting
                if not waiting:
                    time.sleep(0.05)
                    continue
                chunk = self._ser.read(waiting)
                buf += chunk.decode(errors="ignore")
                while "\r\n" in buf:
                    line, buf = buf.split("\r\n", 1)
                    line = line.strip()
                    if line.startswith("+CMTI:"):
                        m = _CMTI_RE.search(line)
                        if m:
                            indices.append(int(m.group(1)))
        return indices
