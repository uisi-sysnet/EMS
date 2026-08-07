@include('layouts.header')
@include('layouts.topbar')

<style>
    .thin-scrollbar::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    .thin-scrollbar::-webkit-scrollbar-track {
        background: #1A1A1A;
        border-radius: 10px;
    }
    .thin-scrollbar::-webkit-scrollbar-thumb {
        background: #4B5563;
        border-radius: 10px;
    }
    .thin-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #6B7280;
    }
    .thin-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: #4B5563 #1A1A1A;
    }

    .docs-code {
        @apply bg-surface-900 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono;
    }
</style>

<main class="pt-20 pb-6 px-4 sm:px-6 max-w-6xl mx-auto w-full">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden">

        {{-- Header --}}
        <div class="px-5 sm:px-8 py-5 border-b border-border-800 bg-surface-800">
            <h1 class="text-xl sm:text-2xl font-bold text-text-100 uppercase tracking-tight">
                EMS IoT Gateway — Raspberry Pi Deployment Beta V1.0
            </h1>
            <p class="text-sm text-text-400 mt-1">
                Always-on services on Raspberry Pi OS (64-bit recommended)
            </p>
        </div>

        <div class="p-5 sm:p-8 space-y-10 text-text-300 leading-relaxed">

            <p class="text-base text-text-200">
                Three always-on services on one Raspberry Pi (Raspberry Pi OS / Raspbian, 64-bit strongly recommended):
            </p>

            {{-- Services table --}}
            <div class="overflow-x-auto rounded-xl border border-border-700">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface-800">
                        <tr class="border-b border-border-700">
                            <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider">Service</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider">File</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider">Ingests via</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider">Database</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-800 bg-surface-900">
                        <tr class="hover:bg-surface-800/60 transition">
                            <td class="px-4 py-3 text-text-100 font-medium">Air Quality Ingestion</td>
                            <td class="px-4 py-3 font-mono text-radar-400 text-xs">air_quality_ingest.py</td>
                            <td class="px-4 py-3">TCP/HJ212 from AQ sensors</td>
                            <td class="px-4 py-3 font-mono text-xs text-text-400">air_quality (Timescale)</td>
                        </tr>
                        <tr class="hover:bg-surface-800/60 transition">
                            <td class="px-4 py-3 text-text-100 font-medium">Seismic Ingestion</td>
                            <td class="px-4 py-3 font-mono text-radar-400 text-xs">seismic_mqtt.py</td>
                            <td class="px-4 py-3">MQTT <strong class="text-text-100">and</strong> SMS (SIM800L)</td>
                            <td class="px-4 py-3 font-mono text-xs text-text-400">seismic_sensor_data (Timescale)</td>
                        </tr>
                        <tr class="hover:bg-surface-800/60 transition">
                            <td class="px-4 py-3 text-text-100 font-medium">Monitoring API</td>
                            <td class="px-4 py-3 font-mono text-radar-400 text-xs">api_server.py</td>
                            <td class="px-4 py-3">HTTP (FastAPI)</td>
                            <td class="px-4 py-3 text-text-400">reads both databases</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p>
                All three log into a shared
                <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">service_logs</code>
                table in
                <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">air_quality</code>
                instead of local files (better for SD card longevity), and are supervised by systemd via
                <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">ems.target</code>
                so they start on boot and restart on failure.
            </p>

            {{-- Files in this package --}}
            <section>
                <h2 class="text-lg font-semibold text-text-100 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <span class="w-1 h-5 bg-radar-500 rounded-full"></span>
                    Files in this package
                </h2>
                <pre class="bg-background-950 border border-border-700 text-text-300 p-4 rounded-xl overflow-x-auto text-xs sm:text-sm leading-relaxed font-mono">air_quality_ingest.py     Air quality TCP/HJ212 ingestion service
seismic_mqtt.py           Seismic ingestion — MQTT + SMS (SIM800L)
sim800l.py                AT-command driver for the SIM800L modem
api_server.py             FastAPI monitoring/read API
db_logging.py             Shared Postgres logging handler (all 3 services)
import_stations.py        CLI: (re-)apply stations.json to the database
deploy.sh                 One-time OS/DB/broker setup (run with sudo)
install_services.sh       Installs + starts the systemd units (run with sudo)
ems.target                systemd target grouping all 3 services
ems-air-quality_service.template
ems-seismic_service.template
ems-api_service.template  systemd unit templates (rendered by install_services.sh)
requirements.txt          Python dependencies
_env                      Environment template — rename to .env and fill in
stations.json             Air-quality station registry (one-time DB import)</pre>
            </section>

            {{-- Deploy from scratch --}}
            <section>
                <h2 class="text-lg font-semibold text-text-100 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <span class="w-1 h-5 bg-radar-500 rounded-full"></span>
                    Deploy from scratch
                </h2>
                <ol class="list-decimal list-inside space-y-3 text-text-300">
                    <li>Copy this whole folder onto the Pi (e.g. <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">/home/pi/ems/</code>).</li>
                    <li><code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">mv _env .env</code> and fill in real passwords/API keys.</li>
                    <li>
                        <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">sudo ./deploy.sh</code>
                        <ul class="list-disc list-inside ml-6 mt-2 space-y-1 text-sm text-text-400">
                            <li>Installs Python, PostgreSQL 16, TimescaleDB, Mosquitto, ntpsec</li>
                            <li>Creates DB roles, configures Mosquitto auth, installs Python deps</li>
                            <li>Detects Raspberry Pi OS vs Ubuntu and adjusts (see below)</li>
                            <li>Enables the Pi's UART for the SIM800L and disables the serial console</li>
                        </ul>
                    </li>
                    <li><strong class="text-text-100">Reboot</strong> (<code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">sudo reboot</code>) — required for the UART change to take effect.</li>
                    <li>
                        <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">sudo ./install_services.sh</code>
                        <ul class="list-disc list-inside ml-6 mt-2 space-y-1 text-sm text-text-400">
                            <li>Renders the systemd units, enables + starts <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">ems.target</code></li>
                        </ul>
                    </li>
                    <li>
                        Check it's alive:
                        <pre class="bg-background-950 border border-border-700 text-text-300 p-3 rounded-xl overflow-x-auto text-xs sm:text-sm mt-2 font-mono">sudo systemctl status ems.target
sudo journalctl -u ems-seismic.service -f</pre>
                    </li>
                </ol>
            </section>

            {{-- Raspberry Pi–specific behavior --}}
            <section>
                <h2 class="text-lg font-semibold text-text-100 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <span class="w-1 h-5 bg-radar-500 rounded-full"></span>
                    Raspberry Pi–specific behavior in deploy.sh
                </h2>
                <ul class="list-disc list-inside space-y-2 text-text-300">
                    <li>Detects CPU architecture; <strong class="text-munti-yellow-400">warns clearly if you're on 32-bit Raspberry Pi OS (armhf)</strong> — TimescaleDB has no official armhf packages, so 64-bit Raspberry Pi OS is required for the seismic/AQ hypertables to work.</li>
                    <li>Uses <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">--no-install-recommends</code> everywhere to keep the SD card footprint down.</li>
                    <li>Installs <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">build-essential</code> / <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">libpq-dev</code> / <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">python3-dev</code> as a fallback in case a Python package has no prebuilt ARM wheel on piwheels.org.</li>
                    <li>Warns if RAM + swap look too small for package builds, with the <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">dphys-swapfile</code> fix.</li>
                    <li>Configures the Pi's UART (<code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">raspi-config nonint do_serial_cons/do_serial_hw</code>) so the SIM800L can use it for AT commands instead of a login console.</li>
                    <li>Systemd unit templates set <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">PYTHONUNBUFFERED=1</code> and include a commented-out <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">MemoryMax=</code> you can enable to cap RAM per service on a constrained Pi.</li>
                </ul>
            </section>

            {{-- Station registry --}}
            <section>
                <h2 class="text-lg font-semibold text-text-100 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <span class="w-1 h-5 bg-radar-500 rounded-full"></span>
                    Station registry (air quality)
                </h2>
                <p class="mb-4">
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">air_quality_ingest.py</code>
                    reads its station list from the database, not from
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">stations.json</code>
                    directly. On first run, if the
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">stations</code>
                    table is empty and
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">stations.json</code>
                    exists, it's imported automatically (one time only). After that, the database is the source of truth — the service refreshes its in-memory copy from the DB every
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">AQ_STATIONS_REFRESH_INTERVAL_SEC</code>
                    (default 300s). To (re-)apply a JSON file later:
                </p>
                <pre class="bg-background-950 border border-border-700 text-text-300 p-4 rounded-xl overflow-x-auto text-xs sm:text-sm font-mono">python3 import_stations.py [path/to/stations.json]  # add --dry-run to preview</pre>
            </section>

            {{-- Seismic: two ingestion channels --}}
            <section>
                <h2 class="text-lg font-semibold text-text-100 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <span class="w-1 h-5 bg-radar-500 rounded-full"></span>
                    Seismic: two ingestion channels
                </h2>
                <p class="mb-3">
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">seismic_mqtt.py</code>
                    runs both, independently, at the same time:
                </p>
                <ul class="list-disc list-inside space-y-2 mb-4">
                    <li><strong class="text-text-100">MQTT</strong> (existing) — subscribes to <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">MQTT_TOPIC</code>, expects JSON payloads.</li>
                    <li><strong class="text-text-100">SMS</strong> (new) — a background thread drives a SIM800L over UART (<code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">SIM800_SERIAL_PORT</code>, default <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">/dev/serial0</code>) and parses incoming SMS in the <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">SEISMSG1</code> format (documented below).</li>
                </ul>
                <p class="mb-3">
                    Every SMS received — parsed or not — is stored in the
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">sms_messages</code>
                    table (sender, raw body, parse status/error), and successfully parsed readings land in the same
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">station_metrics</code>
                    table MQTT uses, tagged
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">source = 'sms'</code>
                    (vs
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">'mqtt'</code>)
                    so you can tell which channel each row came from:
                </p>
                <pre class="bg-background-950 border border-border-700 text-text-300 p-4 rounded-xl overflow-x-auto text-xs sm:text-sm font-mono mb-4">SELECT time, station_id, source, pga, peis FROM station_metrics ORDER BY time DESC LIMIT 20;</pre>
                <p class="mb-3">
                    <strong class="text-text-100">Wiring</strong>: the driver talks to a serial device path, not GPIO pins directly. Default assumes the Pi's hardware UART — GPIO14/GPIO15 (physical header pins 8/10) — wired to the SIM800L's RXD/TXD, with the module powered from its own ~4V supply (not the Pi's 3V3/5V rail). If your wiring differs, just change
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">SIM800_SERIAL_PORT</code>
                    in
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">.env</code>.
                </p>
                <p>
                    <strong class="text-text-100">Disabling SMS ingestion</strong>: set
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">SMS_INGESTION_ENABLED=false</code>
                    in
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">.env</code>,
                    or just don't install
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">pyserial</code>
                    — either way MQTT ingestion is unaffected.
                </p>
            </section>

            {{-- Centralized logging --}}
            <section>
                <h2 class="text-lg font-semibold text-text-100 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <span class="w-1 h-5 bg-radar-500 rounded-full"></span>
                    Centralized logging
                </h2>
                <p class="mb-3">
                    All three services mirror their logs into
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">service_logs</code>
                    in the
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">air_quality</code>
                    database (<code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">service</code> column identifies which one), in addition to console output (captured by systemd's journal). Query it directly:
                </p>
                <pre class="bg-background-950 border border-border-700 text-text-300 p-4 rounded-xl overflow-x-auto text-xs sm:text-sm font-mono mb-3">SELECT created_at, service, level, message
FROM service_logs
WHERE created_at > NOW() - INTERVAL '1 hour'
ORDER BY created_at DESC;</pre>
                <p>
                    Also queryable via
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">GET /api/system/logs</code>
                    on the monitoring API (filter by
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">service</code>,
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">level</code>,
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">hours</code>).
                    Disable with
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">DB_LOG_ENABLED=false</code>
                    in
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">.env</code>.
                </p>
            </section>

            {{-- Known caveats --}}
            <section>
                <h2 class="text-lg font-semibold text-text-100 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <span class="w-1 h-5 bg-munti-yellow-500 rounded-full"></span>
                    Known caveats / things to verify with real hardware
                </h2>
                <ul class="list-disc list-inside space-y-2 text-text-300">
                    <li><strong class="text-text-100">SIM800L AT response parsing</strong> (<code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">sim800l.py</code>) was written against the documented SIMCom AT command set. Some clone modules/firmware format <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">+CMGL</code>/<code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">+CMGR</code> responses slightly differently — if messages aren't being read correctly, first confirm the module responds to plain <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">AT</code> over a terminal (<code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">screen /dev/serial0 9600</code>), then adjust <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">_CMGL_HEADER_RE</code> in <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">sim800l.py</code> if needed.</li>
                    <li><strong class="text-text-100">SEISMSG1 format</strong> is a new design (you didn't have an existing SMS format) — if your sensor firmware sends something different, update <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">parse_seismic_sms()</code> in <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">seismic_mqtt.py</code> to match, or have the firmware emit this format.</li>
                    <li><code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">config.py</code> from the original upload isn't imported by any of the three services (each reads its own env vars directly) — it's not part of this deployable set; leave it out or delete it.</li>
                </ul>
            </section>

            {{-- Seismic SMS protocol --}}
            <section>
                <h2 class="text-lg font-semibold text-text-100 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <span class="w-1 h-5 bg-radar-500 rounded-full"></span>
                    Seismic SMS protocol (SEISMSG1)
                </h2>
                <p class="mb-3">
                    The SMS ingestion service accepts seismic telemetry using the <strong class="text-text-100">SEISMSG1</strong> message format.
                </p>
                <pre class="bg-background-950 border border-border-700 text-text-300 p-4 rounded-xl overflow-x-auto text-xs sm:text-sm font-mono mb-6">SEISMSG1,&lt;station_id&gt;,&lt;epoch_ts&gt;,&lt;lat&gt;,&lt;lon&gt;,&lt;elev_m&gt;,&lt;acc_x&gt;,&lt;acc_y&gt;,&lt;acc_z&gt;,&lt;vel_x&gt;,&lt;vel_y&gt;,&lt;vel_z&gt;,&lt;disp_x&gt;,&lt;disp_y&gt;,&lt;disp_z&gt;,&lt;pga&gt;,&lt;peis&gt;,&lt;checksum&gt;</pre>

                <h3 class="text-base font-semibold text-text-100 mb-3">Field definition</h3>
                <div class="overflow-x-auto rounded-xl border border-border-700 mb-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-800">
                            <tr class="border-b border-border-700">
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider w-12">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider">Field</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-800 bg-surface-900">
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">1</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">SEISMSG1</td><td class="px-4 py-2.5">Literal protocol identifier. Any SMS that does <strong class="text-text-100">not</strong> begin with this tag is stored in the <code class="bg-surface-800 border border-border-700 text-radar-300 px-1 py-0.5 rounded text-xs font-mono">sms_messages</code> table but ignored as seismic telemetry.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">2</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">station_id</td><td class="px-4 py-2.5">Station identifier. Must match the station ID used by MQTT.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">3</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">epoch_ts</td><td class="px-4 py-2.5">Unix timestamp (UTC, seconds) generated by the station.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">4</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">lat</td><td class="px-4 py-2.5">Latitude (decimal degrees). Optional.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">5</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">lon</td><td class="px-4 py-2.5">Longitude (decimal degrees). Optional.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">6</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">elev_m</td><td class="px-4 py-2.5">Elevation above sea level (meters). Optional.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">7</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">acc_x</td><td class="px-4 py-2.5">X-axis acceleration.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">8</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">acc_y</td><td class="px-4 py-2.5">Y-axis acceleration.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">9</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">acc_z</td><td class="px-4 py-2.5">Z-axis acceleration.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">10</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">vel_x</td><td class="px-4 py-2.5">X-axis velocity.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">11</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">vel_y</td><td class="px-4 py-2.5">Y-axis velocity.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">12</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">vel_z</td><td class="px-4 py-2.5">Z-axis velocity.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">13</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">disp_x</td><td class="px-4 py-2.5">X-axis displacement.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">14</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">disp_y</td><td class="px-4 py-2.5">Y-axis displacement.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">15</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">disp_z</td><td class="px-4 py-2.5">Z-axis displacement.</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">16</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">pga</td><td class="px-4 py-2.5">Peak Ground Acceleration (PGA).</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">17</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">peis</td><td class="px-4 py-2.5">Earthquake intensity code (integer).</td></tr>
                            <tr class="hover:bg-surface-800/60 transition"><td class="px-4 py-2.5 text-text-500">18</td><td class="px-4 py-2.5 font-mono text-radar-400 text-xs">checksum</td><td class="px-4 py-2.5">Optional but recommended. Two-digit uppercase hex checksum (sum of ASCII values before the checksum field, mod 256).</td></tr>
                        </tbody>
                    </table>
                </div>

                <h3 class="text-base font-semibold text-text-100 mb-2">Example (with location and checksum)</h3>
                <pre class="bg-background-950 border border-border-700 text-text-300 p-4 rounded-xl overflow-x-auto text-xs sm:text-sm font-mono mb-4">SEISMSG1,STN-004,1721818530,14.5995,120.9842,15.2,0.012,-0.008,0.021,0.5,0.3,0.6,1.2,0.9,1.5,0.045,2,3F</pre>

                <h3 class="text-base font-semibold text-text-100 mb-2">Example (without location or checksum)</h3>
                <pre class="bg-background-950 border border-border-700 text-text-300 p-4 rounded-xl overflow-x-auto text-xs sm:text-sm font-mono mb-4">SEISMSG1,STN-004,1721818530,,,,0.012,-0.008,0.021,0.5,0.3,0.6,1.2,0.9,1.5,0.045,2</pre>

                <h3 class="text-base font-semibold text-text-100 mb-2">Processing behavior</h3>
                <ul class="list-disc list-inside space-y-2 mb-3">
                    <li>SMS messages beginning with <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">SEISMSG1</code> are parsed as seismic telemetry.</li>
                    <li>Successfully parsed readings are stored in <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">station_metrics</code> with <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">source = 'sms'</code>.</li>
                    <li>SMS messages that fail parsing are still archived in <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">sms_messages</code> with the error for troubleshooting.</li>
                    <li>SMS messages that do <strong class="text-text-100">not</strong> begin with <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">SEISMSG1</code> are archived but ignored by the telemetry parser.</li>
                </ul>
                <p>This allows the SIM800L to receive both seismic telemetry and ordinary SMS messages without affecting system operation.</p>
            </section>

            {{-- SMS transmission --}}
            <section>
                <h2 class="text-lg font-semibold text-text-100 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <span class="w-1 h-5 bg-radar-500 rounded-full"></span>
                    SMS transmission (SIM800L)
                </h2>
                <p class="mb-4">
                    In addition to receiving seismic telemetry via SMS, the SIM800L driver supports sending SMS messages for acknowledgements, diagnostics, or remote command responses.
                </p>

                <h3 class="text-base font-semibold text-text-100 mb-2">Implementation</h3>
                <p class="mb-3">
                    The SIM800L driver (<code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">sim800l.py</code>) includes a
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">send_sms()</code>
                    method that follows the standard SIMCom AT command sequence for SMS transmission.
                </p>
                <pre class="bg-background-950 border border-border-700 text-text-300 p-4 rounded-xl overflow-x-auto text-xs sm:text-sm font-mono mb-4">AT+CMGS="&lt;phone_number&gt;"
        ↓
     wait for '>' prompt
        ↓
   write message body
        ↓
   send Ctrl+Z (0x1A)
        ↓
wait for +CMGS:&lt;reference&gt; and OK</pre>
                <p class="mb-4">
                    Unlike most AT commands, <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">AT+CMGS</code> returns a standalone
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">&gt;</code>
                    prompt rather than a CR/LF-terminated response. Because of this, the driver implements a dedicated
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">_wait_for_prompt()</code>
                    helper instead of using the normal
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">_read_until()</code>
                    parser.
                </p>

                <h3 class="text-base font-semibold text-text-100 mb-2">Added methods</h3>
                <ul class="list-disc list-inside space-y-2 mb-4">
                    <li><code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">send_sms(number, text, timeout=None)</code> – Sends an SMS. Uses a longer timeout (max of <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">self.timeout * 3, 15</code>) because GSM delivery takes longer than ordinary AT commands.</li>
                    <li><code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">_wait_for_prompt(timeout)</code> – Waits for the <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">&gt;</code> prompt from <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">AT+CMGS</code>.</li>
                </ul>

                <h3 class="text-base font-semibold text-text-100 mb-2">Integration</h3>
                <p class="mb-3">
                    <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">seismic_ingest.py</code> uses the driver through:
                </p>
                <pre class="bg-background-950 border border-border-700 text-text-300 p-4 rounded-xl overflow-x-auto text-xs sm:text-sm font-mono mb-3">_send_sms_reply(...)
    └── modem.send_sms(number, text)</pre>
                <p class="mb-4">This enables the gateway to send SMS acknowledgements or responses directly through the SIM800L modem.</p>

                <h3 class="text-base font-semibold text-text-100 mb-2">Validation</h3>
                <p class="mb-3">
                    The SMS transmission sequence has been verified using a mocked serial interface. Validated sequence:
                </p>
                <pre class="bg-background-950 border border-border-700 text-text-300 p-4 rounded-xl overflow-x-auto text-xs sm:text-sm font-mono mb-3">AT+CMGS="&lt;number&gt;"
        ↓
     modem returns >
        ↓
   write SMS message
        ↓
   Ctrl+Z (0x1A)
        ↓
 +CMGS:&lt;reference&gt;
        ↓
        OK</pre>
                <p class="mb-4">This confirms the driver's state machine correctly handles the SIM800L SMS transmission workflow.</p>

                <h3 class="text-base font-semibold text-text-100 mb-2">Hardware verification</h3>
                <p class="mb-3">
                    Although the transmission logic has been validated with a mocked serial port, final verification should be performed on a physical SIM800L module. A recommended test is:
                </p>
                <ol class="list-decimal list-inside space-y-2 mb-3">
                    <li>Send a <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">PING</code> command to the gateway.</li>
                    <li>Verify the gateway replies with <code class="bg-surface-800 border border-border-700 text-radar-300 px-1.5 py-0.5 rounded text-sm font-mono">OK</code>.</li>
                    <li>Confirm the application log contains a message similar to:
                        <pre class="bg-background-950 border border-border-700 text-text-300 p-3 rounded-xl overflow-x-auto text-xs sm:text-sm mt-2 font-mono">Sent reply 'OK' to &lt;phone_number&gt;</pre>
                    </li>
                </ol>
                <p>This verifies the complete GSM transmission path, including UART communication, modem operation, and carrier network delivery.</p>
            </section>

        </div>
    </div>
</main>

@include('layouts.footer')