<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Environment Monitoring System Status Report</title>
    <style>
        /* dompdf only understands a subset of CSS: no flexbox/grid, so
           this template sticks to block/table layout and inline-safe
           properties. */
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            margin: 0;
        }
        .header {
            border-bottom: 2px solid #0F766E;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0 0 4px 0;
            color: #0F766E;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 4px;
        }
        .meta-table td {
            font-size: 10px;
            color: #444;
            padding: 1px 0;
        }
        .meta-table td.label {
            width: 110px;
            color: #777;
        }
        .summary-box {
            border: 1px solid #ddd;
            background: #f5f7f7;
            padding: 8px 10px;
            margin-bottom: 18px;
        }
        .summary-box table {
            width: 100%;
        }
        .summary-box th {
            font-size: 9.5px;
            font-weight: normal;
            color: #777;
            text-transform: uppercase;
            text-align: center;
            padding: 2px 0 6px 0;
        }
        .summary-box td {
            font-size: 11px;
            text-align: center;
            padding: 2px 0;
        }
        .summary-box th:first-child,
        .summary-box td:first-child {
            text-align: left;
            width: 100px;
            font-weight: bold;
            color: #333;
        }
        .summary-box .num {
            font-size: 16px;
            font-weight: bold;
            color: #0F766E;
        }
        .summary-box .total-row td {
            border-top: 1px solid #ccc;
            padding-top: 6px;
        }
        h2.section-title {
            font-size: 13px;
            color: #0F766E;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
            margin: 18px 0 8px 0;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        table.data-table th {
            background: #0F766E;
            color: #fff;
            font-size: 9.5px;
            text-align: left;
            padding: 5px 6px;
            text-transform: uppercase;
        }
        table.data-table td {
            font-size: 10px;
            padding: 5px 6px;
            border-bottom: 1px solid #e5e5e5;
        }
        table.data-table tr:nth-child(even) td {
            background: #fafafa;
        }
        .status {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            color: #fff;
        }
        .status-online   { background: #16A34A; }
        .status-idle     { background: #D97706; }
        .status-offline  { background: #DC2626; }
        /* System status (uptime / storage) thresholds:
           <=80% critical, 81-99% warning, 100% good */
        .status-good     { background: #16A34A; }
        .status-warning  { background: #D97706; }
        .status-critical { background: #DC2626; }
        .footer {
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>

    @php
        // Shared threshold logic for percentage-based system metrics
        // (uptime %, storage %): 100% = good, 81-99% = warning,
        // 80% and below = critical.
        $systemStatusClass = function ($percent) {
            if ($percent === null) return 'status-warning';
            if ($percent >= 100) return 'status-good';
            if ($percent >= 81) return 'status-warning';
            return 'status-critical';
        };
        $systemStatusLabel = function ($percent) {
            if ($percent === null) return 'N/A';
            if ($percent >= 100) return 'Good';
            if ($percent >= 81) return 'Warning';
            return 'Critical';
        };

        // For binary services (MQTT broker, database, ems.target) rather
        // than percentage metrics: true = Online, false = Offline,
        // null = Unknown (not reported by the backend check).
        $serviceStatusClass = function ($isUp) {
            if ($isUp === null) return 'status-idle';
            return $isUp ? 'status-online' : 'status-offline';
        };
        $serviceStatusLabel = function ($isUp) {
            if ($isUp === null) return 'Unknown';
            return $isUp ? 'Online' : 'Offline';
        };

        // Network port status, mirroring the dashboard's summary-network
        // color rule: green when the link is up and has an IPv4 address,
        // amber when it's up but still negotiating (no address yet), gray
        // when the link is down.
        $portStatusClass = function ($port) {
            if ($port['active'] && !empty($port['ip_cidr'])) return 'status-online';
            if ($port['active']) return 'status-idle';
            return 'status-offline';
        };
        $portStatusLabel = function ($port) {
            if ($port['active'] && !empty($port['ip_cidr'])) return 'Up';
            if ($port['active']) return 'Up (No IP)';
            return 'Down';
        };
    @endphp

    <div class="header">
        <h1>Environment Monitoring System Status Report</h1>
        <div style="font-size: 9.5px; color: #888; margin: -2px 0 8px 0;">
            Developed by Uplink Integrated Solutions Inc.
        </div>
        <table class="meta-table">
            <tr>
                <td class="label">Report Date/Time:</td>
                <td>{{ $generatedAt->format('F d, Y  h:i A') }}</td>
            </tr>
            <tr>
                <td class="label">Generated By:</td>
                <td>{{ $generatedBy }}</td>
            </tr>
        </table>
    </div>

    {{-- ---------- System Summary (Hardware / Network Ports) ---------- --}}
    <h2 class="section-title">System Summary</h2>
    <table style="width: 100%; margin-bottom: 18px;">
        <tr>
            <td style="width: 50%; padding-right: 8px; vertical-align: top;">
                <div class="summary-box" style="margin-bottom: 0;">
                    <div style="font-size: 9.5px; color: #777; text-transform: uppercase; margin-bottom: 6px;">Hardware</div>
                    <table class="meta-table">
                        <tr>
                            <td class="label">Device Model:</td>
                            <td>{{ $deviceModel ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">CPU Model:</td>
                            <td>{{ $cpuModel ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">OS Version:</td>
                            <td>{{ $osVersion ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Memory:</td>
                            <td>{{ $memoryText ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Storage Type:</td>
                            <td>{{ $storageType ?? '—' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td style="width: 50%; padding-left: 8px; vertical-align: top;">
                <div class="summary-box" style="margin-bottom: 0;">
                    <div style="font-size: 9.5px; color: #777; text-transform: uppercase; margin-bottom: 6px;">Network Ports</div>
                    <table class="meta-table">
                        @forelse (($networkPorts ?? []) as $port)
                            <tr>
                                <td class="label">{{ $port['name'] }}:</td>
                                <td>
                                    <span class="status {{ $portStatusClass($port) }}" style="padding: 1px 5px; font-size: 8px;">
                                        {{ $portStatusLabel($port) }}
                                    </span>
                                    {{ $port['ip_cidr'] ?? '—' }}{{ !empty($port['speed']) ? ' · ' . $port['speed'] : '' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td>No network interfaces reported</td></tr>
                        @endforelse
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- ---------- Stations Status Summary ---------- --}}
    <h2 class="section-title">Stations Status Summary</h2>
    <div class="summary-box">
        <table>
            <tr>
                <th></th>
                <th>Online</th>
                <th>Idle</th>
                <th>Offline</th>
                <th>Total</th>
            </tr>
            <tr>
                <td>Air Quality</td>
                <td class="num">{{ $airQualityCounts['online'] }}</td>
                <td class="num">{{ $airQualityCounts['idle'] }}</td>
                <td class="num">{{ $airQualityCounts['offline'] }}</td>
                <td class="num">{{ count($airQualityData) }}</td>
            </tr>
            <tr>
                <td>Seismic</td>
                <td class="num">{{ $seismicCounts['online'] }}</td>
                <td class="num">{{ $seismicCounts['idle'] }}</td>
                <td class="num">{{ $seismicCounts['offline'] }}</td>
                <td class="num">{{ count($seismicData) }}</td>
            </tr>
            <tr class="total-row">
                <td>Total</td>
                <td class="num">{{ $airQualityCounts['online'] + $seismicCounts['online'] }}</td>
                <td class="num">{{ $airQualityCounts['idle'] + $seismicCounts['idle'] }}</td>
                <td class="num">{{ $airQualityCounts['offline'] + $seismicCounts['offline'] }}</td>
                <td class="num">{{ count($airQualityData) + count($seismicData) }}</td>
            </tr>
        </table>
    </div>

    {{-- ---------- System Status (uptime / storage) ---------- --}}
    <h2 class="section-title">System Status</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Metric</th>
                <th>Detail</th>
                <th>Value</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>System Uptime</td>
                <td>Since last restart</td>
                <td>{{ $systemUptimeHuman ?? '—' }}</td>
                <td>
                    <span class="status {{ $serviceStatusClass(isset($systemUptimeHuman)) }}">
                        {{ $serviceStatusLabel(isset($systemUptimeHuman)) }}
                    </span>
                </td>
            </tr>
            <tr>
                <td>Storage</td>
                <td>
                    @if (isset($storageUsedGb, $storageTotalGb))
                        {{ number_format($storageUsedGb, 1) }} GB used of {{ number_format($storageTotalGb, 1) }} GB
                    @else
                        —
                    @endif
                </td>
                <td>{{ isset($storagePercent) ? number_format($storagePercent, 2) . '% free' : '—' }}</td>
                <td>
                    <span class="status {{ $systemStatusClass($storagePercent ?? null) }}">
                        {{ $systemStatusLabel($storagePercent ?? null) }}
                    </span>
                </td>
            </tr>
            <tr>
                <td>MQTT Broker (Mosquitto)</td>
                <td>mosquitto.service</td>
                <td>{{ $mqttStatusText ?? (($mqttOnline ?? null) === null ? '—' : ($mqttOnline ? 'Running' : 'Stopped')) }}</td>
                <td>
                    <span class="status {{ $serviceStatusClass($mqttOnline ?? null) }}">
                        {{ $serviceStatusLabel($mqttOnline ?? null) }}
                    </span>
                </td>
            </tr>
            <tr>
                <td>Database (PostgreSQL)</td>
                <td>postgresql.service</td>
                <td>{{ $databaseStatusText ?? (($databaseOnline ?? null) === null ? '—' : ($databaseOnline ? 'Running' : 'Stopped')) }}</td>
                <td>
                    <span class="status {{ $serviceStatusClass($databaseOnline ?? null) }}">
                        {{ $serviceStatusLabel($databaseOnline ?? null) }}
                    </span>
                </td>
            </tr>
            <tr>
                <td>EMS Gateway</td>
                <td>ems.target</td>
                <td>{{ $emsStatusText ?? (($emsOnline ?? null) === null ? '—' : ($emsOnline ? 'Running' : 'Stopped')) }}</td>
                <td>
                    <span class="status {{ $serviceStatusClass($emsOnline ?? null) }}">
                        {{ $serviceStatusLabel($emsOnline ?? null) }}
                    </span>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- ---------- Air Quality ---------- --}}
    <h2 class="section-title">
        Air Quality Stations
        ({{ $airQualityCounts['online'] }} online / {{ count($airQualityData) }} total)
    </h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>No.</th>
                <th>Station Name</th>
                <th>Total no. of Data</th>
                <th>Latest Data</th>
                <th>Last Seen</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($airQualityData as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->station }}</td>
                    <td>{{ number_format($item->total) }}</td>
                    <td>{{ $item->latest_at ? \Carbon\Carbon::parse($item->latest_at)->format('Y-m-d H:i') : '—' }}</td>
                    <td>
                        @if ($item->status === 'offline' && $item->latest_at)
                            {{ \Carbon\Carbon::parse($item->latest_at)->format('Y-m-d H:i') }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <span class="status status-{{ $item->status }}">{{ ucfirst($item->status) }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No air quality data available</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ---------- Seismic ---------- --}}
    <h2 class="section-title">
        Seismic Stations
        ({{ $seismicCounts['online'] }} online / {{ count($seismicData) }} total)
    </h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>No.</th>
                <th>Station Name</th>
                <th>Total no. of Data</th>
                <th>Latest Data</th>
                <th>Last Seen</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($seismicData as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->station }}</td>
                    <td>{{ number_format($item->total) }}</td>
                    <td>{{ $item->latest_at ? \Carbon\Carbon::parse($item->latest_at)->format('Y-m-d H:i') : '—' }}</td>
                    <td>
                        @if ($item->status === 'offline' && $item->latest_at)
                            {{ \Carbon\Carbon::parse($item->latest_at)->format('Y-m-d H:i') }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <span class="status status-{{ $item->status }}">{{ ucfirst($item->status) }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No seismic data available</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        This report reflects station status at the time it was generated and may not match a subsequently refreshed dashboard.<br>
        &copy; {{ $generatedAt->format('Y') }} Uplink Integrated Solutions Inc. All rights reserved.
    </div>

</body>
</html>