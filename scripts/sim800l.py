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
_parse_cmgl_raw()'s regex (_CMGL_ENTRY_RE) accordingly.
"""

import logging
import re
import threading
import time

import serial

logger = logging.getLogger("sim800l")

_CMGL_ENTRY_RE = re.compile(
    r'\+CM(?:GL|GR):\s*(?:(\d+),)?"([^"]*)","([^"]*)",[^,]*,"([^"]*)"\r?\n'
)
_CMGL_INDEX_ONLY_RE = re.compile(r'\+CM(?:GL|GR):\s*(\d+)')
_CMTI_RE = re.compile(r'\+CMTI:\s*"[^"]*",(\d+)')


class SIM800LError(Exception):
    pass


class SIM800LTimeoutError(SIM800LError):
    """Raised by _read_raw_until_ok() on timeout. Carries whatever raw text
    was captured before the deadline (`.partial`) so a stalled response —
    e.g. one that never reaches a final OK because an unsolicited +CMTI
    for a newly-arrived message landed mid-dump and the module stalled
    after it — doesn't have to be thrown away wholesale; callers reading a
    multi-message response can still recover the entries that did arrive
    intact."""

    def __init__(self, message, partial):
        super().__init__(message)
        self.partial = partial


class SIM800L:
    def __init__(self, port="/dev/serial0", baudrate=9600, timeout=5):
        self.port = port
        self.baudrate = baudrate
        self.timeout = timeout
        self._ser = None
        self._lock = threading.RLock()
        self._initialized = False

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
            self._initialized = False

    @property
    def is_open(self):
        return self._ser is not None and self._ser.is_open

    def initialize(self, force=False):
        """Bring the modem to a known state: echo off, text-mode SMS, GSM
        charset, and unsolicited '+CMTI' notifications on new SMS.

        Idempotent by default: if this instance already completed
        initialization and the port is still open, this is a no-op — it
        will NOT resend the AT setup commands again. Pass force=True to
        resend them anyway (e.g. after is_alive() confirms the modem
        actually stopped responding and you want a fresh handshake)."""
        if self._initialized and self.is_open and not force:
            logger.debug(f"SIM800L on {self.port} already initialized — skipping re-init")
            return
        self.open()
        self.send_at("AT")                  # basic liveness check
        self.send_at("ATE0")                # echo off — keeps response parsing simple
        self.send_at('AT+CMGF=1')           # text-mode SMS (not PDU mode)
        self.send_at('AT+CSCS="GSM"')       # GSM 7-bit charset
        self.send_at('AT+CNMI=2,1,0,0,0')   # new SMS -> unsolicited +CMTI:"SM",<index>
        self._initialized = True
        logger.info(f"SIM800L initialized on {self.port} @ {self.baudrate} baud")

    def is_alive(self):
        """Cheap liveness check: sends a bare 'AT' and confirms the modem
        still answers, WITHOUT resending the full init sequence.

        Use this in your reconnect/error-handling loop before deciding to
        call initialize(force=True). A failed read (e.g. a CMGL/CMGR
        response that times out because the data came through corrupted)
        does not necessarily mean the modem connection itself is dead —
        and a full reinit won't fix corrupted bytes on the wire, so it's
        worth telling the two failure modes apart before reinitializing."""
        if not self.is_open:
            return False
        try:
            self.send_at("AT", timeout=2, retries=0)
            return True
        except SIM800LError:
            return False

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

    def _read_raw_until_ok(self, timeout):
        """Like _read_until(), but scans the accumulated buffer as a whole
        for a terminating 'OK'/'ERROR' line instead of consuming it strictly
        line-by-line as it arrives. Used for CMGL/CMGR responses instead of
        _read_until(): a stored message with a multi-line body, or one whose
        header/body contains raw control bytes the modem can't render as
        clean text (e.g. an old binary/flash-class SMS left on the SIM from
        before this station used it), can otherwise make a line-by-line
        reader misjudge where one logical line ends and the next begins —
        which then desyncs parsing for everything read afterwards, including
        the final OK. Returns the full raw text; parsing into messages
        happens separately in _parse_cmgl_raw()."""
        deadline = time.time() + timeout
        buf = ""
        while time.time() < deadline:
            waiting = self._ser.in_waiting
            chunk = self._ser.read(waiting or 1)
            if chunk:
                buf += chunk.decode(errors="ignore")
                for line in buf.splitlines():
                    line = line.strip()
                    if line == "ERROR" or line.startswith("+CME ERROR") or line.startswith("+CMS ERROR"):
                        raise SIM800LError(f"Modem returned error: {line}")
                    if line == "OK":
                        return buf
            else:
                time.sleep(0.05)
        raise SIM800LTimeoutError(f"Timed out waiting for 'OK' — got raw: {buf!r}", partial=buf)

    # ---- SMS operations ----

    def list_unread_messages(self, timeout=None):
        """Full-inbox sweep: returns every stored SMS (any status). Used as
        a periodic safety net in case a +CMTI notification was ever
        missed (e.g. the process wasn't running when it arrived).

        Reads and parses the raw response as one block rather than
        line-by-line (see _read_raw_until_ok()/_parse_cmgl_raw()), so one
        malformed/binary message in the store can't desync parsing for
        every message after it in the same dump. Entries that still can't
        be parsed are logged and their SIM slot is deleted so they don't
        keep re-appearing and blocking every future sweep.

        `timeout` defaults to a larger window than a typical AT command
        (self.timeout) since a full store of many messages takes longer to
        transfer, especially at low baud rates — pass it explicitly to
        override.

        If the response never reaches a final OK within `timeout` (e.g. an
        unsolicited +CMTI for a newly-arrived message lands mid-dump and
        the modem stalls after it — this does happen), this does NOT raise
        or blow away what was read: it parses whatever raw text was
        captured and returns the entries that did come through intact.
        The backlog shrinks a bit more on each retry rather than the sweep
        failing outright every time it collides with live traffic."""
        if timeout is None:
            timeout = max(self.timeout * 4, 20)
        incomplete = False
        with self._lock:
            self._ser.reset_input_buffer()
            self._write_line('AT+CMGL="ALL"')
            try:
                raw = self._read_raw_until_ok(timeout)
            except SIM800LTimeoutError as e:
                raw = e.partial
                incomplete = True
                logger.warning(
                    "AT+CMGL=\"ALL\" didn't finish within the timeout — "
                    "processing the partial response and picking up the rest on the next sweep."
                )
        parsed, bad_indices = self._parse_cmgl_raw(raw, drop_last_index=incomplete)
        for idx in bad_indices:
            logger.warning(f"SMS index {idx} on SIM could not be parsed (corrupted/binary content) — deleting slot.")
            try:
                self.delete_message(idx)
            except SIM800LError as e:
                logger.error(f"Could not delete unparseable SMS index {idx}: {e}")
        return parsed

    def read_message(self, index):
        with self._lock:
            self._ser.reset_input_buffer()
            self._write_line(f"AT+CMGR={index}")
            try:
                raw = self._read_raw_until_ok(self.timeout)
            except SIM800LTimeoutError as e:
                logger.warning(f"AT+CMGR={index} timed out — treating as unreadable.")
                raw = e.partial
        parsed, _ = self._parse_cmgl_raw(raw, single_index=index, drop_last_index=True)
        return parsed[0] if parsed else None

    def delete_message(self, index):
        self.send_at(f"AT+CMGD={index}")

    def send_sms(self, number, text, timeout=None):
        """Sends a text-mode SMS to `number`. Requires text mode (set by
        AT+CMGF=1 in initialize()).

        AT+CMGS doesn't fit send_at()/_read_until()'s line-based model: the
        modem replies to 'AT+CMGS="<number>"' with a bare '>' prompt (not a
        \\r\\n-terminated line) and then waits for the message body,
        terminated by Ctrl+Z (0x1A) to send it or ESC (0x1B) to cancel.
        Only after that does it respond with the usual '+CMGS: <ref>' /
        'OK' (or 'ERROR') lines. So this sends the command, waits for the
        prompt with _wait_for_prompt(), writes the body + Ctrl+Z, then
        falls back to _read_until() for the final OK/ERROR — sending can
        take a few seconds longer than a typical AT command, hence the
        larger default timeout.
        """
        if not self.is_open:
            raise SIM800LError("Serial port is not open — call initialize() first")

        send_timeout = timeout or max(self.timeout * 3, 15)
        with self._lock:
            self._ser.reset_input_buffer()
            self._write_line(f'AT+CMGS="{number}"')
            self._wait_for_prompt(send_timeout)
            self._ser.write(text.encode("ascii", errors="ignore"))
            self._ser.write(bytes([0x1A]))  # Ctrl+Z — sends the message (ESC/0x1B would cancel)
            return self._read_until("OK", send_timeout)

    def _wait_for_prompt(self, timeout):
        """Waits for the bare '>' prompt SIM800 sends after AT+CMGS,
        before it will accept the SMS body. Needs its own read loop since
        _read_until() only recognizes complete \\r\\n-terminated lines and
        this prompt isn't one."""
        deadline = time.time() + timeout
        buf = ""
        while time.time() < deadline:
            waiting = self._ser.in_waiting
            chunk = self._ser.read(waiting or 1)
            if chunk:
                buf += chunk.decode(errors="ignore")
                if ">" in buf:
                    return
                if "ERROR" in buf:
                    raise SIM800LError(f"Modem returned error before SMS prompt: {buf.strip()}")
            else:
                time.sleep(0.05)
        raise SIM800LError(f"Timed out waiting for '>' prompt before AT+CMGS body — got: {buf!r}")

    @staticmethod
    def _parse_cmgl_raw(text, single_index=None, drop_last_index=False):
        """Extracts SMS entries from a raw (unsplit) AT+CMGL/AT+CMGR
        response. Anchors on '+CMGL:'/'+CMGR:' header matches and takes
        everything between one header and the next as that message's body
        — so a body that spans multiple lines doesn't get misread as a new
        header, and a header elsewhere in the dump that's corrupted just
        gets skipped rather than throwing off every entry that follows it.

        Returns (parsed_entries, bad_indices): bad_indices lists SMS index
        numbers seen in the raw text (via a looser index-only match) that
        couldn't be matched by the full header pattern — e.g. a sender or
        timestamp field containing raw control bytes the modem couldn't
        render as clean text — so the caller can free those SIM storage
        slots instead of hitting the same corrupt entries on every future
        sweep.

        `drop_last_index`: when the raw text came from a response that got
        cut off by a timeout, the highest-numbered index seen may simply
        not have finished arriving yet rather than being genuinely
        corrupt — pass True to exclude it from bad_indices so a message
        that's just running late doesn't get deleted before it had a fair
        chance to complete."""
        matches = list(_CMGL_ENTRY_RE.finditer(text))
        results = []
        for i, m in enumerate(matches):
            start = m.end()
            end = matches[i + 1].start() if i + 1 < len(matches) else len(text)
            body = re.sub(r'\r?\n\s*OK\s*\Z', '', text[start:end]).strip()
            idx = m.group(1) or single_index
            results.append({
                "index": int(idx) if idx is not None else None,
                "status": m.group(2),
                "sender": m.group(3),
                "timestamp": m.group(4),
                "body": body,
            })
        parsed_indices = {r["index"] for r in results if r["index"] is not None}
        all_seen_indices = {int(n) for n in _CMGL_INDEX_ONLY_RE.findall(text)}
        if drop_last_index and all_seen_indices:
            all_seen_indices.discard(max(all_seen_indices))
        bad_indices = sorted(all_seen_indices - parsed_indices)
        if bad_indices:
            logger.warning(f"Could not parse SMS header(s) for index(es): {bad_indices}")
        return results, bad_indices

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