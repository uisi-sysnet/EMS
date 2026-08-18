"""
Active TCP listener for a device in TCP CLIENT mode, speaking plain Modbus
RTU frames (the device is in transparent mode -- this script builds and
parses the actual RTU frames itself, CRC included).

The PC acts as the TCP SERVER; the device dials in. Once connected, this
sends a Modbus RTU read-holding-registers query (function 0x03) on a timer
and prints the parsed response.
"""

import socket
import time
from datetime import datetime

LISTEN_HOST = "0.0.0.0"
LISTEN_PORT = 1935          # match the device's "Remote Port Number"

SLAVE_ID      = 0x01        # transmitter address (factory default 0x01)
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


def build_query(slave_id: int, start_addr: int, count: int) -> bytes:
    body = bytes([slave_id, 0x03]) + start_addr.to_bytes(2, "big") + count.to_bytes(2, "big")
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


def handle_client(conn, addr):
    print(f"[{timestamp()}] Connected: {addr[0]}:{addr[1]}")
    try:
        while True:
            query = build_query(SLAVE_ID, POLL_START, POLL_COUNT)
            conn.sendall(query)
            print(f"[{timestamp()}] TX: {query.hex(' ')}")

            regs = read_and_parse(conn, SLAVE_ID)
            print(f"[{timestamp()}] RX regs: {regs}")

            if POLL_START == 0x0001 and POLL_COUNT == 2:
                temp = regs[0] / 10.0
                lead = regs[1] / 10.0
                print(f"    Water temp: {temp:>5.1f} C   Lead ion: {lead:>6.1f} ppm\n")

            time.sleep(POLL_INTERVAL)
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