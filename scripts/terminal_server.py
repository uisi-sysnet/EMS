#!/usr/bin/env python3
"""
terminal_server.py — the bridge behind the dashboard's Terminal page
(Maintenance > Terminal, super admins only).

Browser (xterm.js) <-WebSocket /pty-> nginx -> 127.0.0.1:3001 (this) <-pty-> bash -l

Protocol (JSON messages, see resources/views/server/terminal.blade.php):
  browser -> {"type": "auth", "token": ..., "cols": N, "rows": N}   first, within 10 s
             {"type": "input", "data": "..."}  /  {"type": "resize", "cols": N, "rows": N}
  server  -> {"type": "ready"}  /  {"type": "data", "data": "..."}  /  {"type": "error", "message": "..."}

The token is issued by the dashboard (TerminalAuthController): base64url(JSON
{u, iat, exp}) + "." + hex HMAC-SHA256 with TERMINAL_SHARED_SECRET from
scripts/.env. Valid for 60 s and usable once. The shell runs as the user this
service runs as (ems-terminal.service, the EMS user). Listens on 127.0.0.1
only; nginx is the way in.

  python3 terminal_server.py            run the bridge
  python3 terminal_server.py --check    self-check of the token rules
"""
import asyncio
import base64
import codecs
import hashlib
import hmac
import json
import logging
import os
import signal
import struct
import sys
import time
from pathlib import Path

from dotenv import load_dotenv

SCRIPT_DIR = Path(__file__).resolve().parent
load_dotenv(dotenv_path=SCRIPT_DIR / ".env")

HOST = os.getenv("TERMINAL_BIND_HOST", "127.0.0.1")
PORT = int(os.getenv("TERMINAL_PORT", 3001))
SECRET = os.getenv("TERMINAL_SHARED_SECRET", "")
SHELL = os.getenv("TERMINAL_SHELL", "/bin/bash")
AUTH_TIMEOUT_SEC = 10

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
log = logging.getLogger("terminal_server")

_used_tokens = {}  # signature -> expiry; a token opens one session only


def check_token(token, secret, now=None):
    """Returns the username for a valid token, else raises ValueError."""
    now = int(now if now is not None else time.time())
    if not secret:
        raise ValueError("TERMINAL_SHARED_SECRET is not set on the server")
    try:
        payload_b64, signature = str(token).split(".", 1)
    except ValueError:
        raise ValueError("malformed token")
    expected = hmac.new(secret.encode(), payload_b64.encode(), hashlib.sha256).hexdigest()
    if not hmac.compare_digest(expected, signature):
        raise ValueError("invalid token signature")
    payload = json.loads(base64.urlsafe_b64decode(payload_b64 + "=" * (-len(payload_b64) % 4)))
    exp = int(payload.get("exp", 0))
    if exp < now:
        raise ValueError("token expired, reconnect")
    for sig, until in list(_used_tokens.items()):
        if until < now:
            del _used_tokens[sig]
    if signature in _used_tokens:
        raise ValueError("token already used, reconnect")
    _used_tokens[signature] = exp
    return str(payload.get("u") or "unknown")


def set_size(fd, cols, rows):
    import fcntl, termios  # Linux only; imported here so --check runs anywhere
    cols = max(1, min(int(cols or 80), 1000))
    rows = max(1, min(int(rows or 24), 1000))
    fcntl.ioctl(fd, termios.TIOCSWINSZ, struct.pack("HHHH", rows, cols, 0, 0))


async def session(ws):
    async def send(obj):
        await ws.send(json.dumps(obj))

    try:
        first = json.loads(await asyncio.wait_for(ws.recv(), AUTH_TIMEOUT_SEC))
        if first.get("type") != "auth":
            raise ValueError("expected auth first")
        user = check_token(first.get("token"), SECRET)
    except Exception as e:
        log.warning(f"Terminal login refused: {e}")
        await send({"type": "error", "message": f"Not authorised: {e}"})
        await ws.close()
        return

    import pty  # Linux only
    pid, fd = pty.fork()
    if pid == 0:  # child: the user's login shell
        home = os.path.expanduser("~")
        os.chdir(home if os.path.isdir(home) else "/")
        env = {k: v for k, v in os.environ.items() if k in ("PATH", "LANG", "LC_ALL", "USER", "LOGNAME", "HOME", "SHELL")}
        env.update(TERM="xterm-256color", SHELL=SHELL, HOME=home)
        os.execve(SHELL, [SHELL, "-l"], env)

    log.info(f"Terminal opened for dashboard user {user} (pid {pid})")
    set_size(fd, first.get("cols"), first.get("rows"))
    loop = asyncio.get_running_loop()
    output = asyncio.Queue()
    decoder = codecs.getincrementaldecoder("utf-8")(errors="replace")

    def on_output():
        try:
            chunk = os.read(fd, 65536)
        except OSError:
            chunk = b""
        output.put_nowait(decoder.decode(chunk) if chunk else None)
        if not chunk:
            loop.remove_reader(fd)

    loop.add_reader(fd, on_output)
    await send({"type": "ready"})

    async def pump_output():
        while True:
            text = await output.get()
            if text is None:  # shell exited
                await send({"type": "data", "data": "\r\n[session ended]\r\n"})
                await ws.close()
                return
            if text:
                await send({"type": "data", "data": text})

    pump = asyncio.create_task(pump_output())
    try:
        async for raw in ws:
            msg = json.loads(raw)
            if msg.get("type") == "input":
                os.write(fd, str(msg.get("data", "")).encode())
            elif msg.get("type") == "resize":
                set_size(fd, msg.get("cols"), msg.get("rows"))
    except Exception as e:
        log.info(f"Terminal connection closed: {e}")
    finally:
        pump.cancel()
        loop.remove_reader(fd)
        try:
            os.killpg(os.getpgid(pid), signal.SIGHUP)
        except ProcessLookupError:
            pass
        os.close(fd)
        await loop.run_in_executor(None, os.waitpid, pid, 0)
        log.info(f"Terminal closed for dashboard user {user} (pid {pid})")


async def main():
    import websockets

    if not SECRET:
        log.error("TERMINAL_SHARED_SECRET is not set in scripts/.env; every login will be refused.")
    async with websockets.serve(session, HOST, PORT, max_size=1_000_000):
        log.info(f"Terminal bridge listening on {HOST}:{PORT}")
        await asyncio.Future()


def _self_check():
    secret = "s3cret"
    b64 = lambda d: base64.urlsafe_b64encode(json.dumps(d).encode()).decode().rstrip("=")
    sign = lambda p: p + "." + hmac.new(secret.encode(), p.encode(), hashlib.sha256).hexdigest()
    good = sign(b64({"u": "admin", "iat": 100, "exp": 160}))
    assert check_token(good, secret, now=120) == "admin"
    for bad, why in [(good, "used twice"), (sign(b64({"u": "x", "exp": 110})), "expired"),
                     (good[:-4] + "0000", "bad signature"), ("garbage", "malformed")]:
        try:
            check_token(bad, secret, now=120)
            raise AssertionError(f"accepted a token that is {why}")
        except ValueError:
            pass
    try:
        check_token(sign(b64({"u": "a", "exp": 999})), "", now=120)
        raise AssertionError("accepted a token with no secret configured")
    except ValueError:
        pass
    print("terminal_server token checks OK")


if __name__ == "__main__":
    if "--check" in sys.argv:
        _self_check()
    else:
        asyncio.run(main())
