"""
N510 serial server -> lead ion sensor
register 2 = concentration (x10), register 1 = temperature (x10), register 9 = range
"""
import time
from pymodbus.client import ModbusTcpClient
from pymodbus.framer import FramerType
 
N510_IP   = "192.168.55.200"
N510_PORT = 8899
SLAVE_ID  = 1
 
 
def read_regs(client, addr, count, slave_id):
    try:
        return client.read_holding_registers(address=addr, count=count, device_id=slave_id)
    except TypeError:
        return client.read_holding_registers(address=addr, count=count, slave=slave_id)
 
 
def main():
    client = ModbusTcpClient(
        host=N510_IP, port=N510_PORT,
        framer=FramerType.RTU, timeout=3,
    )
    if not client.connect():
        print("Connection failed"); return
    print(f"Connected {N510_IP}:{N510_PORT}\n")
 
    # Read the range once first (register 9)
    rr = read_regs(client, 9, 1, SLAVE_ID)
    if not rr.isError():
        print(f"Sensor range: {rr.registers[0]/10.0} ppm\n")
 
    try:
        while True:
            rr = read_regs(client, 0, 10, SLAVE_ID)
            if rr.isError():
                print(f"Read error: {rr}")
            else:
                regs = rr.registers
                temperature = regs[1] / 10.0
                lead_ppm    = regs[2] / 10.0
                ts = time.strftime("%H:%M:%S")
                print(f"[{ts}] Lead ion: {lead_ppm:>7.2f} ppm   Temperature: {temperature:>5.1f} C   "
                      f"Raw: {regs}")
            time.sleep(2)
    except KeyboardInterrupt:
        print("\nExit")
    finally:
        client.close()
        print("Closed")
 
 
if __name__ == "__main__":
    main()
