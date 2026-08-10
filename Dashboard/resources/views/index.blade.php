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
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- SYSTEM STATUS BANNER -->
                <div id="system-status-banner" class="lg:col-span-2 rounded-xl border {{ $systemStatusMeta['border'] }} {{ $systemStatusMeta['bg'] }} overflow-hidden">
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
                <div class="lg:col-span-2 bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden">
                    <div class="px-3 py-2 border-b border-border-700 bg-surface-900/80 flex items-center justify-between gap-2">
                        <h3 class="text-xs font-semibold text-text-200 flex items-center gap-1.5 min-w-0">
                            <span class="truncate">System Health</span>
                        </h3>
                        <span class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">
                            Live
                        </span>
                    </div>
                    <div class="p-3 sm:p-4 grid grid-cols-2 sm:grid-cols-4 gap-3">

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

                <!-- Air Quality Station Status -->
                <div class="bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden">
                    <div class="px-3 py-2 border-b border-border-700 bg-surface-900/80 flex items-center justify-between gap-2">
                        <h3 class="text-xs font-semibold text-text-200 flex items-center gap-1.5 min-w-0">
                            <span class="truncate">Air Quality Station Status</span>
                        </h3>
                        <span id="aq-online-badge" class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">
                            {{ $airQualityOnline }}/{{ $airQualityTotal }} online
                        </span>
                    </div>
                    <div class="p-3 sm:p-4 flex items-center gap-4">
                        <div class="relative w-24 h-24 sm:w-28 sm:h-28 shrink-0">
                            <canvas id="airQualityStatusChart"></canvas>
                            <div id="aq-donut-center" class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                @if($airQualityTotal > 0)
                                <span class="text-sm sm:text-base font-bold text-text-100">{{ round(($airQualityOnline / $airQualityTotal) * 100) }}%</span>
                                <span class="text-[9px] text-text-400 uppercase">Online</span>
                                @else
                                <span class="text-sm sm:text-base font-bold text-amber-400">—</span>
                                <span class="text-[9px] text-amber-400 uppercase">No Stations</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5 text-xs w-full">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-munti-green-400"></span>
                                <span class="text-text-300">Online</span>
                                <span id="aq-online-count" class="text-text-100 font-semibold ml-auto">{{ $airQualityCounts['online'] }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                <span class="text-text-300">Idle</span>
                                <span id="aq-idle-count" class="text-text-100 font-semibold ml-auto">{{ $airQualityCounts['idle'] }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                <span class="text-text-300">Offline</span>
                                <span id="aq-offline-count" class="text-text-100 font-semibold ml-auto">{{ $airQualityCounts['offline'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seismic Station Status -->
                <div class="bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden">
                    <div class="px-3 py-2 border-b border-border-700 bg-surface-900/80 flex items-center justify-between gap-2">
                        <h3 class="text-xs font-semibold text-text-200 flex items-center gap-1.5 min-w-0">
                            <span class="truncate">Seismic Station Status</span>
                        </h3>
                        <span id="seismic-online-badge" class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">
                            {{ $seismicOnline }}/{{ $seismicTotal }} online
                        </span>
                    </div>
                    <div class="p-3 sm:p-4 flex items-center gap-4">
                        <div class="relative w-24 h-24 sm:w-28 sm:h-28 shrink-0">
                            <canvas id="seismicStatusChart"></canvas>
                            <div id="seismic-donut-center" class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                @if($seismicTotal > 0)
                                <span class="text-sm sm:text-base font-bold text-text-100">{{ round(($seismicOnline / $seismicTotal) * 100) }}%</span>
                                <span class="text-[9px] text-text-400 uppercase">Online</span>
                                @else
                                <span class="text-sm sm:text-base font-bold text-amber-400">—</span>
                                <span class="text-[9px] text-amber-400 uppercase">No Stations</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5 text-xs w-full">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-munti-green-400"></span>
                                <span class="text-text-300">Online</span>
                                <span id="seismic-online-count" class="text-text-100 font-semibold ml-auto">{{ $seismicCounts['online'] }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                <span class="text-text-300">Idle</span>
                                <span id="seismic-idle-count" class="text-text-100 font-semibold ml-auto">{{ $seismicCounts['idle'] }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                <span class="text-text-300">Offline</span>
                                <span id="seismic-offline-count" class="text-text-100 font-semibold ml-auto">{{ $seismicCounts['offline'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

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
                                            {{ $item->latest_at ? \Carbon\Carbon::parse($item->latest_at)->format('Y-m-d') : '—' }}
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
                                            {{ $item->latest_at ? \Carbon\Carbon::parse($item->latest_at)->format('Y-m-d') : '—' }}
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
    document.addEventListener('DOMContentLoaded', function() {
        const statusBadgeMeta = {
            online:  { label: 'Online',  text: 'text-munti-green-400', bg: 'bg-munti-green-700/20', border: 'border-munti-green-600/30', dot: 'bg-munti-green-400' },
            idle:    { label: 'Idle',    text: 'text-amber-400',       bg: 'bg-amber-700/20',       border: 'border-amber-600/30',       dot: 'bg-amber-400' },
            offline: { label: 'Offline', text: 'text-red-400',         bg: 'bg-red-700/20',         border: 'border-red-600/30',         dot: 'bg-red-400' },
        };

        function getChartData(collection) {
            let labels = collection.map(item => item.station);
            let totals = collection.map(item => parseInt(String(item.total).replace(/,/g, '')) || 0);
            return { labels, totals };
        }

        function esc(str) {
            const d = document.createElement('div');
            d.textContent = str ?? '';
            return d.innerHTML;
        }

        function fmtDate(str) {
            if (!str) return '—';
            const d = new Date(str.replace(' ', 'T'));
            if (isNaN(d)) return '—';
            return d.toISOString().slice(0, 10);
        }

        // Color palette (dynamically sized)
        const barColors = ['#14B8A6', '#0F766E', '#5EEAD4', '#0B4F3A', '#2DD4BF', '#115E59'];

        const chartDefaults = {
            responsive: true,
            maintainAspectRatio: false,
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
                y: { beginAtZero: true, grid: { color: '#2B3442' }, ticks: { color: '#9CA3AF' } },
                x: { grid: { display: false }, ticks: { color: '#9CA3AF' } }
            }
        };

        const airData = @json($airQualityData ?? []);
        const seismicDataInit = @json($seismicData ?? []);
        const airChartData = getChartData(airData);
        const seismicChartData = getChartData(seismicDataInit);

        const airChart = new Chart(document.getElementById('airQualityChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: airChartData.labels,
                datasets: [{ label: 'Total Records', data: airChartData.totals, backgroundColor: barColors.slice(0, airChartData.labels.length || 1), borderRadius: 6 }]
            },
            options: chartDefaults
        });

        const seismicChart = new Chart(document.getElementById('seismicChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: seismicChartData.labels,
                datasets: [{ label: 'Total Records', data: seismicChartData.totals, backgroundColor: barColors.slice(0, seismicChartData.labels.length || 1), borderRadius: 6 }]
            },
            options: chartDefaults
        });

        function makeStatusChart(canvasId, online, idle, offline) {
            const el = document.getElementById(canvasId);
            if (!el) return null;
            const total = online + idle + offline;
            const labels = total > 0 ? ['Online', 'Idle', 'Offline'] : ['No Stations'];
            const data = total > 0 ? [online, idle, offline] : [1];
            const colors = total > 0 ? ['#2DD4BF', '#FBBF24', '#F87171'] : ['#FBBF24'];
            return new Chart(el.getContext('2d'), {
                type: 'doughnut',
                data: { labels: labels, datasets: [{ data: data, backgroundColor: colors, borderColor: '#1E293B', borderWidth: 2 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '72%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { backgroundColor: '#1A1A1A', titleColor: '#F3F4F6', bodyColor: '#E5E7EB', borderColor: '#374151', borderWidth: 1 }
                    }
                }
            });
        }

        function updateStatusChart(chart, online, idle, offline) {
            if (!chart) return;
            const total = online + idle + offline;
            chart.data.labels = total > 0 ? ['Online', 'Idle', 'Offline'] : ['No Stations'];
            chart.data.datasets[0].data = total > 0 ? [online, idle, offline] : [1];
            chart.data.datasets[0].backgroundColor = total > 0 ? ['#2DD4BF', '#FBBF24', '#F87171'] : ['#FBBF24'];
            chart.update();
        }

        let airStatusChart = makeStatusChart('airQualityStatusChart', {{ $airQualityCounts['online'] }}, {{ $airQualityCounts['idle'] }}, {{ $airQualityCounts['offline'] }});
        let seismicStatusChart = makeStatusChart('seismicStatusChart', {{ $seismicCounts['online'] }}, {{ $seismicCounts['idle'] }}, {{ $seismicCounts['offline'] }});

        function rowHtml(item, no) {
            const meta = statusBadgeMeta[item.status] || statusBadgeMeta.offline;
            return `<tr class="hover:bg-surface-700 transition h-10">
                <td class="px-2 py-0 whitespace-nowrap text-text-300">${no}</td>
                <td class="px-2 py-0 whitespace-nowrap font-medium text-munti-green-400">${esc(item.station)}</td>
                <td class="px-2 py-0 whitespace-nowrap text-munti-green-300">${esc(item.ip ?? '—')}</td>
                <td class="px-2 py-0 whitespace-nowrap text-text-400">${fmtDate(item.installed_at)}</td>
                <td class="px-2 py-0 whitespace-nowrap text-text-400">${fmtDate(item.latest_at)}</td>
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
            if (!collection.length) {
                tbody.innerHTML = `<tr><td colspan="7" class="px-2 py-4 text-center text-text-400">${emptyLabel}</td></tr>`;
                return;
            }
            tbody.innerHTML = collection.map((item, i) => rowHtml(item, i + 1)).join('');
        }

        function setBarTile(prefix, tileData) {
            const percentEl = document.getElementById(prefix + '-percent');
            const barEl = document.getElementById(prefix + '-bar');
            if (!percentEl || !barEl || !tileData) return;
            percentEl.textContent = tileData.percent + '%';
            percentEl.className = 'text-xs font-semibold ' + tileData.colors.text;
            barEl.style.width = Math.min(tileData.percent, 100) + '%';
            barEl.className = 'h-full rounded-full ' + tileData.colors.bar;
        }

        function updateSystemHealth(health) {
            if (!health) return;
            setBarTile('cpu', health.cpu);
            const cpuMeta = document.getElementById('cpu-meta');
            if (cpuMeta) cpuMeta.textContent = `${health.cpu.cores} core${health.cpu.cores > 1 ? 's' : ''} · load ${health.cpu.load}`;

            setBarTile('mem', health.memory);
            const memMeta = document.getElementById('mem-meta');
            if (memMeta) memMeta.textContent = `${health.memory.used} / ${health.memory.total}`;

            setBarTile('disk', health.disk);
            const diskMeta = document.getElementById('disk-meta');
            if (diskMeta) diskMeta.textContent = `${health.disk.used} / ${health.disk.total}`;

            const uptimeEl = document.getElementById('uptime-text');
            if (uptimeEl) uptimeEl.textContent = `${health.uptime.days}d ${health.uptime.hours}h ${health.uptime.minutes}m`;
        }

        function updateStatusBanner(airCounts, seismicCounts) {
            const totalOnline = airCounts.online + seismicCounts.online;
            const totalStations = airCounts.online + airCounts.idle + airCounts.offline
                + seismicCounts.online + seismicCounts.idle + seismicCounts.offline;
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
            const ping = document.getElementById('system-status-ping');
            const dot = document.getElementById('system-status-dot');
            const label = document.getElementById('system-status-label');
            const count = document.getElementById('system-status-count');

            if (banner) banner.className = `lg:col-span-2 rounded-xl border ${meta.border} ${meta.bg} overflow-hidden`;
            if (ping) { ping.className = `animate-ping absolute inline-flex h-full w-full rounded-full ${meta.dot} opacity-60`; ping.style.display = status === 'good' ? 'none' : ''; }
            if (dot) dot.className = `relative inline-flex rounded-full h-2.5 w-2.5 ${meta.dot}`;
            if (label) { label.textContent = meta.label; label.className = `text-sm font-semibold ${meta.text}`; }
            if (count) count.textContent = totalStations === 0 ? 'No stations added yet' : `${totalOnline}/${totalStations} stations online (${percent}%)`;
        }

        function updateDonutCard(prefix, counts) {
            const total = counts.online + counts.idle + counts.offline;
            const center = document.getElementById(prefix + '-donut-center');
            if (center) {
                center.innerHTML = total > 0
                    ? `<span class="text-sm sm:text-base font-bold text-text-100">${Math.round((counts.online / total) * 100)}%</span><span class="text-[9px] text-text-400 uppercase">Online</span>`
                    : `<span class="text-sm sm:text-base font-bold text-amber-400">—</span><span class="text-[9px] text-amber-400 uppercase">No Stations</span>`;
            }
            const badge = document.getElementById(prefix + '-online-badge');
            if (badge) badge.textContent = `${counts.online}/${total} online`;
            const onlineEl = document.getElementById(prefix + '-online-count');
            const idleEl = document.getElementById(prefix + '-idle-count');
            const offlineEl = document.getElementById(prefix + '-offline-count');
            if (onlineEl) onlineEl.textContent = counts.online;
            if (idleEl) idleEl.textContent = counts.idle;
            if (offlineEl) offlineEl.textContent = counts.offline;
        }

        function updateTotalBadges(prefix, count) {
            const chartBadge = document.getElementById(prefix + '-chart-total-badge');
            const tableBadge = document.getElementById(prefix + '-table-total-badge');
            if (chartBadge) chartBadge.textContent = `${count} total`;
            if (tableBadge) tableBadge.textContent = `${count} total`;
        }

        async function refreshDashboard() {
            const url = document.getElementById('main-content')?.dataset.refreshUrl;
            if (!url) return;
            try {
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();

                renderTable('aq-table-body', data.airQualityData, 'No air quality data available');
                renderTable('seismic-table-body', data.seismicData, 'No seismic data available');
                updateTotalBadges('aq', data.airQualityData.length);
                updateTotalBadges('seismic', data.seismicData.length);

                const airChartData2 = getChartData(data.airQualityData);
                airChart.data.labels = airChartData2.labels;
                airChart.data.datasets[0].data = airChartData2.totals;
                airChart.data.datasets[0].backgroundColor = barColors.slice(0, airChartData2.labels.length || 1);
                airChart.update();

                const seismicChartData2 = getChartData(data.seismicData);
                seismicChart.data.labels = seismicChartData2.labels;
                seismicChart.data.datasets[0].data = seismicChartData2.totals;
                seismicChart.data.datasets[0].backgroundColor = barColors.slice(0, seismicChartData2.labels.length || 1);
                seismicChart.update();

                updateStatusChart(airStatusChart, data.airQualityCounts.online, data.airQualityCounts.idle, data.airQualityCounts.offline);
                updateStatusChart(seismicStatusChart, data.seismicCounts.online, data.seismicCounts.idle, data.seismicCounts.offline);
                updateDonutCard('aq', data.airQualityCounts);
                updateDonutCard('seismic', data.seismicCounts);
                updateStatusBanner(data.airQualityCounts, data.seismicCounts);
                updateSystemHealth(data.systemHealth);

                const lastUpdated = document.getElementById('last-updated');
                if (lastUpdated) lastUpdated.textContent = `Last updated: ${data.generatedAt}`;
            } catch (e) {
                console.error('Dashboard refresh failed:', e);
            }
        }

        setInterval(refreshDashboard, 20000);
    });
</script>