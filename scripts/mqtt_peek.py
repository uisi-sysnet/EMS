#!/usr/bin/env python3
"""
mqtt_peek.py — listen to the MQTT broker for a few seconds and print what
arrived, as one JSON object on stdout. Used by the dashboard's MQTT page
(Settings > MQTT > Live MQTT Messages) to check that data is reaching the
broker. Read-only: it subscribes and never publishes.

Uses the same broker settings as seismic_mqtt.py (scripts/.env:
MQTT_BROKER_HOST, MQTT_BROKER_PORT, MQTT_USER, MQTT_PASSWORD, MQTT_TOPIC).
The password is never printed.

  python3 mqtt_peek.py [--topic 'seismic/#'] [--seconds 10] [--max 100]
"""
import argparse
import json
import os
import threading
import time
from datetime import datetime, timezone
from pathlib import Path

import paho.mqtt.client as mqtt
from dotenv import load_dotenv

SCRIPT_DIR = Path(__file__).resolve().parent
load_dotenv(dotenv_path=SCRIPT_DIR / ".env")

HOST = os.getenv("MQTT_BROKER_HOST", "localhost")
PORT = int(os.getenv("MQTT_BROKER_PORT", 1883))
USER = os.getenv("MQTT_USER")
PASSWORD = os.getenv("MQTT_PASSWORD")
DEFAULT_TOPIC = os.getenv("MQTT_TOPIC", "#")

MAX_PAYLOAD_CHARS = 4000


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--topic", default=DEFAULT_TOPIC)
    parser.add_argument("--seconds", type=float, default=10)
    parser.add_argument("--max", type=int, default=100)
    args = parser.parse_args()
    seconds = max(1.0, min(args.seconds, 60.0))
    limit = max(1, min(args.max, 500))

    result = {
        "ok": False,
        "broker": f"{HOST}:{PORT}",
        "topic": args.topic,
        "seconds": seconds,
        "connected": False,
        "messages": [],
        "truncated": False,
        "error": None,
    }
    connected = threading.Event()
    done = threading.Event()

    try:
        client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, client_id=f"ems-dashboard-peek-{os.getpid()}")
    except AttributeError:  # paho-mqtt 1.x
        client = mqtt.Client(client_id=f"ems-dashboard-peek-{os.getpid()}")
    if USER and PASSWORD:
        client.username_pw_set(USER, PASSWORD)

    def on_connect(client, userdata, flags, rc, properties=None):
        code = getattr(rc, "value", rc)
        if code == 0:
            result["connected"] = True
            client.subscribe(args.topic)
        else:
            result["error"] = f"Broker refused the connection: {rc} (check MQTT_USER / MQTT_PASSWORD)."
            done.set()
        connected.set()

    def on_message(client, userdata, msg):
        if len(result["messages"]) >= limit:
            result["truncated"] = True
            done.set()
            return
        text = msg.payload.decode("utf-8", errors="replace")
        result["messages"].append({
            "topic": msg.topic,
            "received_at": datetime.now(timezone.utc).isoformat(),
            "qos": msg.qos,
            "retained": bool(msg.retain),
            "bytes": len(msg.payload),
            "payload": text[:MAX_PAYLOAD_CHARS],
            "payload_truncated": len(text) > MAX_PAYLOAD_CHARS,
        })

    client.on_connect = on_connect
    client.on_message = on_message

    try:
        client.connect(HOST, PORT, keepalive=30)
    except Exception as e:  # unreachable host, refused port, DNS...
        result["error"] = f"Could not connect to the broker at {HOST}:{PORT}: {e}"
        print(json.dumps(result))
        return

    client.loop_start()
    try:
        if not connected.wait(timeout=10):
            result["error"] = f"No answer from the broker at {HOST}:{PORT} within 10 seconds."
        elif result["connected"]:
            done.wait(timeout=seconds)
    finally:
        client.loop_stop()
        try:
            client.disconnect()
        except Exception:
            pass

    result["ok"] = result["connected"] and result["error"] is None
    print(json.dumps(result))


if __name__ == "__main__":
    main()
