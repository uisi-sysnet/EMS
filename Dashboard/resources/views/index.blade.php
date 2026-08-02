@include('layouts.header')
@include('layouts.topbar')

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
     class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">

    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                <span class="leading-tight uppercase">Dashboard</span>
            </h2>
            <span class="text-xs text-text-400">Overview of stations</span>
        </div>

        <!-- Body – scrollable -->
        <div class="flex-1 p-3 sm:p-4 overflow-y-auto thin-scrollbar min-h-0 bg-background-900">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- LEFT COLUMN: Air Quality -->
                <div class="flex flex-col gap-4">
                    <!-- Graph Card -->
                    <div class="bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden">
                        <div class="px-3 py-2 border-b border-border-700 bg-surface-900/80 flex items-center justify-between gap-2">
                            <h3 class="text-xs font-semibold text-text-200 flex items-center gap-1.5 min-w-0">
                                <span class="truncate">Air Quality – Total per Station</span>
                            </h3>
                            <span class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">
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
                            <span class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">
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
                                    </tr>
                                </thead>
                                <tbody class="bg-surface-800 divide-y divide-border-800">
                                    @forelse ($airQualityData ?? [] as $item)
                                    <tr class="hover:bg-surface-700 transition h-10">
                                        <td class="px-2 py-0 whitespace-nowrap text-text-300">{{ $loop->iteration }}</td>
                                        <td class="px-2 py-0 whitespace-nowrap font-medium text-text-100">{{ $item->station }}</td>
                                        <td class="px-2 py-0 whitespace-nowrap text-text-400">{{ $item->ip }}</td>
                                        <td class="px-2 py-0 whitespace-nowrap text-text-400">
                                            {{ \Carbon\Carbon::parse($item->installed_at)->format('Y-m-d') }}
                                        </td>
                                        <td class="px-2 py-0 whitespace-nowrap text-text-400">
                                            {{ \Carbon\Carbon::parse($item->latest_at)->format('Y-m-d') }}
                                        </td>
                                        <td class="px-2 py-0 whitespace-nowrap text-text-300">{{ number_format($item->total) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="px-2 py-4 text-center text-text-400">No air quality data available</td>
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
                            <span class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">
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
                            <span class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">
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
                                    </tr>
                                </thead>
                                <tbody class="bg-surface-800 divide-y divide-border-800">
                                    @forelse ($seismicData ?? [] as $item)
                                    <tr class="hover:bg-surface-700 transition h-10">
                                        <td class="px-2 py-0 whitespace-nowrap text-text-300">{{ $loop->iteration }}</td>
                                        <td class="px-2 py-0 whitespace-nowrap font-medium text-text-100">{{ $item->station }}</td>
                                        <td class="px-2 py-0 whitespace-nowrap text-text-400">{{ $item->ip }}</td>
                                        <td class="px-2 py-0 whitespace-nowrap text-text-400">
                                            {{ \Carbon\Carbon::parse($item->installed_at)->format('Y-m-d') }}
                                        </td>
                                        <td class="px-2 py-0 whitespace-nowrap text-text-400">
                                            {{ \Carbon\Carbon::parse($item->latest_at)->format('Y-m-d') }}
                                        </td>
                                        <td class="px-2 py-0 whitespace-nowrap text-text-300">{{ number_format($item->total) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="px-2 py-4 text-center text-text-400">No seismic data available</td>
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
            <span>Last updated: {{ now()->timezone('Asia/Manila')->format('Y-m-d h:i A') }}</span>
            <span>Data from station database</span>
        </div>
    </div>
</div>

@include('layouts.footer')

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
 document.addEventListener('DOMContentLoaded', function() {
    function getChartData(collection) {
        let labels = collection.map(item => item.station);
        let totals = collection.map(item => parseInt(String(item.total).replace(/,/g, '')) || 0);
        return { labels, totals };
    }

    const airData = @json($airQualityData ?? []);
    const seismicData = @json($seismicData ?? []);

    const airChartData = getChartData(airData);
    const seismicChartData = getChartData(seismicData);

    // Color palette (dynamically sized)
    const colors = ['#14B8A6', '#0F766E', '#5EEAD4', '#0B4F3A', '#2DD4BF', '#115E59'];

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
            y: {
                beginAtZero: true,
                grid: { color: '#2B3442' },
                ticks: { color: '#9CA3AF' }
            },
            x: {
                grid: { display: false },
                ticks: { color: '#9CA3AF' }
            }
        }
    };

    // Air Quality Chart
    const ctx1 = document.getElementById('airQualityChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: airChartData.labels,
            datasets: [{
                label: 'Total Records',
                data: airChartData.totals,
                backgroundColor: colors.slice(0, airChartData.labels.length || 1),
                borderRadius: 6,
            }]
        },
        options: chartDefaults
    });

    // Seismic Chart
    const ctx2 = document.getElementById('seismicChart').getContext('2d');
    new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: seismicChartData.labels,
            datasets: [{
                label: 'Total Records',
                data: seismicChartData.totals,
                backgroundColor: colors.slice(0, seismicChartData.labels.length || 1),
                borderRadius: 6,
            }]
        },
        options: chartDefaults
    });
});
</script>