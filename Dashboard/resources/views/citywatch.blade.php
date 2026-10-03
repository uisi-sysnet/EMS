@include('layouts.header')
@include('layouts.topbar')

<div id="citywatch-content"
     data-refresh-url="{{ route('citywatch.data') }}"
     class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">

    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                <span class="leading-tight uppercase">CityWatch</span>
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-xs text-text-400">Live station map — sensors, cameras and network</span>
                <span id="citywatch-updated" class="text-xs text-text-500">Updated {{ now()->timezone('Asia/Manila')->format('Y-m-d h:i A') }}</span>
            </div>
        </div>

        <!-- Map fills the rest of the page -->
        <div class="flex-1 min-h-0 p-3 sm:p-4 bg-background-900">
            @include('partials.station-map')
        </div>
    </div>
</div>

@include('layouts.footer')

<script>
    // Refresh the map every 20 seconds, same cadence as the dashboard.
    (function () {
        const root = document.getElementById('citywatch-content');
        const url = root?.dataset.refreshUrl;
        if (!url) return;

        async function refreshCityWatch() {
            try {
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                if (window.updateStationMap) window.updateStationMap(data.mapStations);
                if (data.generatedAt) document.getElementById('citywatch-updated').textContent = `Updated ${data.generatedAt}`;
            } catch (e) {
                console.error('CityWatch refresh failed:', e);
            }
        }

        setInterval(refreshCityWatch, 20000);
    })();
</script>
