#!/usr/bin/env python3
"""
db_logging.py
Drop-in logging.Handler that mirrors log records into a shared Postgres
table (`service_logs` in the air_quality database by default), in
addition to whatever console handler a script already has.

Why this exists / design notes:
- On Raspberry Pi OS, the app normally logs to on-disk files. Every log
  line is a small write to the SD card, and SD cards wear out faster than
  spinning disks or SSDs under that kind of constant small-write load.
  Moving the durable copy of the logs into Postgres (which is already
  running, already has proper write batching/WAL, and is easy to query)
  removes that steady drip of file writes. Console output is kept and is
  captured by systemd's journal (journald handles rotation/size limits on
  its own), so nothing is lost — it's just no longer this app's job to
  manage log files.
- Runs on its own background thread with a bounded queue, so a slow or
  unreachable database can never block the calling service's real work
  (HJ212 parsing, MQTT callbacks, API request handling).
- Uses its OWN direct database connection, intentionally NOT the
  application's connection pool, so log delivery can't starve or
  deadlock against normal query traffic, and keeps working even if
  application code is busy handling pool errors of its own.
- Best-effort: if the database is unreachable, records are dropped
  (oldest-first once the queue is full) rather than blocking or crashing
  the service. Console output remains the fallback source of truth.
- Never raises out of emit() — required by the logging.Handler contract.
"""

import atexit
import logging
import queue
import threading
import time

try:
    import psycopg  # psycopg3 (used by seismic_mqtt.py)
    _PSYCOPG_MAJOR = 3
except ImportError:  # pragma: no cover
    import psycopg2 as psycopg  # psycopg2 (used by air_quality_ingest.py / api_server.py)
    _PSYCOPG_MAJOR = 2


class PostgresLogHandler(logging.Handler):
    def __init__(self, dsn, service_name, table="service_logs", max_queue=2000, reconnect_backoff_sec=10):
        super().__init__()
        self._dsn = dsn
        self._service_name = service_name
        self._table = table
        self._queue = queue.Queue(maxsize=max_queue)
        self._conn = None
        self._reconnect_backoff = reconnect_backoff_sec
        self._stop_event = threading.Event()
        self._thread = threading.Thread(target=self._worker, name="PostgresLogHandler", daemon=True)
        self._thread.start()
        atexit.register(self.close)

    def emit(self, record):
        try:
            msg = self.format(record)
            item = (record.created, record.levelname, record.name, record.threadName, msg)
            try:
                self._queue.put_nowait(item)
            except queue.Full:
                # Drop the oldest queued record to make room rather than
                # block the caller — losing a log line beats stalling HJ212
                # parsing / MQTT callbacks / API responses.
                try:
                    self._queue.get_nowait()
                except queue.Empty:
                    pass
                try:
                    self._queue.put_nowait(item)
                except queue.Full:
                    pass
        except Exception:
            self.handleError(record)

    def _connect(self):
        conn = psycopg.connect(self._dsn)
        conn.autocommit = True
        cur = conn.cursor()
        cur.execute(f"""
            CREATE TABLE IF NOT EXISTS {self._table} (
                created_at TIMESTAMPTZ NOT NULL,
                service VARCHAR(50) NOT NULL,
                level VARCHAR(10) NOT NULL,
                logger_name VARCHAR(100),
                thread_name VARCHAR(100),
                message TEXT
            );
        """)
        try:
            cur.execute(
                f"SELECT create_hypertable('{self._table}', 'created_at', "
                f"if_not_exists => TRUE, migrate_data => TRUE);"
            )
        except Exception:
            # TimescaleDB extension not available/ready yet — fall back to
            # a plain table. Logging still works, just without hypertable
            # chunking/compression.
            pass
        cur.execute(
            f"CREATE INDEX IF NOT EXISTS idx_{self._table}_service_time "
            f"ON {self._table}(service, created_at DESC);"
        )
        cur.close()
        return conn

    def _worker(self):
        while not self._stop_event.is_set():
            try:
                item = self._queue.get(timeout=1)
            except queue.Empty:
                continue
            batch = [item]
            # Drain a bit more so a burst of log lines is one INSERT, not N
            # — fewer round trips matters on a Pi's more limited CPU/network.
            while len(batch) < 200:
                try:
                    batch.append(self._queue.get_nowait())
                except queue.Empty:
                    break
            self._flush(batch)

    def _flush(self, batch):
        for attempt in range(2):
            try:
                if self._conn is None or getattr(self._conn, "closed", True):
                    self._conn = self._connect()
                cur = self._conn.cursor()
                cur.executemany(
                    f"INSERT INTO {self._table} "
                    f"(created_at, service, level, logger_name, thread_name, message) "
                    f"VALUES (to_timestamp(%s), %s, %s, %s, %s, %s)",
                    [(ts, self._service_name, level, name, thread, msg) for ts, level, name, thread, msg in batch],
                )
                cur.close()
                return
            except Exception:
                try:
                    if self._conn:
                        self._conn.close()
                except Exception:
                    pass
                self._conn = None
                if attempt == 0:
                    time.sleep(self._reconnect_backoff)
        # Both attempts failed — give up on this batch. Console output
        # (captured by journald under systemd) still has it; this handler
        # is a best-effort mirror, not the sole system of record.

    def close(self):
        self._stop_event.set()
        if self._conn:
            try:
                self._conn.close()
            except Exception:
                pass
        super().close()


def attach_db_logging(logger, dsn, service_name, table="service_logs", level=logging.INFO):
    """Attaches a PostgresLogHandler to `logger`. Safe to call even if the
    database/table doesn't exist yet — the handler connects lazily on its
    background thread, creates the table itself, and just drops records
    until the database is reachable."""
    handler = PostgresLogHandler(dsn, service_name, table=table)
    handler.setLevel(level)
    logger.addHandler(handler)
    return handler
