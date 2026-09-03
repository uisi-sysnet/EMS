@include('layouts.header')
@include('layouts.topbar')

@php
    // --- System stats -------------------------------------------------
    // Note: this reads Linux's /proc filesystem + PHP's disk_*_space()
    // functions. It works out of the box on a typical Linux server /
    // Raspberry Pi. On shared hosting these can be disabled, and on
    // Windows /proc simply won't exist — in that case the tiles below
    // fall back to 0 / "—" instead of erroring. Ideally this logic
    // moves into a controller/service and gets passed to the view (or
    // exposed via a small JSON endpoint for AJAX refresh), but it's
    // inlined here since only the view file is in hand.

    $formatBytes = function ($bytes, $decimals = 1) {
        if (!$bytes) return '0 GB';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = (int) floor((strlen((string) (int) $bytes) - 1) / 3);
        $factor = max(0, min($factor, count($units) - 1));
        return sprintf("%.{$decimals}f", $bytes / (1024 ** $factor)) . ' ' . $units[$factor];
    };

    $barColor = function ($percent) {
        if ($percent >= 85) return ['text-red-400', 'bg-red-500'];
        if ($percent >= 60) return ['text-amber-400', 'bg-amber-500'];
        return ['text-munti-green-400', 'bg-munti-green-500'];
    };

    // Storage (root filesystem)
    $diskTotal   = @disk_total_space('/') ?: 0;
    $diskFree    = @disk_free_space('/') ?: 0;
    $diskUsed    = $diskTotal ? $diskTotal - $diskFree : 0;
    $diskPercent = $diskTotal ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

    // Memory
    $memTotal = 0;
    $memAvailable = 0;
    if (@is_readable('/proc/meminfo')) {
        foreach (file('/proc/meminfo') as $line) {
            if (str_starts_with($line, 'MemTotal:')) {
                $memTotal = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT) * 1024;
            }
            if (str_starts_with($line, 'MemAvailable:')) {
                $memAvailable = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT) * 1024;
            }
        }
    }
    $memUsed    = $memTotal ? $memTotal - $memAvailable : 0;
    $memPercent = $memTotal ? round(($memUsed / $memTotal) * 100, 1) : 0;

    // CPU (approximated from 1-minute load average / core count)
    $cpuCores = 1;
    if (@is_readable('/proc/cpuinfo')) {
        $cpuCores = max(1, substr_count(file_get_contents('/proc/cpuinfo'), 'processor'));
    }
    $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
    $load = $load ?: [0, 0, 0];
    $cpuPercent = round(min(($load[0] / $cpuCores) * 100, 100), 1);

    // Uptime
    $uptimeSeconds = 0;
    if (@is_readable('/proc/uptime')) {
        $uptimeSeconds = (int) floatval(explode(' ', file_get_contents('/proc/uptime'))[0]);
    }
    $uptimeDays    = intdiv($uptimeSeconds, 86400);
    $uptimeHours   = intdiv($uptimeSeconds % 86400, 3600);
    $uptimeMinutes = intdiv($uptimeSeconds % 3600, 60);

    $cpuColors  = $barColor($cpuPercent);
    $memColors  = $barColor($memPercent);
    $diskColors = $barColor($diskPercent);

    // --- Station status -------------------------------------------------
    // There's no explicit "status" field coming from the query, so status
    // is derived from how long ago each station's last reading came in:
    //   <= 2 min   -> Online
    //   2–3 min    -> Idle
    //   > 3 min (or no reading at all) -> Offline
    // Adjust the two thresholds below to match your stations' real
    // reporting interval (or swap this out for a real $item->status
    // column if/when the controller provides one).
    $idleThresholdMinutes    = 2;
    $offlineThresholdMinutes = 3;

    $annotateStatus = function ($collection) use ($idleThresholdMinutes, $offlineThresholdMinutes) {
        $counts = ['online' => 0, 'idle' => 0, 'offline' => 0];
        foreach ($collection as $item) {
            $status = 'offline';
            if (!empty($item->latest_at)) {
                $minutesAgo = \Carbon\Carbon::parse($item->latest_at)->diffInMinutes(now());
                if ($minutesAgo <= $idleThresholdMinutes) {
                    $status = 'online';
                } elseif ($minutesAgo <= $offlineThresholdMinutes) {
                    $status = 'idle';
                }
            }
            $item->status = $status;
            $counts[$status]++;
        }
        return $counts;
    };

    $statusBadgeMeta = [
        'online'  => ['label' => 'Online',  'text' => 'text-munti-green-400', 'bg' => 'bg-munti-green-700/20', 'border' => 'border-munti-green-600/30', 'dot' => 'bg-munti-green-400'],
        'idle'    => ['label' => 'Idle',    'text' => 'text-amber-400',       'bg' => 'bg-amber-700/20',       'border' => 'border-amber-600/30',       'dot' => 'bg-amber-400'],
        'offline' => ['label' => 'Offline', 'text' => 'text-red-400',         'bg' => 'bg-red-700/20',         'border' => 'border-red-600/30',         'dot' => 'bg-red-400'],
    ];

    $airQualityData    = $airQualityData ?? [];
    $seismicData       = $seismicData ?? [];
    $airQualityCounts  = $annotateStatus($airQualityData);
    $seismicCounts     = $annotateStatus($seismicData);
    $airQualityOnline  = $airQualityCounts['online'];
    $seismicOnline     = $seismicCounts['online'];
    $airQualityTotal   = count($airQualityData);
    $seismicTotal      = count($seismicData);

    $cameraCounts = $cameraCounts ?? ['online' => 0, 'idle' => 0, 'offline' => 0];
    $cameraOnline = $cameraCounts['online'];
    $cameraIdle = $cameraCounts['idle'];
    $cameraOffline = $cameraCounts['offline'];
    $cameraTotal = $cameraOnline + $cameraIdle + $cameraOffline;

    $totalStations = $airQualityTotal + $seismicTotal;
    $totalOnline   = $airQualityOnline + $seismicOnline;
    // Percentage used for the system status banner counts strictly-Online
    // stations only — an Idle station is a stale-data warning sign too,
    // so it doesn't count toward "fully healthy" here. If you'd rather
    // have Idle count as "up", change this to ($totalOnline + idle) / total.
    $overallOnlinePercent = $totalStations > 0 ? round(($totalOnline / $totalStations) * 100, 1) : 100;

    // System status thresholds (based on % of stations online):
    //   no stations -> Idle (nothing configured yet, not a "good" or "bad" state)
    //   100%        -> Good
    //   80% - 99%   -> Warning
    //   below 80%   -> Critical
    if ($totalStations === 0) {
        $systemStatus = 'idle';
    } elseif ($overallOnlinePercent >= 100) {
        $systemStatus = 'good';
    } elseif ($overallOnlinePercent >= 80) {
        $systemStatus = 'warning';
    } else {
        $systemStatus = 'critical';
    }

    $systemStatusMeta = [
        'idle' => [
            'label' => 'No Stations Configured', 'text' => 'text-amber-400',
            'bg' => 'bg-amber-700/10', 'border' => 'border-amber-600/30', 'dot' => 'bg-amber-400',
        ],
        'good' => [
            'label' => 'All Systems Good', 'text' => 'text-munti-green-400',
            'bg' => 'bg-munti-green-700/10', 'border' => 'border-munti-green-600/30', 'dot' => 'bg-munti-green-400',
        ],
        'warning' => [
            'label' => 'Warning', 'text' => 'text-amber-400',
            'bg' => 'bg-amber-700/10', 'border' => 'border-amber-600/30', 'dot' => 'bg-amber-400',
        ],
        'critical' => [
            'label' => 'Critical', 'text' => 'text-red-400',
            'bg' => 'bg-red-700/10', 'border' => 'border-red-600/30', 'dot' => 'bg-red-400',
        ],
    ][$systemStatus];
@endphp

<style>
    /* Hide scrollbar but keep scrolling */
    .thin-scrollbar {
        -ms-overflow-style: none;  /* IE and Edge */
        scrollbar-width: none;     /* Firefox */
    }
    .thin-scrollbar::-webkit-scrollbar {
        display: none;             /* Chrome, Safari, Opera */
    }
</style>

<div id="main-content"
     data-refresh-url="{{ route('dashboard.data') }}"
     class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">

    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                <span class="leading-tight uppercase">Dashboard</span>
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-xs text-text-400">Overview of stations</span>
                <a href="{{ route('dashboard.report.image') }}"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 text-xs font-medium text-text-200 bg-surface-700/40 border border-border-600/30 px-3 py-1.5 rounded-lg hover:bg-surface-700/60 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Download Image
                </a>
                <a href="{{ route('dashboard.report') }}"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 text-xs font-medium text-munti-green-400 bg-munti-green-700/20 border border-munti-green-600/30 px-3 py-1.5 rounded-lg hover:bg-munti-green-700/30 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H8a2 2 0 01-2-2V5a2 2 0 012-2h6l6 6v11a2 2 0 01-2 2z" />
                    </svg>
                    Generate Report
                </a>
            </div>
        </div>

        <!-- Body – scrollable -->
        <div class="flex-1 p-3 sm:p-4 overflow-y-auto thin-scrollbar min-h-0 bg-background-900">
            <div class="grid grid-cols-1 gap-4">

                <!-- SYSTEM STATUS BANNER -->
                <div id="system-status-banner" class="rounded-xl border {{ $systemStatusMeta['border'] }} {{ $systemStatusMeta['bg'] }} overflow-hidden">
                    <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-2.5 w-2.5">
                                <span id="system-status-ping" class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $systemStatusMeta['dot'] }} opacity-60" @if($systemStatus === 'good') style="display:none" @endif></span>
                                <span id="system-status-dot" class="relative inline-flex rounded-full h-2.5 w-2.5 {{ $systemStatusMeta['dot'] }}"></span>
                            </span>
                            <span id="system-status-label" class="text-sm font-semibold {{ $systemStatusMeta['text'] }}">{{ $systemStatusMeta['label'] }}</span>
                        </div>
                        <span id="system-status-count" class="text-xs text-text-300">
                            @if($totalStations === 0)
                                No stations added yet
                            @else
                                {{ $totalOnline }}/{{ $totalStations }} stations online ({{ $overallOnlinePercent }}%)
                            @endif
                        </span>
                    </div>
                </div>

                <!-- SYSTEM HEALTH: CPU / Memory / Storage / Uptime -->
                <div class="bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden">
                    <div class="px-3 py-2 border-b border-border-700 bg-surface-900/80 flex items-center justify-between gap-2">
                        <h3 class="text-xs font-semibold text-text-200 flex items-center gap-1.5 min-w-0">
                            <span class="truncate">System Health</span>
                        </h3>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full border border-munti-green-600/30">
                                Live
                            </span>
                            <button type="button" id="system-health-toggle" aria-expanded="true" aria-controls="system-health-body"
                                    class="p-0.5 rounded hover:bg-surface-700/60 text-text-400 hover:text-text-200 transition">
                                <svg id="system-health-chevron" class="w-3.5 h-3.5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div id="system-health-body" class="p-3 sm:p-4 grid grid-cols-2 sm:grid-cols-4 gap-3">

                        <!-- CPU -->
                        <div class="bg-surface-900/60 rounded-lg border border-border-700 p-3 flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] uppercase tracking-wider text-text-400 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 7h10v10H7V7z"/></svg>
                                    CPU
                                </span>
                                <span id="cpu-percent" class="text-xs font-semibold {{ $cpuColors[0] }}">{{ $cpuPercent }}%</span>
                            </div>
                            <div class="w-full h-1.5 bg-surface-700 rounded-full overflow-hidden">
                                <div id="cpu-bar" class="h-full {{ $cpuColors[1] }} rounded-full" style="width: {{ min($cpuPercent, 100) }}%"></div>
                            </div>
                            <span id="cpu-meta" class="text-[10px] text-text-500">{{ $cpuCores }} core{{ $cpuCores > 1 ? 's' : '' }} · load {{ number_format($load[0], 2) }}</span>
                        </div>

                        <!-- Memory -->
                        <div class="bg-surface-900/60 rounded-lg border border-border-700 p-3 flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] uppercase tracking-wider text-text-400 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V7a2 2 0 012-2h14a2 2 0 012 2v3a2 2 0 01-2 2M5 12a2 2 0 00-2 2v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 00-2-2"/></svg>
                                    Memory
                                </span>
                                <span id="mem-percent" class="text-xs font-semibold {{ $memColors[0] }}">{{ $memPercent }}%</span>
                            </div>
                            <div class="w-full h-1.5 bg-surface-700 rounded-full overflow-hidden">
                                <div id="mem-bar" class="h-full {{ $memColors[1] }} rounded-full" style="width: {{ min($memPercent, 100) }}%"></div>
                            </div>
                            <span id="mem-meta" class="text-[10px] text-text-500">{{ $formatBytes($memUsed) }} / {{ $formatBytes($memTotal) }}</span>
                        </div>

                        <!-- Storage -->
                        <div class="bg-surface-900/60 rounded-lg border border-border-700 p-3 flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] uppercase tracking-wider text-text-400 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10a2 2 0 002 2h12a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H6a2 2 0 00-2 2z"/></svg>
                                    Storage
                                </span>
                                <span id="disk-percent" class="text-xs font-semibold {{ $diskColors[0] }}">{{ $diskPercent }}%</span>
                            </div>
                            <div class="w-full h-1.5 bg-surface-700 rounded-full overflow-hidden">
                                <div id="disk-bar" class="h-full {{ $diskColors[1] }} rounded-full" style="width: {{ min($diskPercent, 100) }}%"></div>
                            </div>
                            <span id="disk-meta" class="text-[10px] text-text-500">{{ $formatBytes($diskUsed) }} / {{ $formatBytes($diskTotal) }}</span>
                        </div>

                        <!-- Uptime -->
                        <div class="bg-surface-900/60 rounded-lg border border-border-700 p-3 flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] uppercase tracking-wider text-text-400 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Uptime
                                </span>
                                <span class="text-xs font-semibold text-munti-green-400">Online</span>
                            </div>
                            <span id="uptime-text" class="text-sm font-medium text-text-100">{{ $uptimeDays }}d {{ $uptimeHours }}h {{ $uptimeMinutes }}m</span>
                            <span class="text-[10px] text-text-500">Since last reboot</span>
                        </div>

                    </div>
                </div>

                <!-- SYSTEM SUMMARY: Device / CPU / OS / Memory / Storage / Network identity -->
                <div class="bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden">
                    <div class="px-3 py-2 border-b border-border-700 bg-surface-900/80 flex items-center justify-between gap-2">
                        <h3 class="text-xs font-semibold text-text-200 flex items-center gap-1.5 min-w-0">
                            <span class="truncate">System Summary</span>
                        </h3>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-[10px] text-text-400 bg-surface-700/40 px-1.5 py-0.5 rounded-full border border-border-600/30">
                                Hardware
                            </span>
                            <button type="button" id="system-summary-toggle" aria-expanded="true" aria-controls="system-summary-body"
                                    class="p-0.5 rounded hover:bg-surface-700/60 text-text-400 hover:text-text-200 transition">
                                <svg id="system-summary-chevron" class="w-3.5 h-3.5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div id="system-summary-body">
                        <div class="p-3 sm:p-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 text-xs">

                            <div class="flex items-center justify-between gap-2 py-1 border-b border-border-700/50">
                                <span class="text-text-400">Device Model</span>
                                <span id="summary-device" class="text-text-100 font-medium text-right truncate max-w-[65%]">{{ $systemSummary['device_model'] }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-2 py-1 border-b border-border-700/50">
                                <span class="text-text-400">CPU Model</span>
                                <span id="summary-cpu" class="text-text-100 font-medium text-right truncate max-w-[65%]">{{ $systemSummary['cpu_model'] }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-2 py-1 border-b border-border-700/50">
                                <span class="text-text-400">OS Version</span>
                                <span id="summary-os" class="text-text-100 font-medium text-right truncate max-w-[65%]">{{ $systemSummary['os_version'] }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-2 py-1 border-b border-border-700/50">
                                <span class="text-text-400">Memory</span>
                                <span id="summary-memory" class="text-text-100 font-medium text-right truncate max-w-[65%]">
                                    @if($systemSummary['memory']['available'])
                                        {{ $systemSummary['memory']['slots_used'] }}/{{ $systemSummary['memory']['slots_total'] }} DIMMs &middot; {{ $systemSummary['memory']['total_label'] }}
                                    @else
                                        {{ $systemSummary['memory']['total_label'] }} <span class="text-text-500">(DIMM count needs sudo)</span>
                                    @endif
                                </span>
                            </div>

                            <div class="flex items-center justify-between gap-2 py-1 border-b border-border-700/50">
                                <span class="text-text-400">Storage Type</span>
                                <span id="summary-storage" class="text-text-100 font-medium text-right truncate max-w-[65%]">{{ $systemSummary['storage'] }}</span>
                            </div>

                            <!-- Network Ports strip -->
                            <div class="flex items-center gap-4 min-w-0">
                                <span class="text-[10px] uppercase tracking-wider text-text-400 shrink-0 me-32">Network Ports</span>
                                <div id="summary-network-ports" class="flex items-center justify-center gap-4 flex-nowrap min-w-0">
                                    @forelse($systemSummary['network']['ports'] as $port)
                                        <div class="flex flex-col items-center w-[90px] gap-1.5 shrink-0">
                                            <span class="text-[9px] text-text-500 uppercase tracking-wide w-[90px] text-center"
                                                title="{{ $port['name'] }}">
                                                {{ $port['name'] }}
                                            </span>
                                            <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ $port['colors']['bg'] }}">
                                                @if($port['active'])
                                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                            d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                    </svg>
                                                @else
                                                    <svg class="w-4 h-4 text-text-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                    </svg>
                                                @endif
                                            </div>
                                            <span class="text-[9px] text-text-500 w-[90px] text-center leading-none"
                                                title="{{ $port['ip_cidr'] ?? 'No IP assigned' }}">
                                                {{ $port['ip_cidr'] ?? '—' }}
                                            </span>
                                        </div>
                                    @empty
                                        <span class="text-xs text-text-500">No network interfaces detected</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- THREE STATUS CARDS IN ONE ROW -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 w-full min-w-0">
                    
                    <!-- Card 1: Air Quality Station Status -->
                    <div class="bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden">
                        <div class="px-3 py-2 border-b border-border-700 bg-surface-900/80 flex items-center justify-between gap-2">
                            <h3 class="text-xs font-semibold text-text-200 flex items-center gap-1.5 min-w-0">
                                <span class="truncate">Air Quality Station Status</span>
                            </h3>
                        </div>

                        <div class="p-4 sm:p-5 flex flex-col items-center gap-4 w-full min-w-0">
                            <!-- Donut Chart -->
                            <div class="relative w-28 h-28 sm:w-32 sm:h-32 shrink-0">
                                <canvas id="airQualityStatusChart"></canvas>
                                <div id="aq-donut-center" class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    @if($airQualityTotal > 0)
                                        <span class="text-lg font-bold text-text-100 leading-none">
                                            {{ round(($airQualityOnline / $airQualityTotal) * 100) }}%
                                        </span>
                                        <span class="text-[10px] text-text-400 uppercase tracking-wide mt-0.5">Online</span>
                                    @else
                                        <span class="text-lg font-bold text-amber-400 leading-none">—</span>
                                        <span class="text-[10px] text-amber-400 uppercase tracking-wide mt-0.5">No Stations</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Status Counts -->
                            <div class="flex flex-col gap-2 w-full">
                                <div class="flex items-center justify-between gap-4 px-3 py-1">
                                    <span class="flex items-center gap-4 min-w-0">
                                        <span class="w-2.5 h-2.5 rounded-full bg-munti-green-400 shadow-[0_0_6px_rgba(74,222,128,0.45)] shrink-0"></span>
                                        <span class="text-sm text-text-300 truncate">Online</span>
                                    </span>
                                    <span id="aq-online-count" class="text-sm font-semibold text-text-100 tabular-nums shrink-0">
                                        {{ $airQualityCounts['online'] }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between gap-4 px-3 py-1">
                                    <span class="flex items-center gap-4 min-w-0">
                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 shadow-[0_0_6px_rgba(251,191,36,0.4)] shrink-0"></span>
                                        <span class="text-sm text-text-300 truncate">Idle</span>
                                    </span>
                                    <span id="aq-idle-count" class="text-sm font-semibold text-text-100 tabular-nums shrink-0">
                                        {{ $airQualityCounts['idle'] }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between gap-4 px-3 py-1">
                                    <span class="flex items-center gap-4 min-w-0">
                                        <span class="w-2.5 h-2.5 rounded-full bg-red-400 shadow-[0_0_6px_rgba(248,113,113,0.4)] shrink-0"></span>
                                        <span class="text-sm text-text-300 truncate">Offline</span>
                                    </span>
                                    <span id="aq-offline-count" class="text-sm font-semibold text-text-100 tabular-nums shrink-0">
                                        {{ $airQualityCounts['offline'] }}
                                    </span>
                                </div>
                            </div>

                            <!-- Summary Badge -->
                            <div class="inline-flex flex-col items-center gap-1 mt-1">
                                <span id="aq-online-badge"
                                    class="inline-flex items-center gap-1.5 text-sm font-medium text-munti-green-400
                                            bg-munti-green-700/20 px-4 py-2.5 rounded-full
                                            border border-munti-green-600/30 shadow-sm whitespace-nowrap">
                                    {{ $airQualityOnline }}/{{ $airQualityTotal }} Online
                                </span>
                                <span class="text-[10px] text-text-500 uppercase tracking-wider whitespace-nowrap">
                                    Station Status
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Seismic Station Status -->
                    <div class="bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden">
                        <div class="px-3 py-2 border-b border-border-700 bg-surface-900/80 flex items-center justify-between gap-2">
                            <h3 class="text-xs font-semibold text-text-200 flex items-center gap-1.5 min-w-0">
                                <span class="truncate">Seismic Station Status</span>
                            </h3>
                        </div>

                        <div class="p-4 sm:p-5 flex flex-col items-center gap-4 w-full min-w-0">
                            <!-- Donut Chart -->
                            <div class="relative w-28 h-28 sm:w-32 sm:h-32 shrink-0">
                                <canvas id="seismicStatusChart"></canvas>
                                <div id="seismic-donut-center" class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    @if($seismicTotal > 0)
                                        <span class="text-lg font-bold text-text-100 leading-none">
                                            {{ round(($seismicOnline / $seismicTotal) * 100) }}%
                                        </span>
                                        <span class="text-[10px] text-text-400 uppercase tracking-wide mt-0.5">Online</span>
                                    @else
                                        <span class="text-lg font-bold text-amber-400 leading-none">—</span>
                                        <span class="text-[10px] text-amber-400 uppercase tracking-wide mt-0.5">No Stations</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Status Counts -->
                            <div class="flex flex-col gap-2 w-full">
                                <div class="flex items-center justify-between gap-4 px-3 py-1">
                                    <span class="flex items-center gap-4 min-w-0">
                                        <span class="w-2.5 h-2.5 rounded-full bg-munti-green-400 shadow-[0_0_6px_rgba(74,222,128,0.45)] shrink-0"></span>
                                        <span class="text-sm text-text-300 truncate">Online</span>
                                    </span>
                                    <span id="seismic-online-count" class="text-sm font-semibold text-text-100 tabular-nums shrink-0">
                                        {{ $seismicCounts['online'] }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between gap-4 px-3 py-1">
                                    <span class="flex items-center gap-4 min-w-0">
                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 shadow-[0_0_6px_rgba(251,191,36,0.4)] shrink-0"></span>
                                        <span class="text-sm text-text-300 truncate">Idle</span>
                                    </span>
                                    <span id="seismic-idle-count" class="text-sm font-semibold text-text-100 tabular-nums shrink-0">
                                        {{ $seismicCounts['idle'] }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between gap-4 px-3 py-1">
                                    <span class="flex items-center gap-4 min-w-0">
                                        <span class="w-2.5 h-2.5 rounded-full bg-red-400 shadow-[0_0_6px_rgba(248,113,113,0.4)] shrink-0"></span>
                                        <span class="text-sm text-text-300 truncate">Offline</span>
                                    </span>
                                    <span id="seismic-offline-count" class="text-sm font-semibold text-text-100 tabular-nums shrink-0">
                                        {{ $seismicCounts['offline'] }}
                                    </span>
                                </div>
                            </div>

                            <!-- Summary Badge -->
                            <div class="inline-flex flex-col items-center gap-1 mt-1">
                                <span id="seismic-online-badge"
                                    class="inline-flex items-center gap-1.5 text-sm font-medium text-munti-green-400
                                            bg-munti-green-700/20 px-4 py-2.5 rounded-full
                                            border border-munti-green-600/30 shadow-sm whitespace-nowrap">
                                    {{ $seismicOnline }}/{{ $seismicTotal }} Online
                                </span>
                                <span class="text-[10px] text-text-500 uppercase tracking-wider whitespace-nowrap">
                                    Station Status
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: Camera Devices Status -->
                    <div class="bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden">
                        <div class="px-3 py-2 border-b border-border-700 bg-surface-900/80 flex items-center justify-between gap-2">
                            <h3 class="text-xs font-semibold text-text-200 flex items-center gap-1.5 min-w-0">
                                <span class="truncate">Camera Devices Status</span>
                            </h3>
                        </div>

                        <div class="p-4 sm:p-5 flex flex-col items-center gap-4 w-full min-w-0">
                            <!-- Donut Chart -->
                            <div class="relative w-28 h-28 sm:w-32 sm:h-32 shrink-0">
                                <canvas id="cameraStatusChart"></canvas>
                                <div id="camera-donut-center" class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    @if($cameraTotal > 0)
                                        <span class="text-lg font-bold text-text-100 leading-none">
                                            {{ $cameraTotal > 0 ? round(($cameraOnline / $cameraTotal) * 100) : 0 }}%
                                        </span>
                                        <span class="text-[10px] text-text-400 uppercase tracking-wide mt-0.5">Online</span>
                                    @else
                                        <span class="text-lg font-bold text-amber-400 leading-none">—</span>
                                        <span class="text-[10px] text-amber-400 uppercase tracking-wide mt-0.5">No Cameras</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Status Counts -->
                            <div class="flex flex-col gap-2 w-full">
                                <div class="flex items-center justify-between gap-4 px-3 py-1">
                                    <span class="flex items-center gap-4 min-w-0">
                                        <span class="w-2.5 h-2.5 rounded-full bg-munti-green-400 shadow-[0_0_6px_rgba(74,222,128,0.45)] shrink-0"></span>
                                        <span class="text-sm text-text-300 truncate">Online</span>
                                    </span>
                                    <span id="camera-online-count" class="text-sm font-semibold text-text-100 tabular-nums shrink-0">
                                        {{ $cameraOnline }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between gap-4 px-3 py-1">
                                    <span class="flex items-center gap-4 min-w-0">
                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 shadow-[0_0_6px_rgba(251,191,36,0.4)] shrink-0"></span>
                                        <span class="text-sm text-text-300 truncate">Idle</span>
                                    </span>
                                    <span id="camera-idle-count" class="text-sm font-semibold text-text-100 tabular-nums shrink-0">
                                        {{ $cameraIdle }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between gap-4 px-3 py-1">
                                    <span class="flex items-center gap-4 min-w-0">
                                        <span class="w-2.5 h-2.5 rounded-full bg-red-400 shadow-[0_0_6px_rgba(248,113,113,0.4)] shrink-0"></span>
                                        <span class="text-sm text-text-300 truncate">Offline</span>
                                    </span>
                                    <span id="camera-offline-count" class="text-sm font-semibold text-text-100 tabular-nums shrink-0">
                                        {{ $cameraOffline }}
                                    </span>
                                </div>
                            </div>

                            <!-- Summary Badge -->
                            <div class="inline-flex flex-col items-center gap-1 mt-1">
                                <span id="camera-online-badge"
                                    class="inline-flex items-center gap-1.5 text-sm font-medium text-munti-green-400
                                            bg-munti-green-700/20 px-4 py-2.5 rounded-full
                                            border border-munti-green-600/30 shadow-sm whitespace-nowrap">
                                    {{ $cameraOnline }}/{{ $cameraTotal }} Online
                                </span>
                                <span class="text-[10px] text-text-500 uppercase tracking-wider whitespace-nowrap">
                                    Camera Status
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CHARTS AND TABLES SECTION -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                    <!-- LEFT COLUMN: Air Quality -->
                    <div class="flex flex-col gap-4">
                        <!-- Graph Card -->
                        <div class="bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden">
                            <div class="px-3 py-2 border-b border-border-700 bg-surface-900/80 flex items-center justify-between gap-2">
                                <h3 class="text-xs font-semibold text-text-200 flex items-center gap-1.5 min-w-0">
                                    <span class="truncate">Air Quality – Total per Station</span>
                                </h3>
                                <span id="aq-chart-total-badge" class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">
                                    {{ count($airQualityData ?? []) }} total
                                </span>
                            </div>
                            <div class="px-2 sm:px-3 pt-3 pb-1 h-[220px] sm:h-[260px] lg:h-[300px]">
                                <canvas id="airQualityChart"></canvas>
                            </div>
                        </div>

                        <!-- Table Card -->
                        <div class="bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden flex flex-col">
                            <div class="px-3 py-2 border-b border-border-700 bg-surface-900/80 flex items-center justify-between gap-2">
                                <h3 class="text-xs font-semibold text-text-200 flex items-center gap-1.5 min-w-0">
                                    <span class="truncate">Air Quality Stations</span>
                                </h3>
                                <span id="aq-table-total-badge" class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">
                                    {{ count($airQualityData ?? []) }} total
                                </span>
                            </div>
                            <div class="overflow-x-auto max-h-[220px] sm:max-h-[250px] thin-scrollbar">
                                <table class="min-w-full divide-y divide-border-700 text-xs">
                                    <thead class="bg-surface-900 sticky top-0">
                                        <tr class="h-10">
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">No.</th>
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Station</th>
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">IP Address</th>
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Installation</th>
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Latest</th>
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Total</th>
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="aq-table-body" class="bg-surface-800 divide-y divide-border-800">
                                        @forelse ($airQualityData as $item)
                                        <tr class="hover:bg-surface-700 transition h-10">
                                            <td class="px-2 py-0 whitespace-nowrap text-text-300">{{ $loop->iteration }}</td>
                                            <td class="px-2 py-0 whitespace-nowrap font-medium text-munti-green-400">{{ $item->station }}</td>
                                            <td class="px-2 py-0 whitespace-nowrap text-munti-green-300">{{ $item->ip ?? '—' }}</td>
                                            <td class="px-2 py-0 whitespace-nowrap text-text-400">
                                                {{ $item->installed_at ? \Carbon\Carbon::parse($item->installed_at)->format('Y-m-d') : '—' }}
                                            </td>
                                            <td class="px-2 py-0 whitespace-nowrap text-text-400">
                                                {{ $item->latest_at ? \Carbon\Carbon::parse($item->latest_at)->format('Y-m-d h:i A') : '—' }}
                                            </td>
                                            <td class="px-2 py-0 whitespace-nowrap text-munti-green-300">{{ number_format($item->total) }}</td>
                                            <td class="px-2 py-0 whitespace-nowrap">
                                                @php($meta = $statusBadgeMeta[$item->status])
                                                <span class="inline-flex items-center gap-1 text-[10px] font-medium {{ $meta['text'] }} {{ $meta['bg'] }} border {{ $meta['border'] }} px-1.5 py-0.5 rounded-full">
                                                    <span class="w-1.5 h-1.5 rounded-full {{ $meta['dot'] }}"></span> {{ $meta['label'] }}
                                                </span>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="7" class="px-2 py-4 text-center text-text-400">No air quality data available</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Seismic -->
                    <div class="flex flex-col gap-4">
                        <!-- Graph Card -->
                        <div class="bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden">
                            <div class="px-3 py-2 border-b border-border-700 bg-surface-900/80 flex items-center justify-between gap-2">
                                <h3 class="text-xs font-semibold text-text-200 flex items-center gap-1.5 min-w-0">
                                    <span class="truncate">Seismic – Total per Station</span>
                                </h3>
                                <span id="seismic-chart-total-badge" class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">
                                    {{ count($seismicData ?? []) }} total
                                </span>
                            </div>
                            <div class="px-2 sm:px-3 pt-3 pb-1 h-[220px] sm:h-[260px] lg:h-[300px]">
                                <canvas id="seismicChart"></canvas>
                            </div>
                        </div>

                        <!-- Table Card -->
                        <div class="bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden flex flex-col">
                            <div class="px-3 py-2 border-b border-border-700 bg-surface-900/80 flex items-center justify-between gap-2">
                                <h3 class="text-xs font-semibold text-text-200 flex items-center gap-1.5 min-w-0">
                                    <span class="truncate">Seismic Stations</span>
                                </h3>
                                <span id="seismic-table-total-badge" class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">
                                    {{ count($seismicData ?? []) }} total
                                </span>
                            </div>
                            <div class="overflow-x-auto max-h-[220px] sm:max-h-[250px] thin-scrollbar">
                                <table class="min-w-full divide-y divide-border-700 text-xs">
                                    <thead class="bg-surface-900 sticky top-0">
                                        <tr class="h-10">
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">No.</th>
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Station</th>
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Station ID</th>
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Installation</th>
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Latest</th>
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Total</th>
                                            <th class="px-2 py-0 text-left font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="seismic-table-body" class="bg-surface-800 divide-y divide-border-800">
                                        @forelse ($seismicData as $item)
                                        <tr class="hover:bg-surface-700 transition h-10">
                                            <td class="px-2 py-0 whitespace-nowrap text-text-300">{{ $loop->iteration }}</td>
                                            <td class="px-2 py-0 whitespace-nowrap font-medium text-munti-green-400">{{ $item->station }}</td>
                                            <td class="px-2 py-0 whitespace-nowrap text-munti-green-300">{{ $item->ip ?? '—' }}</td>
                                            <td class="px-2 py-0 whitespace-nowrap text-text-400">
                                                {{ $item->installed_at ? \Carbon\Carbon::parse($item->installed_at)->format('Y-m-d') : '—' }}
                                            </td>
                                            <td class="px-2 py-0 whitespace-nowrap text-text-400">
                                                {{ $item->latest_at ? \Carbon\Carbon::parse($item->latest_at)->format('Y-m-d h:i A') : '—' }}
                                            </td>
                                            <td class="px-2 py-0 whitespace-nowrap text-munti-green-300">{{ number_format($item->total) }}</td>
                                            <td class="px-2 py-0 whitespace-nowrap">
                                                @php($meta = $statusBadgeMeta[$item->status])
                                                <span class="inline-flex items-center gap-1 text-[10px] font-medium {{ $meta['text'] }} {{ $meta['bg'] }} border {{ $meta['border'] }} px-1.5 py-0.5 rounded-full">
                                                    <span class="w-1.5 h-1.5 rounded-full {{ $meta['dot'] }}"></span> {{ $meta['label'] }}
                                                </span>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="7" class="px-2 py-4 text-center text-text-400">No seismic data available</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <!-- Footer -->
        <div class="px-4 sm:px-6 py-2 bg-surface-800 border-t border-border-800 flex flex-col sm:flex-row justify-between items-center gap-1 text-xs text-text-400">
            <span id="last-updated">Last updated: {{ now()->timezone('Asia/Manila')->format('Y-m-d h:i A') }}</span>
            <span>Data from station database</span>
        </div>
    </div>
</div>

@include('layouts.footer')

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const statusBadgeMeta = {
            online:  { label: 'Online',  text: 'text-munti-green-400', bg: 'bg-munti-green-700/20', border: 'border-munti-green-600/30', dot: 'bg-munti-green-400' },
            idle:    { label: 'Idle',    text: 'text-amber-400',       bg: 'bg-amber-700/20',       border: 'border-amber-600/30',       dot: 'bg-amber-400' },
            offline: { label: 'Offline', text: 'text-red-400',         bg: 'bg-red-700/20',         border: 'border-red-600/30',         dot: 'bg-red-400' },
        };

        function getChartData(collection) {
            if (!Array.isArray(collection)) return { labels: [], totals: [] };
            const labels = collection.map(item => item.station ?? '—');
            const totals = collection.map(item => parseInt(String(item.total ?? 0).replace(/,/g, '')) || 0);
            return { labels, totals };
        }

        function esc(str) {
            const d = document.createElement('div');
            d.textContent = str ?? '';
            return d.innerHTML;
        }

        function fmtDate(str) {
            if (!str) return '—';
            const match = String(str).match(/^(\d{4}-\d{2}-\d{2})/);
            return match ? match[1] : '—';
        }

        function fmtDateTime(str) {
            if (!str) return '—';
            const match = String(str).match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2})/);
            if (!match) return fmtDate(str);
            const [, datePart, hh, mm] = match;
            let hour = parseInt(hh, 10) % 12;
            if (hour === 0) hour = 12;
            const ampm = parseInt(hh, 10) >= 12 ? 'PM' : 'AM';
            return `${datePart} ${String(hour).padStart(2, '0')}:${mm} ${ampm}`;
        }

        const barColors = ['#14B8A6', '#0F766E', '#5EEAD4', '#0B4F3A', '#2DD4BF', '#115E59'];

        const chartDefaults = {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    bottom: 4   // small breathing room for rotated labels
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1A1A1A',
                    titleColor: '#F3F4F6',
                    bodyColor: '#E5E7EB',
                    borderColor: '#374151',
                    borderWidth: 1
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#2B3442' },
                    ticks: { color: '#9CA3AF' }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#9CA3AF',
                        maxRotation: 45,      // rotate when needed
                        minRotation: 0,
                        autoSkip: true,
                        autoSkipPadding: 8,
                        maxTicksLimit: 25,    // don’t show every label if there are 30+
                        font: {
                            size: 10
                        }
                    }
                }
            }
        };

        // Initial data from Blade
        const airData = @json($airQualityData ?? []);
        const seismicDataInit = @json($seismicData ?? []);
        const airChartData = getChartData(airData);
        const seismicChartData = getChartData(seismicDataInit);

        const airChart = new Chart(document.getElementById('airQualityChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: airChartData.labels,
                datasets: [{
                    label: 'Total Records',
                    data: airChartData.totals,
                    backgroundColor: barColors.slice(0, airChartData.labels.length || 1),
                    borderRadius: 6
                }]
            },
            options: chartDefaults
        });

        const seismicChart = new Chart(document.getElementById('seismicChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: seismicChartData.labels,
                datasets: [{
                    label: 'Total Records',
                    data: seismicChartData.totals,
                    backgroundColor: barColors.slice(0, seismicChartData.labels.length || 1),
                    borderRadius: 6
                }]
            },
            options: chartDefaults
        });

        function makeStatusChart(canvasId, online, idle, offline) {
            const el = document.getElementById(canvasId);
            if (!el) return null;
            const total = (online || 0) + (idle || 0) + (offline || 0);
            const labels = total > 0 ? ['Online', 'Idle', 'Offline'] : ['No Stations'];
            const data = total > 0 ? [online || 0, idle || 0, offline || 0] : [1];
            const colors = total > 0 ? ['#2DD4BF', '#FBBF24', '#F87171'] : ['#FBBF24'];
            return new Chart(el.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{ data, backgroundColor: colors, borderColor: '#1E293B', borderWidth: 2 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1A1A1A',
                            titleColor: '#F3F4F6',
                            bodyColor: '#E5E7EB',
                            borderColor: '#374151',
                            borderWidth: 1
                        }
                    }
                }
            });
        }

        function updateStatusChart(chart, online, idle, offline) {
            if (!chart) return;
            const o = online || 0, i = idle || 0, f = offline || 0;
            const total = o + i + f;
            chart.data.labels = total > 0 ? ['Online', 'Idle', 'Offline'] : ['No Stations'];
            chart.data.datasets[0].data = total > 0 ? [o, i, f] : [1];
            chart.data.datasets[0].backgroundColor = total > 0 ? ['#2DD4BF', '#FBBF24', '#F87171'] : ['#FBBF24'];
            chart.update();
        }

        let airStatusChart = makeStatusChart(
            'airQualityStatusChart',
            {{ $airQualityCounts['online'] ?? 0 }},
            {{ $airQualityCounts['idle'] ?? 0 }},
            {{ $airQualityCounts['offline'] ?? 0 }}
        );
        let seismicStatusChart = makeStatusChart(
            'seismicStatusChart',
            {{ $seismicCounts['online'] ?? 0 }},
            {{ $seismicCounts['idle'] ?? 0 }},
            {{ $seismicCounts['offline'] ?? 0 }}
        );
        let cameraStatusChart = makeStatusChart(
            'cameraStatusChart',
            {{ $cameraOnline ?? 0 }},
            {{ $cameraIdle ?? 0 }},
            {{ $cameraOffline ?? 0 }}
        );

        function rowHtml(item, no) {
            const meta = statusBadgeMeta[item.status] || statusBadgeMeta.offline;
            return `<tr class="hover:bg-surface-700 transition h-10">
                <td class="px-2 py-0 whitespace-nowrap text-text-300">${no}</td>
                <td class="px-2 py-0 whitespace-nowrap font-medium text-munti-green-400">${esc(item.station)}</td>
                <td class="px-2 py-0 whitespace-nowrap text-munti-green-300">${esc(item.ip ?? '—')}</td>
                <td class="px-2 py-0 whitespace-nowrap text-text-400">${fmtDate(item.installed_at)}</td>
                <td class="px-2 py-0 whitespace-nowrap text-text-400">${fmtDateTime(item.latest_at)}</td>
                <td class="px-2 py-0 whitespace-nowrap text-munti-green-300">${Number(item.total || 0).toLocaleString()}</td>
                <td class="px-2 py-0 whitespace-nowrap">
                    <span class="inline-flex items-center gap-1 text-[10px] font-medium ${meta.text} ${meta.bg} border ${meta.border} px-1.5 py-0.5 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full ${meta.dot}"></span> ${meta.label}
                    </span>
                </td>
            </tr>`;
        }

        function renderTable(tbodyId, collection, emptyLabel) {
            const tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            if (!Array.isArray(collection) || !collection.length) {
                tbody.innerHTML = `<tr><td colspan="7" class="px-2 py-4 text-center text-text-400">${emptyLabel}</td></tr>`;
                return;
            }
            tbody.innerHTML = collection.map((item, i) => rowHtml(item, i + 1)).join('');
        }

        function setBarTile(prefix, tileData) {
            if (!tileData) return;
            const percentEl = document.getElementById(prefix + '-percent');
            const barEl = document.getElementById(prefix + '-bar');
            if (!percentEl || !barEl) return;

            const pct = Number(tileData.percent) || 0;
            percentEl.textContent = pct + '%';
            percentEl.className = 'text-xs font-semibold ' + (tileData.colors?.text || 'text-text-300');
            barEl.style.width = Math.min(pct, 100) + '%';
            barEl.className = 'h-full rounded-full ' + (tileData.colors?.bar || 'bg-surface-600');
        }

        function initCollapsible(toggleId, bodyId, chevronId, storageKey) {
            const toggle = document.getElementById(toggleId);
            const body = document.getElementById(bodyId);
            const chevron = document.getElementById(chevronId);
            if (!toggle || !body) return;

            function setCollapsed(collapsed) {
                body.classList.toggle('hidden', collapsed);
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                if (chevron) chevron.style.transform = collapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
                try { localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch (e) {}
            }

            let startCollapsed = false;
            try { startCollapsed = localStorage.getItem(storageKey) === '1'; } catch (e) {}
            setCollapsed(startCollapsed);

            toggle.addEventListener('click', () => setCollapsed(!body.classList.contains('hidden')));
        }

        function updateSystemHealth(health) {
            if (!health) return;

            if (health.cpu) {
                setBarTile('cpu', health.cpu);
                const cpuMeta = document.getElementById('cpu-meta');
                if (cpuMeta) {
                    const cores = health.cpu.cores ?? 1;
                    cpuMeta.textContent = `${cores} core${cores > 1 ? 's' : ''} · load ${health.cpu.load ?? '0.00'}`;
                }
            }

            if (health.memory) {
                setBarTile('mem', health.memory);
                const memMeta = document.getElementById('mem-meta');
                if (memMeta) memMeta.textContent = `${health.memory.used ?? '—'} / ${health.memory.total ?? '—'}`;
            }

            if (health.disk) {
                setBarTile('disk', health.disk);
                const diskMeta = document.getElementById('disk-meta');
                if (diskMeta) diskMeta.textContent = `${health.disk.used ?? '—'} / ${health.disk.total ?? '—'}`;
            }

            if (health.uptime) {
                const uptimeEl = document.getElementById('uptime-text');
                if (uptimeEl) {
                    uptimeEl.textContent = `${health.uptime.days ?? 0}d ${health.uptime.hours ?? 0}h ${health.uptime.minutes ?? 0}m`;
                }
            }
        }

        function networkPortIconSvg(active) {
            return active
                ? '<svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>'
                : '<svg class="w-4 h-4 text-text-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>';
        }

        function updateSystemSummary(summary) {
            if (!summary) return;

            const deviceEl = document.getElementById('summary-device');
            if (deviceEl) deviceEl.textContent = summary.device_model ?? '—';

            const cpuEl = document.getElementById('summary-cpu');
            if (cpuEl) cpuEl.textContent = summary.cpu_model ?? '—';

            const osEl = document.getElementById('summary-os');
            if (osEl) osEl.textContent = summary.os_version ?? '—';

            const memEl = document.getElementById('summary-memory');
            if (memEl && summary.memory) {
                memEl.textContent = summary.memory.available
                    ? `${summary.memory.slots_used ?? 0}/${summary.memory.slots_total ?? 0} DIMMs · ${summary.memory.total_label ?? '—'}`
                    : `${summary.memory.total_label ?? '—'} (DIMM count needs sudo)`;
            }

            const storageEl = document.getElementById('summary-storage');
            if (storageEl) storageEl.textContent = summary.storage ?? '—';

            // Network ports – keep original structure & classes so layout doesn’t break
            const portsEl = document.getElementById('summary-network-ports');
            if (portsEl) {
                const ports = summary.network?.ports;
                if (Array.isArray(ports) && ports.length) {
                    portsEl.innerHTML = ports.map(p => `
                        <div class="flex flex-col items-center w-[90px] gap-1.5 shrink-0">
                            <span class="text-[9px] text-text-500 uppercase tracking-wide w-[90px] text-center"
                                title="${esc(p.name)}">${esc(p.name)}</span>
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center ${p.colors?.bg || 'bg-surface-700'}">
                                ${networkPortIconSvg(!!p.active)}
                            </div>
                            <span class="text-[9px] text-text-500 w-[90px] text-center leading-none"
                                title="${esc(p.ip_cidr || 'No IP assigned')}">${esc(p.ip_cidr || '—')}</span>
                        </div>
                    `).join('');
                } else {
                    portsEl.innerHTML = '<span class="text-xs text-text-500">No network interfaces detected</span>';
                }
            }
        }

        function updateStatusBanner(airCounts, seismicCounts) {
            const a = airCounts || { online: 0, idle: 0, offline: 0 };
            const s = seismicCounts || { online: 0, idle: 0, offline: 0 };

            const totalOnline = (a.online || 0) + (s.online || 0);
            const totalStations = (a.online || 0) + (a.idle || 0) + (a.offline || 0)
                                + (s.online || 0) + (s.idle || 0) + (s.offline || 0);
            const percent = totalStations > 0 ? Math.round((totalOnline / totalStations) * 1000) / 10 : 100;

            let status = 'idle';
            if (totalStations === 0) status = 'idle';
            else if (percent >= 100) status = 'good';
            else if (percent >= 80) status = 'warning';
            else status = 'critical';

            const meta = {
                idle:     { label: 'No Stations Configured', text: 'text-amber-400', bg: 'bg-amber-700/10', border: 'border-amber-600/30', dot: 'bg-amber-400' },
                good:     { label: 'All Systems Good', text: 'text-munti-green-400', bg: 'bg-munti-green-700/10', border: 'border-munti-green-600/30', dot: 'bg-munti-green-400' },
                warning:  { label: 'Warning', text: 'text-amber-400', bg: 'bg-amber-700/10', border: 'border-amber-600/30', dot: 'bg-amber-400' },
                critical: { label: 'Critical', text: 'text-red-400', bg: 'bg-red-700/10', border: 'border-red-600/30', dot: 'bg-red-400' },
            }[status];

            const banner = document.getElementById('system-status-banner');
            const ping   = document.getElementById('system-status-ping');
            const dot    = document.getElementById('system-status-dot');
            const label  = document.getElementById('system-status-label');
            const count  = document.getElementById('system-status-count');

            // Keep original classes – do NOT invent lg:col-span-2
            if (banner) banner.className = `rounded-xl border ${meta.border} ${meta.bg} overflow-hidden`;
            if (ping) {
                ping.className = `animate-ping absolute inline-flex h-full w-full rounded-full ${meta.dot} opacity-60`;
                ping.style.display = status === 'good' ? 'none' : '';
            }
            if (dot)   dot.className = `relative inline-flex rounded-full h-2.5 w-2.5 ${meta.dot}`;
            if (label) {
                label.textContent = meta.label;
                label.className = `text-sm font-semibold ${meta.text}`;
            }
            if (count) {
                count.textContent = totalStations === 0
                    ? 'No stations added yet'
                    : `${totalOnline}/${totalStations} stations online (${percent}%)`;
            }
        }

        function updateTotalBadges(prefix, count) {
            const n = Number(count) || 0;
            const chartBadge = document.getElementById(prefix + '-chart-total-badge');
            const tableBadge = document.getElementById(prefix + '-table-total-badge');
            if (chartBadge) chartBadge.textContent = `${n} total`;
            if (tableBadge) tableBadge.textContent = `${n} total`;
        }

        function updateDonutCard(prefix, counts) {
            const c = counts || { online: 0, idle: 0, offline: 0 };
            const total = (c.online || 0) + (c.idle || 0) + (c.offline || 0);

            const center = document.getElementById(prefix + '-donut-center');
            if (center) {
                const emptyLabel = prefix === 'camera' ? 'No Cameras' : 'No Stations';
                // Keep same size/classes as the original Blade render
                center.innerHTML = total > 0
                    ? `<span class="text-lg font-bold text-text-100 leading-none">${Math.round((c.online / total) * 100)}%</span>
                    <span class="text-[10px] text-text-400 uppercase tracking-wide mt-0.5">Online</span>`
                    : `<span class="text-lg font-bold text-amber-400 leading-none">—</span>
                    <span class="text-[10px] text-amber-400 uppercase tracking-wide mt-0.5">${emptyLabel}</span>`;
            }

            const badge = document.getElementById(prefix + '-online-badge');
            if (badge) badge.textContent = `${c.online || 0}/${total} Online`;

            const onlineEl  = document.getElementById(prefix + '-online-count');
            const idleEl    = document.getElementById(prefix + '-idle-count');
            const offlineEl = document.getElementById(prefix + '-offline-count');
            if (onlineEl)  onlineEl.textContent  = c.online  || 0;
            if (idleEl)    idleEl.textContent    = c.idle    || 0;
            if (offlineEl) offlineEl.textContent = c.offline || 0;
        }

        // ---------- MAIN REFRESH ----------
        async function refreshDashboard() {
            const url = document.getElementById('main-content')?.dataset.refreshUrl;
            if (!url) return;

            try {
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();

                // Safe defaults so missing keys never crash the whole refresh
                const airQualityData  = Array.isArray(data.airQualityData)  ? data.airQualityData  : [];
                const seismicData     = Array.isArray(data.seismicData)     ? data.seismicData     : [];
                const airCounts       = data.airQualityCounts  || { online: 0, idle: 0, offline: 0 };
                const seismicCounts   = data.seismicCounts     || { online: 0, idle: 0, offline: 0 };
                const cameraCounts    = data.cameraCounts      || { online: 0, idle: 0, offline: 0 };

                // Tables
                renderTable('aq-table-body', airQualityData, 'No air quality data available');
                renderTable('seismic-table-body', seismicData, 'No seismic data available');
                updateTotalBadges('aq', airQualityData.length);
                updateTotalBadges('seismic', seismicData.length);

                // Bar charts
                const airChartData2 = getChartData(airQualityData);
                airChart.data.labels = airChartData2.labels;
                airChart.data.datasets[0].data = airChartData2.totals;
                airChart.data.datasets[0].backgroundColor = barColors.slice(0, airChartData2.labels.length || 1);
                airChart.update();

                const seismicChartData2 = getChartData(seismicData);
                seismicChart.data.labels = seismicChartData2.labels;
                seismicChart.data.datasets[0].data = seismicChartData2.totals;
                seismicChart.data.datasets[0].backgroundColor = barColors.slice(0, seismicChartData2.labels.length || 1);
                seismicChart.update();

                // Donut charts
                updateStatusChart(airStatusChart, airCounts.online, airCounts.idle, airCounts.offline);
                updateStatusChart(seismicStatusChart, seismicCounts.online, seismicCounts.idle, seismicCounts.offline);
                updateStatusChart(cameraStatusChart, cameraCounts.online, cameraCounts.idle, cameraCounts.offline);

                // Status cards content
                updateDonutCard('aq', airCounts);
                updateDonutCard('seismic', seismicCounts);
                updateDonutCard('camera', cameraCounts);

                // Banner
                updateStatusBanner(airCounts, seismicCounts);

                // System tiles (safe even if endpoint doesn’t send them yet)
                updateSystemHealth(data.systemHealth);
                updateSystemSummary(data.systemSummary);

                // Timestamp
                const lastUpdated = document.getElementById('last-updated');
                if (lastUpdated && data.generatedAt) {
                    lastUpdated.textContent = `Last updated: ${data.generatedAt}`;
                }
            } catch (e) {
                console.error('Dashboard refresh failed:', e);
            }
        }

        // Collapsible cards
        initCollapsible('system-health-toggle', 'system-health-body', 'system-health-chevron', 'dashboard.system-health.collapsed');
        initCollapsible('system-summary-toggle', 'system-summary-body', 'system-summary-chevron', 'dashboard.system-summary.collapsed');

        // Auto-refresh every 20 seconds
        setInterval(refreshDashboard, 20000);
    });
</script>