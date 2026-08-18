"""
Combines the listener and write-register scripts: on each device connection
this first (optionally) reprograms the slave ID and/or baud rate, then falls
straight into the normal read-holding-registers poll loop using whatever
address is current after the change.

The PC acts as the TCP SERVER; the device (DR134 gateway) dials in as the
TCP CLIENT, same as before. All frames are plain Modbus RTU over that TCP
connection.

NOTE ON BAUD RATE:
Writing register 0x0101 changes the SENSOR's own serial baud rate right
away. It does NOT touch the DR134 gateway's own Port Parameter baud
setting, which lives on the DR134's web page. Until those two match again,
the gateway physically can't talk to the sensor, so no read can succeed
over this connection no matter what this script does. So: if NEW_BAUD_RATE
is set, the script sends the write, prints a reminder to go update the
DR134 page, and stops -- it deliberately does NOT try to poll afterward.
Once you've matched the DR134 setting to the new baud, set NEW_BAUD_RATE
back to None and rerun; it will pick up polling on NEW_SLAVE_ID from there.

NOTE ON RERUNS:
Once the device has been reprogrammed to NEW_SLAVE_ID, it will keep
answering at that address on every future connection. If you rerun this
script with SLAVE_ID still pointed at the OLD address, the write step
will get no response. Update SLAVE_ID to match before rerunning, or set
NEW_SLAVE_ID to None to skip straight to polling.
"""

import socket
import time
from datetime import datetime

LISTEN_HOST = "0.0.0.0"
LISTEN_PORT = 1935          # match the device's "Remote Port Number"

SLAVE_ID      = 0x01        # device's CURRENT address, before any change
NEW_SLAVE_ID  = 2           # register 0x0100, valid range 0-252. None = skip.
NEW_BAUD_RATE = None        # register 0x0101, must be 2400/4800/9600. None = skip.

POLL_START    = 0x0001      # 0x0001 = water temp, 0x0002 = lead ion
POLL_COUNT    = 2           # reading 2 registers from POLL_START covers both
POLL_INTERVAL = 2.0         # seconds between queries


def timestamp():
    return datetime.now().strftime("%H:%M:%S.%f")[:-3]


def modbus_crc16(data: bytes) -> bytes:
    """Standard Modbus RTU CRC16 (poly 0xA001). Returned low byte first."""
    crc = 0xFFFF
    for byte in data:
        crc ^= byte
        for _ in range(8):
            if crc & 1:
                crc >>= 1
                crc ^= 0xA001
            else:
                crc >>= 1
    return bytes([crc & 0xFF, (crc >> 8) & 0xFF])


def build_read_query(slave_id: int, start_addr: int, count: int) -> bytes:
    body = bytes([slave_id, 0x03]) + start_addr.to_bytes(2, "big") + count.to_bytes(2, "big")
    return body + modbus_crc16(body)


def build_write_single(slave_id: int, reg_addr: int, value: int) -> bytes:
    if not 0 <= value <= 0xFFFF:
        raise ValueError(f"{value} doesn't fit in a 16-bit register (max 65535)")
    body = bytes([slave_id, 0x06]) + reg_addr.to_bytes(2, "big") + value.to_bytes(2, "big")
    return body + modbus_crc16(body)


def recv_exact(conn, n, timeout=3.0) -> bytes:
    conn.settimeout(timeout)
    buf = b""
    while len(buf) < n:
        chunk = conn.recv(n - len(buf))
        if not chunk:
            raise ConnectionError("connection closed while reading response")
        buf += chunk
    return buf


def write_register(conn, slave_id: int, reg_addr: int, value: int, label: str):
    query = build_write_single(slave_id, reg_addr, value)
    conn.sendall(query)
    print(f"[{timestamp()}] TX ({label}): {query.hex(' ')}")

    header = recv_exact(conn, 2)   # addr, func
    if header[1] & 0x80:           # exception response
        tail = recv_exact(conn, 3)  # exception_code + crc(2)
        frame = header + tail
        raise ValueError(f"device rejected write ({label}): exception "
                          f"code {tail[0]:#04x} raw={frame.hex(' ')}")

    tail = recv_exact(conn, 6)     # reg_addr(2) + value(2) + crc(2)
    frame = header + tail
    print(f"[{timestamp()}] RX ({label}): {frame.hex(' ')}")

    if frame != query:
        print("    WARNING: response didn't echo the request - verify manually\n")
    else:
        print(f"    Confirmed: {label} write accepted\n")


def read_and_parse(conn, expected_slave: int):
    """Reads one Modbus RTU response frame off the socket and returns register values."""
    header = recv_exact(conn, 3)          # addr, func, (byte_count OR exception code)
    addr, func = header[0], header[1]

    if func & 0x80:  # exception response: addr, func, code, crc_lo, crc_hi (5 bytes total)
        tail = recv_exact(conn, 2)
        frame = header + tail
        raise ValueError(f"device returned exception: func={func:#04x} code={header[2]:#04x} raw={frame.hex(' ')}")

    byte_count = header[2]
    tail = recv_exact(conn, byte_count + 2)
    frame = header + tail

    data = frame[3:3 + byte_count]
    crc_recv = frame[3 + byte_count:3 + byte_count + 2]
    crc_calc = modbus_crc16(frame[:3 + byte_count])
    if crc_recv != crc_calc:
        raise ValueError(f"CRC mismatch: got {crc_recv.hex()} expected {crc_calc.hex()} raw={frame.hex(' ')}")
    if addr != expected_slave:
        raise ValueError(f"unexpected slave address {addr:#04x} raw={frame.hex(' ')}")

    return [int.from_bytes(data[i:i + 2], "big") for i in range(0, len(data), 2)]


def poll_loop(conn, slave_id: int):
    print(f"[{timestamp()}] Polling slave {slave_id} every {POLL_INTERVAL}s (Ctrl+C to stop)\n")
    while True:
        query = build_read_query(slave_id, POLL_START, POLL_COUNT)
        conn.sendall(query)
        print(f"[{timestamp()}] TX: {query.hex(' ')}")

        regs = read_and_parse(conn, slave_id)
        print(f"[{timestamp()}] RX regs: {regs}")

        if POLL_START == 0x0001 and POLL_COUNT == 2:
            temp = regs[0] / 10.0
            lead = regs[1] / 10.0
            print(f"    Water temp: {temp:>5.1f} C   Lead ion: {lead:>6.1f} ppm\n")

        time.sleep(POLL_INTERVAL)


def handle_client(conn, addr):
    print(f"[{timestamp()}] Connected: {addr[0]}:{addr[1]}")
    current_addr = SLAVE_ID
    try:
        if NEW_SLAVE_ID is not None:
            write_register(conn, current_addr, 0x0100, NEW_SLAVE_ID, "device address")
            current_addr = NEW_SLAVE_ID   # subsequent commands must use the new address
            time.sleep(0.5)

        if NEW_BAUD_RATE is not None:
            if NEW_BAUD_RATE not in (2400, 4800, 9600):
                print(f"Refusing to send: {NEW_BAUD_RATE} isn't a supported baud rate "
                      f"for this device (must be 2400, 4800, or 9600).")
            else:
                write_register(conn, current_addr, 0x0101, NEW_BAUD_RATE, "baud rate")
                print("Baud rate changed on the device - it will stop responding over "
                      "this link until the DR134's own Port Parameter baud rate is "
                      "updated to match. Update that on the DR134 web page, then set "
                      "NEW_BAUD_RATE back to None and rerun this script to resume polling.")
                return  # link is dead until the gateway side is reconfigured manually

        poll_loop(conn, current_addr)

    except (ConnectionError, socket.timeout) as e:
        print(f"[{timestamp()}] Disconnected/timeout: {e}")
    except ValueError as e:
        print(f"[{timestamp()}] {e}")
    finally:
        conn.close()


def main():
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.bind((LISTEN_HOST, LISTEN_PORT))
    server.listen(5)
    print(f"Listening on {LISTEN_HOST}:{LISTEN_PORT} - waiting for the device to connect...")
    print("Press Ctrl+C to stop.\n")
    try:
        while True:
            conn, addr = server.accept()
            handle_client(conn, addr)   # one device at a time; goes back to accept() when it drops
    except KeyboardInterrupt:
        print("\nShutting down.")
    finally:
        server.close()


if __name__ == "__main__":
    main()