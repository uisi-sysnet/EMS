#!/usr/bin/env python3
"""
Synthetic Seismic Data Generator for MQTT
Compatible with seismic_mqtt.py ingestion service
"""

import json
import random
import time
import datetime
import math
import paho.mqtt.client as mqtt

# MQTT Configuration (matches seismic_mqtt.py)
MQTT_BROKER_HOST = "192.168.55.10"
MQTT_BROKER_PORT = 1883
MQTT_TIMEOUT_SEC = 60
MQTT_TOPIC = "seismic/stations/+/telemetry"
MQTT_USER = "mqtt_user_seismic"
MQTT_PASSWORD = "UisI_2026##"

# Simulated station data (matches database schema)
STATIONS = [
    {"id": "STN-001", "name": "North Monitoring Station", "lat": 14.654321, "lon": 121.056790, "elev": 42.5},
    {"id": "STN-002", "name": "South Monitoring Station", "lat": 14.523456, "lon": 120.987654, "elev": 38.2},
    {"id": "STN-003", "name": "East Monitoring Station", "lat": 14.789012, "lon": 121.123456, "elev": 56.8},
    {"id": "STN-004", "name": "West Monitoring Station", "lat": 14.612345, "lon": 120.945678, "elev": 29.1}
]

# Track cumulative values for each station
tracker = {
    station["id"]: {
        "displacement": {"x": 0.0, "y": 0.0, "z": 0.0},
        "peak_accel": {"x": 0.0, "y": 0.0, "z": 0.0},
        "peak_vel": {"x": 0.0, "y": 0.0, "z": 0.0},
        "pga": 0.0,  # Peak Ground Acceleration (overall)
        "pgv": 0.0,  # Peak Ground Velocity (overall)
        "pgd": 0.0,  # Peak Ground Displacement (overall)
        "peis": 0    # PEIS intensity scale (0-12)
    } for station in STATIONS
}

def calculate_peis(pga_g, pgv_ms, pgd_m):
    """
    Calculate PEIS (Philippine Earthquake Intensity Scale) based on PGA, PGV, and PGD.
    PEIS ranges from 0-12 (based on PHIVOLCS scale)
    """
    # Scaled intensity based on PGA
    if pga_g < 0.001:
        peis = 0  # Not felt
    elif pga_g < 0.002:
        peis = 1  # Scarcely perceptible
    elif pga_g < 0.005:
        peis = 2  # Slightly felt
    elif pga_g < 0.01:
        peis = 3  # Weak
    elif pga_g < 0.02:
        peis = 4  # Moderately felt
    elif pga_g < 0.04:
        peis = 5  # Strong
    elif pga_g < 0.08:
        peis = 6  # Very strong
    elif pga_g < 0.15:
        peis = 7  # Damaging
    elif pga_g < 0.25:
        peis = 8  # Very damaging
    elif pga_g < 0.40:
        peis = 9  # Destructive
    elif pga_g < 0.60:
        peis = 10  # Very destructive
    elif pga_g < 0.85:
        peis = 11  # Extremely destructive
    else:
        peis = 12  # Catastrophic
    
    # Adjust based on PGV for more accuracy (higher velocity = higher intensity)
    if pgv_ms > 0.5:
        peis = min(12, peis + 1)
    if pgv_ms > 1.0:
        peis = min(12, peis + 1)
    if pgv_ms > 2.0:
        peis = min(12, peis + 1)
    
    # Adjust based on PGD for long-period effects
    if pgd_m > 0.1:
        peis = min(12, peis + 1)
    if pgd_m > 0.3:
        peis = min(12, peis + 1)
    
    return peis

def generate_seismic_data(station):
    """Generate realistic seismic sensor data compatible with seismic_mqtt.py"""
    
    # Base noise level (micro-g)
    base_noise = 0.0002
    
    # Random earthquake event (5% probability)
    is_event = random.random() < 0.05
    
    if is_event:
        # Earthquake event - higher amplitude
        magnitude = random.uniform(0.5, 3.0)  # Richter scale-like
        amplification = 0.01 * magnitude
        x_accel = random.uniform(-amplification, amplification)
        y_accel = random.uniform(-amplification, amplification)
        z_accel = random.uniform(-amplification * 0.5, amplification * 0.5)
    else:
        # Normal background noise
        x_accel = random.gauss(0, base_noise)
        y_accel = random.gauss(0, base_noise)
        z_accel = random.gauss(0, base_noise * 0.5)
    
    # Add slight drift
    x_accel += 0.00005 * random.random()
    y_accel += 0.00005 * random.random()
    z_accel += 0.00003 * random.random()
    
    # Round acceleration to 4 decimal places
    x_accel = round(x_accel, 4)
    y_accel = round(y_accel, 4)
    z_accel = round(z_accel, 4)
    
    # Calculate PGA (Peak Ground Acceleration) - vector magnitude
    pga_current = round(math.sqrt(x_accel**2 + y_accel**2 + z_accel**2), 4)
    
    # Generate velocity data (derived from acceleration with slight variation)
    vel_x = round(x_accel * random.uniform(0.8, 1.2) + random.gauss(0, 0.0001), 4)
    vel_y = round(y_accel * random.uniform(0.8, 1.2) + random.gauss(0, 0.0001), 4)
    vel_z = round(z_accel * random.uniform(0.8, 1.2) + random.gauss(0, 0.0001), 4)
    
    # Calculate PGV (Peak Ground Velocity) - vector magnitude
    pgv_current = round(math.sqrt(vel_x**2 + vel_y**2 + vel_z**2), 4)
    
    # Calculate displacement (integration of velocity with damping)
    station_id = station["id"]
    
    # Update displacement with velocity (integration)
    tracker[station_id]["displacement"]["x"] += vel_x * 0.1
    tracker[station_id]["displacement"]["y"] += vel_y * 0.1
    tracker[station_id]["displacement"]["z"] += vel_z * 0.1
    
    # Apply damping to prevent unbounded drift
    damping_factor = 0.98
    tracker[station_id]["displacement"]["x"] *= damping_factor
    tracker[station_id]["displacement"]["y"] *= damping_factor
    tracker[station_id]["displacement"]["z"] *= damping_factor
    
    # Round displacement
    disp_x = round(tracker[station_id]["displacement"]["x"], 4)
    disp_y = round(tracker[station_id]["displacement"]["y"], 4)
    disp_z = round(tracker[station_id]["displacement"]["z"], 4)
    
    # Calculate PGD (Peak Ground Displacement) - vector magnitude
    pgd_current = round(math.sqrt(disp_x**2 + disp_y**2 + disp_z**2), 4)
    
    # Update peak values (keep maximum absolute values)
    tracker[station_id]["peak_accel"]["x"] = max(tracker[station_id]["peak_accel"]["x"], abs(x_accel))
    tracker[station_id]["peak_accel"]["y"] = max(tracker[station_id]["peak_accel"]["y"], abs(y_accel))
    tracker[station_id]["peak_accel"]["z"] = max(tracker[station_id]["peak_accel"]["z"], abs(z_accel))
    
    tracker[station_id]["peak_vel"]["x"] = max(tracker[station_id]["peak_vel"]["x"], abs(vel_x))
    tracker[station_id]["peak_vel"]["y"] = max(tracker[station_id]["peak_vel"]["y"], abs(vel_y))
    tracker[station_id]["peak_vel"]["z"] = max(tracker[station_id]["peak_vel"]["z"], abs(vel_z))
    
    # Update overall PGA, PGV, PGD
    tracker[station_id]["pga"] = max(tracker[station_id]["pga"], pga_current)
    tracker[station_id]["pgv"] = max(tracker[station_id]["pgv"], pgv_current)
    tracker[station_id]["pgd"] = max(tracker[station_id]["pgd"], pgd_current)
    
    # Calculate PEIS based on current values
    peis = calculate_peis(pga_current, pgv_current, pgd_current)
    tracker[station_id]["peis"] = max(tracker[station_id]["peis"], peis)
    
    # Apply decay to peak values (simulate fading over time)
    peak_decay = 0.999
    tracker[station_id]["peak_accel"]["x"] *= peak_decay
    tracker[station_id]["peak_accel"]["y"] *= peak_decay
    tracker[station_id]["peak_accel"]["z"] *= peak_decay
    tracker[station_id]["peak_vel"]["x"] *= peak_decay
    tracker[station_id]["peak_vel"]["y"] *= peak_decay
    tracker[station_id]["peak_vel"]["z"] *= peak_decay
    tracker[station_id]["pga"] *= peak_decay
    tracker[station_id]["pgv"] *= peak_decay
    tracker[station_id]["pgd"] *= peak_decay
    
    # Create timestamp in the exact format expected by seismic_mqtt.py
    # ISO format with timezone offset
    timestamp = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=8))).isoformat()
    
    # Construct the data packet in the exact format expected by insert_station_metrics()
    data = {
        "station_id": station["id"],
        "station_name": station["name"],
        "timestamp": timestamp,
        "location": {
            "latitude": station["lat"],
            "longitude": station["lon"],
            "elevation_m": station["elev"]
        },
        "measurements": {
            "acceleration": {
                "x": x_accel,
                "y": y_accel,
                "z": z_accel
                # "unit" is NOT needed - database schema doesn't store it
            },
            "velocity": {
                "x": vel_x,
                "y": vel_y,
                "z": vel_z
                # "unit" is NOT needed - database schema doesn't store it
            },
            "displacement": {
                "x": disp_x,
                "y": disp_y,
                "z": disp_z
                # "unit" is NOT needed - database schema doesn't store it
            }
        },
        # Top-level PGA and PEIS as expected by insert_station_metrics()
        "pga": round(tracker[station_id]["pga"], 4),
        "peis": tracker[station_id]["peis"]
    }
    
    return data

def on_connect(client, userdata, flags, rc, properties=None):
    """Callback for when the client connects to the broker"""
    if rc == 0:
        print(f"✓ Connected to MQTT broker at {MQTT_BROKER_HOST}:{MQTT_BROKER_PORT}")
    else:
        print(f"✗ Failed to connect, return code {rc}")

def on_publish(client, userdata, mid, reason_code=None, properties=None):
    """Callback for when a message is published"""
    print(f"✓ Message {mid} published successfully")

def main():
    """Main loop - generates and publishes synthetic seismic data"""
    
    print("\n" + "="*80)
    print("SEISMIC DATA GENERATOR - Synthetic Seismic Telemetry")
    print("="*80)
    print(f"MQTT Broker: {MQTT_BROKER_HOST}:{MQTT_BROKER_PORT}")
    print(f"Topic: {MQTT_TOPIC}")
    print(f"Stations: {', '.join([s['id'] for s in STATIONS])}")
    print("Press Ctrl+C to stop\n")
    
    # Create MQTT client with VERSION2 API
    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
    client.on_connect = on_connect
    client.on_publish = on_publish
    
    # Set authentication
    if MQTT_USER and MQTT_PASSWORD:
        client.username_pw_set(MQTT_USER, MQTT_PASSWORD)
    
    # Connect to broker
    try:
        client.connect(MQTT_BROKER_HOST, MQTT_BROKER_PORT, MQTT_TIMEOUT_SEC)
        client.loop_start()
    except Exception as e:
        print(f"✗ Failed to connect: {e}")
        return
    
    message_count = 0
    
    try:
        while True:
            # Send data for each station
            for station in STATIONS:
                # Generate synthetic data
                data = generate_seismic_data(station)
                
                # Convert to JSON
                payload = json.dumps(data)
                
                # Replace + wildcard with specific station ID in topic
                topic = MQTT_TOPIC.replace("+", station["id"])
                
                # Publish to MQTT
                result = client.publish(topic, payload, qos=1)
                
                if result.rc == mqtt.MQTT_ERR_SUCCESS:
                    message_count += 1
                    # Print summary with key parameters
                    measurements = data["measurements"]
                    accel = measurements["acceleration"]
                    vel = measurements["velocity"]
                    disp = measurements["displacement"]
                    
                    print(f"📤 [{message_count:4d}] {station['id']:8s} | "
                          f"Accel: ({accel['x']:6.4f}, {accel['y']:6.4f}, {accel['z']:6.4f})g | "
                          f"PGA: {data['pga']:.4f}g | "
                          f"PEIS: {data['peis']:2d} | "
                          f"Event: {'🚨' if data['peis'] >= 5 else '✓'}")
                else:
                    print(f"✗ Failed to publish message for {station['id']}")
                
                # Slight delay between stations
                time.sleep(0.2)
            
            # Wait before next cycle
            time.sleep(1)
            
    except KeyboardInterrupt:
        print("\n\n⚠️ Stopping data transmission...")
    finally:
        client.loop_stop()
        client.disconnect()
        print("✓ Disconnected from MQTT broker")

if __name__ == "__main__":
    main()