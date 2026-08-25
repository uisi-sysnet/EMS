@include('layouts.header')
@include('layouts.topbar')

<style>
    .thin-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
    .thin-scrollbar::-webkit-scrollbar-track { background: #1A1A1A; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb { background: #4B5563; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb:hover { background: #6B7280; }
    .thin-scrollbar { scrollbar-width: thin; scrollbar-color: #4B5563 #1A1A1A; }
</style>

<!-- Edit Station Modal -->
<div id="editModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-surface-800 rounded-2xl border border-border-700 shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto thin-scrollbar">
        <div class="sticky top-0 bg-surface-800/95 backdrop-blur-sm px-6 py-4 border-b border-border-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-radar-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Station
            </h3>
            <button type="button" onclick="closeEditModal()" class="p-2 rounded-lg hover:bg-surface-700 text-text-400 hover:text-text-100 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        <form id="editForm" method="POST" class="p-6">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Station MN -->
                <div class="flex flex-col">
                    <label for="edit_station_mn" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Station MN <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text"
                        id="edit_station_mn"
                        name="station_mn"
                        required
                        maxlength="14"
                        autocomplete="off"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="Enter Station MN">
                </div>
                <!-- Station Name -->
                <div class="flex flex-col">
                    <label for="edit_station_name" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Station Name
                    </label>
                    <input type="text" id="edit_station_name" name="station_name"
                        maxlength="32" class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                </div>
                <!-- Enabled -->
                <div class="flex flex-col">
                    <label class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Enabled
                    </label>
                    <div class="flex items-center h-11 px-3.5">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" id="edit_enabled" name="enabled" value="1"
                            class="h-4 w-4 rounded border-border-600 bg-surface-900 text-munti-green-600 focus:ring-munti-green-500 focus:ring-offset-0">
                        <label for="edit_enabled" class="ml-2 text-sm text-text-300">Enable this station</label>
                    </div>
                </div>
                <!-- Latitude -->
                <div class="flex flex-col">
                    <label for="edit_latitude" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Latitude
                    </label>
                    <input type="number"
                        step="any"
                        min="4.5"
                        max="21.5"
                        id="edit_latitude"
                        name="latitude"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                </div>
                <!-- Longitude -->
                <div class="flex flex-col">
                    <label for="edit_longitude" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Longitude
                    </label>
                    <input type="number"
                        step="any"
                        min="116.0"
                        max="127.0"
                        id="edit_longitude"
                        name="longitude"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                </div>
                <!-- Lead IP -->
                <div class="flex flex-col">
                    <label for="edit_lead_ip" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        IP Address
                    </label>
                    <input type="text"
                        id="edit_lead_ip"
                        name="lead_ip"
                        required
                        maxlength="15"
                        pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                        inputmode="decimal"
                        autocomplete="off"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="e.g. 192.168.1.10"
                        oninput="
                            let v = this.value.replace(/[^0-9.]/g, '');
                            const parts = v.split('.');
                            if (parts.length > 4) {
                                v = parts.slice(0, 4).join('.');
                            }
                            v = parts.slice(0, 4).map(p => p.slice(0, 3)).join('.');
                            this.value = v;
                        ">
                </div>
                <!-- Lead Port - Auto-filled with 8899 -->
                <div class="flex flex-col">
                    <label for="edit_lead_port" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Port <span class="text-text-500">(Auto: 8899)</span>
                    </label>
                    <input type="number" id="edit_lead_port" name="lead_port" value="8899"
                        readonly
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-800 text-text-400 placeholder-text-500 text-sm transition cursor-not-allowed opacity-75">
                </div>
                <!-- Lead Slave - Auto-filled with 1 -->
                <div class="flex flex-col">
                    <label for="edit_lead_slave" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Slave <span class="text-text-500">(Auto: 1)</span>
                    </label>
                    <input type="number" id="edit_lead_slave" name="lead_slave" value="1"
                        readonly
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-800 text-text-400 placeholder-text-500 text-sm transition cursor-not-allowed opacity-75">
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-border-700 flex justify-end gap-3">
                <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2.5 text-sm font-medium text-text-300 hover:text-text-100 bg-surface-700 hover:bg-surface-600 rounded-lg transition border border-border-600">
                    Cancel
                </button>
                <button type="submit"
                        class="px-6 py-2.5 bg-radar-500 hover:bg-radar-400 text-text-100 font-semibold rounded-lg transition border border-radar-400/30 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Update Station
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Deleted Stations Modal -->
<div id="deletedModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-surface-800 rounded-2xl border border-border-700 shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col">
        
        <!-- Header -->
        <div class="sticky top-0 bg-surface-800/95 backdrop-blur-sm px-6 py-4 border-b border-border-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-munti-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Deleted Stations
            </h3>
            <button type="button" onclick="closeDeletedModal()" class="p-2 rounded-lg hover:bg-surface-700 text-text-400 hover:text-text-100 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <!-- Content -->
        <div class="overflow-y-auto thin-scrollbar flex-1 p-0">
            @if($deletedStations->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border-700">
                        <thead class="bg-surface-900/60 text-[11px] uppercase tracking-wider text-text-500 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">MN</th>
                                <th class="px-4 py-3 text-left font-medium">Name</th>
                                <th class="px-4 py-3 text-left font-medium">Enabled</th>
                                <th class="px-4 py-3 text-left font-medium">Data Status</th>
                                <th class="px-4 py-3 text-left font-medium">Latitude</th>
                                <th class="px-4 py-3 text-left font-medium">Longitude</th>
                                <th class="px-4 py-3 text-left font-medium">IP Address</th>
                                <th class="px-4 py-3 text-left font-medium">Updated At</th>
                                <th class="px-4 py-3 text-center font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-800">
                            @foreach($deletedStations as $station)
                                <tr class="hover:bg-surface-700/50 transition">
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-munti-red-400">
                                        {{ $station->station_mn }}
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-200">
                                        {{ $station->station_name ?? '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border
                                            {{ $station->enabled
                                                ? 'bg-munti-green-700/15 text-munti-green-400 border-munti-green-600/30'
                                                : 'bg-munti-red-700/15 text-munti-red-400 border-munti-red-600/30' }}">
                                            {{ $station->enabled ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        @if($station->sensor_data_count > 0)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border border-munti-green-600/30 bg-munti-green-700/15 text-munti-green-400">
                                                DATA ({{ $station->sensor_data_count }})
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border border-munti-red-600/30 bg-munti-red-700/15 text-munti-red-400">
                                                NO DATA
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        {{ $station->latitude ?? '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        {{ $station->longitude ?? '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-text-300">
                                        {{ $station->lead_ip ?? '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                        {{ $station->updated_at ? $station->updated_at->format('Y-m-d H:i') : '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                        <form action="{{ route('stations.restore', $station->station_mn) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                                                        bg-munti-green-700/20 hover:bg-munti-green-600/30 text-munti-green-400
                                                        border border-munti-green-600/30 transition"
                                                    title="Restore Station">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                </svg>
                                                Restore
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex items-center justify-center h-40 text-sm text-text-500">
                    No deleted stations found.
                </div>
            @endif
        </div>
        <!-- Footer -->
        <div class="px-6 py-4 border-t border-border-700 flex justify-end">
            <button type="button" onclick="closeDeletedModal()"
                    class="px-4 py-2.5 text-sm font-medium text-text-300 hover:text-text-100 bg-surface-700 hover:bg-surface-600 rounded-lg transition border border-border-600">
                Close
            </button>
        </div>
    </div>
</div>

<div id="main-content" class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2.5">
                <span class="leading-tight uppercase tracking-wide">Manage Stations</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">Create and manage your monitoring stations</span>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-6 px-5 sm:px-8">

            @if(session('success'))
                <div class="mb-6 px-4 py-3 rounded-lg border border-munti-green-600/30 bg-munti-green-700/15 text-munti-green-400 text-sm font-medium">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm">

            <!-- Form Section -->
            <div class="p-5 border-b border-border-700">
                <!-- Toggle Button -->
                <button type="button" 
                        onclick="toggleStationForm()" 
                        class="w-full flex items-center justify-between text-left group">
                    <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-radar-400"></span>
                        Add New Station
                    </h3>
                    <span id="toggleIcon" class="text-text-400 transition-transform duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </span>
                </button>

                <!-- Collapsible Form -->
                <div id="stationForm" class="mt-4 hidden">
                    <form action="{{ route('stations.store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Station MN -->
                            <div class="flex flex-col">
                                <label for="station_mn" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Station MN
                                </label>

                                <input type="text"
                                    id="station_mn"
                                    name="station_mn"
                                    value="{{ old('station_mn') }}"
                                    maxlength="14"
                                    autocomplete="off"
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('station_mn') border-munti-red-500 @enderror"
                                    placeholder="Enter Station MN">

                                @error('station_mn')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Station Name -->
                            <div class="flex flex-col">
                                <label for="station_name" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Station Name <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="text" id="station_name" name="station_name" value="{{ old('station_name') }}" required
                                    maxlength="32" class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('station_name') border-munti-red-500 @enderror"
                                    placeholder="Enter unique station name">
                                @error('station_name')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Latitude -->
                            <div class="flex flex-col">
                                <label for="latitude" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Latitude
                                </label>
                                <input type="number"
                                    step="any"
                                    min="4.5"
                                    max="21.5"
                                    id="latitude"
                                    name="latitude"
                                    value="{{ old('latitude') }}"
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('latitude') border-munti-red-500 @enderror">
                                @error('latitude')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Longitude -->
                            <div class="flex flex-col">
                                <label for="longitude" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Longitude
                                </label>
                                <input type="number"
                                    step="any"
                                    min="116.0"
                                    max="127.0"
                                    id="longitude"
                                    name="longitude"
                                    value="{{ old('longitude') }}"
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('longitude') border-munti-red-500 @enderror">
                                @error('longitude')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Lead IP -->
                            <div class="flex flex-col">
                                <label for="lead_ip" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    IP Address <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="text"
                                    id="lead_ip"
                                    name="lead_ip"
                                    value="{{ old('lead_ip') }}"
                                    required
                                    maxlength="15"
                                    pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('lead_ip') border-munti-red-500 @enderror"
                                    placeholder="e.g. 192.168.1.10"
                                    oninput="
                                        let v = this.value.replace(/[^0-9.]/g, '');
                                        const parts = v.split('.');
                                        if (parts.length > 4) {
                                            v = parts.slice(0, 4).join('.');
                                        }
                                        // limit each octet to 3 digits
                                        v = parts.slice(0, 4).map(p => p.slice(0, 3)).join('.');
                                        this.value = v;
                                    ">
                                @error('lead_ip')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Lead Port - Auto-filled with 8899 -->
                            <div class="flex flex-col">
                                <label for="lead_port" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Port
                                </label>
                                <input type="number" id="lead_port" name="lead_port" value="{{ old('lead_port', 8899) }}" 
                                    readonly
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-800 text-text-400 placeholder-text-500 text-sm transition cursor-not-allowed opacity-75">
                                @error('lead_port')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Lead Slave - Auto-filled with 1 -->
                            <div class="flex flex-col">
                                <label for="lead_slave" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Slave
                                </label>
                                <input type="number" id="lead_slave" name="lead_slave" value="{{ old('lead_slave', 1) }}"
                                    readonly
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-800 text-text-400 placeholder-text-500 text-sm transition cursor-not-allowed opacity-75">
                                @error('lead_slave')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Combined: Enabled + Create Station Button -->
                            <div class="flex flex-col justify-end">
                                <label class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Enabled
                                </label>
                                <div class="flex items-center gap-3 flex-1">
                                    <div class="flex items-center h-11 px-3.5">
                                        <input type="hidden" name="enabled" value="0">
                                        <input type="checkbox" id="enabled" name="enabled" value="1"
                                            {{ old('enabled', true) ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-border-600 bg-surface-900 text-munti-green-600 focus:ring-munti-green-500 focus:ring-offset-0">
                                    </div>

                                    <button type="submit"
                                            class="flex-1 px-6 py-2.5 h-11 bg-munti-green-600 hover:bg-munti-green-500 text-text-100 font-semibold rounded-lg transition border border-munti-green-500/30 flex items-center justify-center gap-2 whitespace-nowrap">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Create Station
                                    </button>
                                </div>
                                @error('enabled')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        
                        <!-- Note about uniqueness -->
                        <div class="mt-4 p-3 bg-surface-700/30 rounded-lg border border-border-600">
                            <p class="text-xs text-text-400">
                                <span class="text-munti-red-400">*</span> Note: 
                                <span class="text-text-300">Station MN, Station Name, and IP Address must be unique across all records (including deleted stations).</span>
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Table Section -->
                <div class="flex-1 flex flex-col min-h-0">
                    <div class="px-5 py-3 border-b border-border-700 bg-surface-900/40 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-radar-400"></span>
                        Add New Station
                    </h3>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-500">{{ $stations->count() }} Station(s)</span>
                            
                            @if($deletedStations->count() > 0)
                                <button type="button" onclick="openDeletedModal()"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                                            bg-surface-700 hover:bg-surface-600 text-text-300 hover:text-text-100
                                            border border-border-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Deleted ({{ $deletedStations->count() }})
                                </button>
                            @endif
                        </div>
                    </div>
                    

                    <div class="overflow-x-auto thin-scrollbar flex-1">
                        @if($stations->count())
                            <table class="min-w-full divide-y divide-border-700">
                                <thead class="bg-surface-900/60 text-[11px] uppercase tracking-wider text-text-500 sticky top-0 z-10">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">MN</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Name</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Enabled</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Data Status</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Latitude</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Longitude</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">IP Address</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Port</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Slave</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Updated At</th>
                                        <th scope="col" class="px-4 py-3 text-center font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border-800">
                                    @foreach($stations as $station)
                                        <tr class="hover:bg-surface-700/50 transition" data-station-id="{{ $station->id }}">
                                            <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-munti-green-400">
                                                {{ $station->station_mn }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-200">
                                                {{ $station->station_name ?? '-' }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border
                                                    {{ $station->enabled
                                                        ? 'bg-munti-green-700/15 text-munti-green-400 border-munti-green-600/30'
                                                        : 'bg-munti-red-700/15 text-munti-red-400 border-munti-red-600/30' }}">
                                                    {{ $station->enabled ? 'Yes' : 'No' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                @if($station->sensor_data_count > 0)
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border border-munti-green-600/30 bg-munti-green-700/15 text-munti-green-400">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        DATA ({{ $station->sensor_data_count }})
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border border-munti-red-600/30 bg-munti-red-700/15 text-munti-red-400">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        NO DATA
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $station->latitude ?? '-' }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $station->longitude ?? '-' }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-text-300">
                                                {{ $station->lead_ip ?? '-' }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $station->lead_port ?? '-' }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $station->lead_slave ?? '-' }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                                {{ $station->updated_at ? $station->updated_at->format('Y-m-d H:i') : '-' }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <!-- Edit Button -->
                                                    <button type="button" 
                                                            onclick="editStation('{{ $station->station_mn }}')"
                                                            class="p-1.5 rounded-lg text-text-400 hover:text-radar-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                            title="Edit Station">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                    </button>

                                                    <!-- Delete Button -->
                                                    <button type="button" 
                                                            onclick="deleteStation('{{ $station->station_mn }}', '{{ $station->station_mn }}')"
                                                            class="p-1.5 rounded-lg text-text-400 hover:text-munti-red-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                            title="Delete Station">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24" class="text-red-400">
                                                            <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="flex items-center justify-center h-32 text-sm text-text-500">
                                No stations found.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
// Edit Station
function editStation(stationMn) {
    const modal = document.getElementById('editModal');
    modal.style.display = 'flex';
    
    // Fetch station data
    fetch(`/stations/${stationMn}/edit`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            document.getElementById('edit_station_mn').value = data.station_mn || '';
            document.getElementById('edit_station_name').value = data.station_name || '';
            document.getElementById('edit_latitude').value = data.latitude || '';
            document.getElementById('edit_longitude').value = data.longitude || '';
            document.getElementById('edit_lead_ip').value = data.lead_ip || '';
            document.getElementById('edit_lead_port').value = data.lead_port || '';
            document.getElementById('edit_lead_slave').value = data.lead_slave || '';
            document.getElementById('edit_enabled').checked = data.enabled === true;
            
            // Set form action
            document.getElementById('editForm').action = `/stations/${stationMn}`;
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load station data. Please refresh and try again.');
        });
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Delete Station
function deleteStation(stationMn, stationName) {
    // First check if station has data via AJAX
    fetch(`/stations/${stationMn}/check-data`)
        .then(response => response.json())
        .then(data => {
            let titleText = 'Delete Station?';
            let htmlText = `Are you sure you want to delete station <strong>"${stationName}"</strong>?`;
            
            if (data.hasData) {
                htmlText += `<br><span style="color: #f59e0b;">⚠️ This station has ${data.dataCount} sensor data records. It will be deactivated (hidden) but the data will be preserved.</span>`;
                htmlText += `<br><span style="color: #94a3b8; font-size: 13px;">You can reactivate it later if needed.</span>`;
            } else {
                htmlText += `<br><span style="color: #ef4444;">This action cannot be undone!</span>`;
            }
            
            Swal.fire({
                title: titleText,
                html: htmlText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: data.hasData ? '#f59e0b' : '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: data.hasData ? 'Yes, deactivate it!' : 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                background: '#1f2937',
                color: '#f3f4f6',
                iconColor: data.hasData ? '#f59e0b' : '#ef4444'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `/stations/${stationMn}`;
                    
                    const csrfToken = document.createElement('input');
                    csrfToken.type = 'hidden';
                    csrfToken.name = '_token';
                    csrfToken.value = '{{ csrf_token() }}';
                    form.appendChild(csrfToken);
                    
                    const methodField = document.createElement('input');
                    methodField.type = 'hidden';
                    methodField.name = '_method';
                    methodField.value = 'DELETE';
                    form.appendChild(methodField);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        })
        .catch(error => {
            console.error('Error checking station data:', error);
            // Fallback to default delete behavior
            Swal.fire({
                title: 'Delete Station?',
                html: `Are you sure you want to delete station <strong>"${stationName}"</strong>?<br><span style="color: #ef4444;">This action cannot be undone!</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                background: '#1f2937',
                color: '#f3f4f6',
                iconColor: '#f59e0b'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `/stations/${stationMn}`;
                    
                    const csrfToken = document.createElement('input');
                    csrfToken.type = 'hidden';
                    csrfToken.name = '_token';
                    csrfToken.value = '{{ csrf_token() }}';
                    form.appendChild(csrfToken);
                    
                    const methodField = document.createElement('input');
                    methodField.type = 'hidden';
                    methodField.name = '_method';
                    methodField.value = 'DELETE';
                    form.appendChild(methodField);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditModal();
    }
});

// Close modal on backdrop click
document.getElementById('editModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeEditModal();
    }
});

// Deleted Stations Modal
function openDeletedModal() {
    document.getElementById('deletedModal').style.display = 'flex';
}

function closeDeletedModal() {
    document.getElementById('deletedModal').style.display = 'none';
}

// Close deleted modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditModal();
        closeDeletedModal();
    }
});

// Close deleted modal on backdrop click
document.getElementById('deletedModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeDeletedModal();
    }
});

function toggleStationForm() {
    const form = document.getElementById('stationForm');
    const icon = document.getElementById('toggleIcon');
    
    form.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}

</script>

@include('layouts.footer')