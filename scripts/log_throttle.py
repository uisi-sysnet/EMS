"""
log_throttle.py — log a repeating problem at most once per interval.

A station with a dead clock, a device sending bad payloads, or a stale API
fallback source repeats the same problem on every reading or request. Logged
each time, that floods service_logs (and, for errors, the dashboard's
notification bell). Use:

    from log_throttle import throttled
    if throttled(f"drift:{mn}", minutes=30):
        logger.warning(...)

The first call for a key returns True, then False until the interval has
passed. State is per process, which is enough: each service logs its own.
"""
import threading
import time

_last = {}
_lock = threading.Lock()


def throttled(key: str, minutes: float = 30) -> bool:
    now = time.monotonic()
    with _lock:
        last = _last.get(key)
        if last is not None and now - last < minutes * 60:
            return False
        _last[key] = now
        return True


if __name__ == "__main__":  # self-check: python3 log_throttle.py
    assert throttled("a", minutes=0.001) is True
    assert throttled("a", minutes=0.001) is False
    assert throttled("b", minutes=0.001) is True
    time.sleep(0.07)
    assert throttled("a", minutes=0.001) is True
    print("log_throttle OK")
