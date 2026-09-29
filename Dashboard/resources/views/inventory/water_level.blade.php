@include('layouts.header')
@include('layouts.topbar')

<style>
    .thin-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
    .thin-scrollbar::-webkit-scrollbar-track { background: #1A1A1A; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb { background: #4B5563; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb:hover { background: #6B7280; }
    .thin-scrollbar { scrollbar-width: thin; scrollbar-color: #4B5563 #1A1A1A; }
</style>

<!-- Add Water Level Station Modal -->
<div id="addModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-surface-800 rounded-2xl border border-border-700 shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto thin-scrollbar">
        <div class="sticky top-0 bg-surface-800/95 backdrop-blur-sm px-6 py-4 border-b border-border-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-munti-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add New Water Level Station
            </h3>
            <button type="button" onclick="closeAddModal()" class="p-2 rounded-lg hover:bg-surface-700 text-text-400 hover:text-text-100 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        <form action="{{ route('inventory.water-level-stations.store') }}" method="POST" class="p-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-4">
                <!-- Station MN -->
                <div class="flex flex-col">
                    <label for="modal_station_mn" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Station MN <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text"
                        id="modal_station_mn"
                        name="station_mn"
                        required
                        maxlength="14"
                        autocomplete="off"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="Enter Station MN">
                </div>

                <!-- Station Name -->
                <div class="flex flex-col">
                    <label for="modal_station_name" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Station Name <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text" 
                           id="modal_station_name" 
                           name="station_name" 
                           required
                           maxlength="32" 
                           class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                           placeholder="Enter unique station name">
                </div>

                <!-- Enabled (Hidden - Default: true) -->
                <input type="hidden" name="enabled" value="1">

                <!-- Location -->
                <div class="flex flex-col">
                    <label for="modal_location" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Location
                    </label>
                    <select id="modal_location" 
                            name="location"
                            class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                        <option value="">Select Location</option>
                        <option value="Brgy. Alabang, Muntinlupa City">Brgy. Alabang, Muntinlupa City</option>
                        <option value="Brgy. Bayanan, Muntinlupa City">Brgy. Bayanan, Muntinlupa City</option>
                        <option value="Brgy. Buli, Muntinlupa City">Brgy. Buli, Muntinlupa City</option>
                        <option value="Brgy. Cupang, Muntinlupa City">Brgy. Cupang, Muntinlupa City</option>
                        <option value="Brgy. Poblacion, Muntinlupa City">Brgy. Poblacion, Muntinlupa City</option>
                        <option value="Brgy. Putatan, Muntinlupa City">Brgy. Putatan, Muntinlupa City</option>
                        <option value="Brgy. Sucat, Muntinlupa City">Brgy. Sucat, Muntinlupa City</option>
                        <option value="Brgy. Tunasan, Muntinlupa City">Brgy. Tunasan, Muntinlupa City</option>
                    </select>
                </div>

                <!-- Latitude & Longitude - Combined Row with Separate Labels -->
                <div class="flex flex-col">
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label for="modal_latitude" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                Latitude
                            </label>
                            <input type="number"
                                step="any"
                                min="4.5"
                                max="21.5"
                                id="modal_latitude"
                                name="latitude"
                                class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                                placeholder="14.5995">
                        </div>
                        <div class="flex-1">
                            <label for="modal_longitude" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                Longitude
                            </label>
                            <input type="number"
                                step="any"
                                min="116.0"
                                max="127.0"
                                id="modal_longitude"
                                name="longitude"
                                class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                                placeholder="120.9842">
                        </div>
                    </div>
                </div>

                <!-- Installation Height & Elevation Height -->
                <div class="flex flex-col">
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label for="modal_installation_height" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                Installation Height
                            </label>
                            <input type="number"
                                step="any"
                                id="modal_installation_height"
                                name="installation_height"
                                class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                                placeholder="e.g. 3.50">
                        </div>
                        <div class="flex-1">
                            <label for="modal_elevation_height" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                Elevation Height
                                <span id="modal_elevation_status" class="ml-1 text-[10px] text-text-500 normal-case"></span>
                            </label>
                            <input type="number"
                                step="any"
                                id="modal_elevation_height"
                                name="elevation_height"
                                class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                                placeholder="e.g. 12.75">
                        </div>
                    </div>
                </div>

                <!-- Lead IP -->
                <div class="flex flex-col">
                    <label for="modal_lead_ip" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        IP Address <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text"
                        id="modal_lead_ip"
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

                <!-- Lead Port & Slave - Combined Row with Separate Labels -->
                <div class="flex flex-col">
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label for="modal_lead_port" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                Port
                            </label>
                            <input type="number" 
                                id="modal_lead_port" 
                                name="lead_port" 
                                value="8899"
                                min="1"
                                max="65535"
                                class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                        </div>
                        <div class="flex-1">
                            <label for="modal_lead_slave" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                Slave
                            </label>
                            <input type="number" 
                                id="modal_lead_slave" 
                                name="lead_slave" 
                                value="1"
                                min="1"
                                max="255"
                                class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Note about uniqueness -->
            <div class="mt-4 p-3 bg-surface-700/30 rounded-lg border border-border-600">
                <p class="text-xs text-text-400">
                    <span class="text-munti-red-400">*</span> Note: 
                    <span class="text-text-300">Station MN, Station Name, and IP Address must be unique across all records (including deleted stations).</span>
                </p>
            </div>

            <div class="mt-6 pt-4 border-t border-border-700 flex justify-end gap-3">
                <button type="button" onclick="closeAddModal()"
                        class="px-4 py-2.5 text-sm font-medium text-text-300 hover:text-text-100 bg-surface-700 hover:bg-surface-600 rounded-lg transition border border-border-600">
                    Cancel
                </button>
                <button type="submit"
                        class="px-6 py-2.5 bg-munti-green-600 hover:bg-munti-green-500 text-text-100 font-semibold rounded-lg transition border border-munti-green-500/30 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create Station
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Water Level Station Modal -->
<div id="editModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-surface-800 rounded-2xl border border-border-700 shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto thin-scrollbar">
        <div class="sticky top-0 bg-surface-800/95 backdrop-blur-sm px-6 py-4 border-b border-border-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-radar-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Water Level Station
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
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-4">
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
                <!-- Enabled (Hidden - Default: true) -->
                <input type="hidden" name="enabled" value="1">
                <!-- Location -->
                <div class="flex flex-col">
                    <label for="edit_location" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Location
                    </label>
                    <select id="edit_location" 
                            name="location"
                            class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                        <option value="">Select Location</option>
                        <option value="Brgy. Alabang, Muntinlupa City">Brgy. Alabang, Muntinlupa City</option>
                        <option value="Brgy. Bayanan, Muntinlupa City">Brgy. Bayanan, Muntinlupa City</option>
                        <option value="Brgy. Buli, Muntinlupa City">Brgy. Buli, Muntinlupa City</option>
                        <option value="Brgy. Cupang, Muntinlupa City">Brgy. Cupang, Muntinlupa City</option>
                        <option value="Brgy. Poblacion, Muntinlupa City">Brgy. Poblacion, Muntinlupa City</option>
                        <option value="Brgy. Putatan, Muntinlupa City">Brgy. Putatan, Muntinlupa City</option>
                        <option value="Brgy. Sucat, Muntinlupa City">Brgy. Sucat, Muntinlupa City</option>
                        <option value="Brgy. Tunasan, Muntinlupa City">Brgy. Tunasan, Muntinlupa City</option>
                    </select>
                </div>
                <!-- Latitude & Longitude - Combined Row with Separate Labels -->
                <div class="flex flex-col">
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label for="edit_latitude" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                Latitude
                            </label>
                            <input type="number"
                                step="any"
                                min="4.5"
                                max="21.5"
                                id="edit_latitude"
                                name="latitude"
                                class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                                placeholder="14.5995">
                        </div>
                        <div class="flex-1">
                            <label for="edit_longitude" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                Longitude
                            </label>
                            <input type="number"
                                step="any"
                                min="116.0"
                                max="127.0"
                                id="edit_longitude"
                                name="longitude"
                                class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                                placeholder="120.9842">
                        </div>
                    </div>
                </div>

                <!-- Installation Height & Elevation Height -->
                <div class="flex flex-col">
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label for="edit_installation_height" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                Installation Height
                            </label>
                            <input type="number"
                                step="any"
                                id="edit_installation_height"
                                name="installation_height"
                                class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                        </div>
                        <div class="flex-1">
                            <label for="edit_elevation_height" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                Elevation Height
                                <span id="edit_elevation_status" class="ml-1 text-[10px] text-text-500 normal-case"></span>
                            </label>
                            <input type="number"
                                step="any"
                                id="edit_elevation_height"
                                name="elevation_height"
                                class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                        </div>
                    </div>
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
                <!-- Lead Port & Slave - Combined Row with Separate Labels -->
                <div class="flex flex-col">
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label for="edit_lead_port" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                Port
                            </label>
                            <input type="number" 
                                id="edit_lead_port" 
                                name="lead_port" 
                                value="8899"
                                min="1"
                                max="65535"
                                class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                        </div>
                        <div class="flex-1">
                            <label for="edit_lead_slave" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                Slave
                            </label>
                            <input type="number" 
                                id="edit_lead_slave" 
                                name="lead_slave" 
                                value="1"
                                min="1"
                                max="255"
                                class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                        </div>
                    </div>
                </div>
            </div>
            <!-- Note about uniqueness -->
            <div class="mt-4 p-3 bg-surface-700/30 rounded-lg border border-border-600">
                <p class="text-xs text-text-400">
                    <span class="text-munti-red-400">*</span> Note: 
                    <span class="text-text-300">Station MN, Station Name, and IP Address must be unique across all records (including deleted stations).</span>
                </p>
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

<!-- Deleted Water Level Stations Modal -->
<div id="deletedModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-surface-800 rounded-2xl border border-border-700 shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col">
        
        <!-- Header -->
        <div class="sticky top-0 bg-surface-800/95 backdrop-blur-sm px-6 py-4 border-b border-border-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-munti-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Deleted Water Level Stations
            </h3>
            <button type="button" onclick="closeDeletedModal()" class="p-2 rounded-lg hover:bg-surface-700 text-text-400 hover:text-text-100 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <!-- Content -->
        <div class="overflow-y-auto thin-scrollbar flex-1 p-0">
            @if(isset($deletedStations) && $deletedStations->count())
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
                                <th class="px-4 py-3 text-left font-medium">Install. Height</th>
                                <th class="px-4 py-3 text-left font-medium">Elev. Height</th>
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
                                        {{ $station->station_name }}
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
                                        @if(isset($station->sensor_data_count) && $station->sensor_data_count > 0)
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
                                        {{ $station->latitude }}
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        {{ $station->longitude }}
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        {{ $station->installation_height ?? '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        {{ $station->elevation_height ?? '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-text-300">
                                        {{ $station->lead_ip }}
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                        {{ $station->updated_at ? $station->updated_at->format('Y-m-d H:i') : '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                        <form action="{{ route('inventory.water-level-stations.restore', $station->station_mn) }}" method="POST" class="inline">
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
                    No deleted water level stations found.
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
                <span class="leading-tight uppercase tracking-wide">Manage Water Level Stations</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">Create and manage your water level monitoring stations</span>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-6 px-5 sm:px-8">

            @if(session('success'))
                <div class="mb-6 px-4 py-3 rounded-lg border border-munti-green-600/30 bg-munti-green-700/15 text-munti-green-400 text-sm font-medium">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm">

                <!-- Table Section -->
                <div class="flex-1 flex flex-col min-h-0">
                    <div class="px-5 py-3 border-b border-border-700 bg-surface-900/40 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider flex items-center gap-2">
                            {{-- <span class="w-1.5 h-1.5 rounded-full bg-munti-green-400"></span> --}}
                            Existing Water Level Stations
                        </h3>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-500">{{ isset($stations) ? $stations->count() : 0 }} Station(s)</span>

                            {{-- Download Format --}}
                            <a href="#"
                                class="inline-flex items-center gap-1.5 h-8 px-2.5 text-xs font-medium text-text-200 bg-surface-700/40 border border-border-600/30 rounded-md hover:bg-surface-700/60 transition whitespace-nowrap">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="w-3.5 h-3.5 shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Download Format
                            </a>

                            {{-- Export --}}
                            <a href="#"
                                class="inline-flex items-center gap-1.5 h-8 px-2.5 text-xs font-medium text-munti-green-400 bg-munti-green-700/20 border border-munti-green-600/30 rounded-md hover:bg-munti-green-700/30 transition whitespace-nowrap">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="w-3.5 h-3.5 shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Export
                            </a>

                            {{-- Import --}}
                            <form id="importForm"
                                action="#"
                                method="POST"
                                enctype="multipart/form-data"
                                class="m-0">
                                @csrf
                                <label for="importFile"
                                    class="inline-flex items-center gap-1.5 h-8 px-2.5 text-xs font-medium text-munti-yellow-400 bg-munti-yellow-300/10 border border-munti-yellow-600/30 rounded-md hover:bg-munti-yellow-700/30 transition whitespace-nowrap cursor-pointer">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="w-3.5 h-3.5 shrink-0"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    Import
                                </label>
                                <input type="file"
                                    id="importFile"
                                    name="file"
                                    accept=".xlsx,.xls,.csv"
                                    class="hidden"
                                    onchange="this.form.submit()">
                            </form>

                            @if(isset($deletedStations) && $deletedStations->count() > 0)
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

                            <!-- Add Station Button (opens modal) -->
                            <button type="button" 
                                    onclick="openAddModal()"
                                    class="inline-flex items-center gap-1.5 h-8 px-2.5 text-xs font-medium text-munti-green-400 bg-munti-green-700/20 border border-munti-green-600/30 rounded-md hover:bg-munti-green-700/30 transition whitespace-nowrap">
                                <svg xmlns="http://www.w3.org/2000/svg" 
                                    class="w-3.5 h-3.5 shrink-0" 
                                    fill="none" 
                                    viewBox="0 0 24 24" 
                                    stroke="currentColor">
                                    <path stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        stroke-width="2" 
                                        d="M12 4v16m8-8H4"/>
                                </svg>
                                Add Station
                            </button>

                        </div>
                    </div>

                    <div class="overflow-x-auto thin-scrollbar flex-1">
                        @php
                            $stations = $stations ?? collect();
                        @endphp

                        @if(isset($stations) && $stations->count())
                            <table class="min-w-full divide-y divide-border-700">
                                <thead class="bg-surface-900/60 text-[11px] uppercase tracking-wider text-text-500 sticky top-0 z-10">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">No.</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Station MN</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Name</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Enabled</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Data Status</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Location</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Latitude</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Longitude</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Install. Height</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Elev. Height</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">IP Address</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Port</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Slave</th>
                                        {{-- <th scope="col" class="px-4 py-3 text-left font-medium">Updated At</th> --}}
                                        <th scope="col" class="px-4 py-3 text-center font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border-800">
                                    @foreach($stations as $station)
                                        <tr class="hover:bg-surface-700/50 transition" data-station-id="{{ $station->id }}">
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">{{ $loop->iteration }}</td>
                                            <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-munti-green-400">
                                                {{ $station->station_mn }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-200">
                                                {{ $station->station_name }}
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
                                                @if(isset($station->sensor_data_count) && $station->sensor_data_count > 0)
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border border-munti-green-600/30 bg-munti-green-700/15 text-munti-green-400">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        DATA ({{ number_format($station->sensor_data_count) }})
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
                                            <td class="px-4 py-2.5 text-xs text-text-300">
                                                <div class="log-message collapsed rounded px-1 -mx-1"
                                                    title="Click to expand / collapse"
                                                    onclick="this.classList.toggle('collapsed'); this.classList.toggle('expanded');">
                                                    {{ $station->location }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $station->latitude }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $station->longitude }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $station->installation_height ?? '-' }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $station->elevation_height ?? '-' }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-text-300">
                                                {{ $station->lead_ip }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $station->lead_port }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $station->lead_slave }}
                                            </td>
                                            {{-- <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                                {{ $station->updated_at ? $station->updated_at->format('Y-m-d H:i') : '-' }}
                                            </td> --}}
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
                                No water level stations found.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>

// Add Station Modal
function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
    // Reset the "user edited" flag when opening fresh
    if (window.addModalElevation) {
        window.addModalElevation.resetUserEdited();
    }
}
function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

// Close add modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditModal();
        closeDeletedModal();
        closeAddModal();
    }
});

// Close add modal on backdrop click
document.getElementById('addModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeAddModal();
    }
});

// Edit Station
function editStation(stationMn) {
    const modal = document.getElementById('editModal');
    modal.style.display = 'flex';

    // Try fetch first, fallback to dummy
    fetch(`/inventory/water-level-stations/${stationMn}/edit`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            populateEditForm(data);
            if (window.editModalElevation) {
                window.editModalElevation.resetUserEdited();
            }
        })
        .catch(error => {
            console.warn('Using dummy data for edit:', error);
            const data = dummyData[stationMn] || dummyData['WLS-001'];
            populateEditForm(data);
            if (window.editModalElevation) {
                window.editModalElevation.resetUserEdited();
            }
        });
}

function populateEditForm(data) {
    document.getElementById('edit_station_mn').value = data.station_mn || '';
    document.getElementById('edit_station_name').value = data.station_name || '';
    document.getElementById('edit_location').value = data.location || '';
    document.getElementById('edit_latitude').value = data.latitude || '';
    document.getElementById('edit_longitude').value = data.longitude || '';
    document.getElementById('edit_installation_height').value = data.installation_height || '';
    document.getElementById('edit_elevation_height').value = data.elevation_height || '';
    document.getElementById('edit_lead_ip').value = data.lead_ip || '';
    document.getElementById('edit_lead_port').value = data.lead_port || '';
    document.getElementById('edit_lead_slave').value = data.lead_slave || '';

    const enabledHidden = document.querySelector('#editForm input[name="enabled"]');
    if (enabledHidden) {
        enabledHidden.value = data.enabled === true ? '1' : '0';
    }

    document.getElementById('editForm').action = `/inventory/water-level-stations/${data.station_mn}`;
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Delete Station
function deleteStation(stationMn, stationName) {
    fetch(`/inventory/water-level-stations/${stationMn}/check-data`, {
        headers: { 'Accept': 'application/json' }
    })
        .then(response => {
            if (!response.ok) throw new Error('Failed to check station data');
            return response.json();
        })
        .then(data => {
            confirmDelete(data, stationMn, stationName);
        })
        .catch(error => {
            console.error('Error checking station data:', error);
            Swal.fire({
                icon: 'error',
                title: 'Failed to check station',
                text: 'Could not verify station data. Please try again.',
                background: '#1f2937',
                color: '#f3f4f6'
            });
        });
}

function confirmDelete(data, stationMn, stationName) {
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
            form.action = `/inventory/water-level-stations/${stationMn}`;
            
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

/* ============================================================
   Auto-Calculate Elevation Height from Latitude / Longitude
   Uses the free Open-Meteo Elevation API (no API key needed)
   Docs: https://open-meteo.com/en/docs/elevation-api
   ============================================================ */

// Debounce helper so we don't spam the API on every keystroke
function debounce(fn, delay = 600) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

/**
 * Fetch elevation for a given lat/long.
 * @param {number} lat
 * @param {number} lon
 * @returns {Promise<number|null>}
 */
async function fetchElevation(lat, lon) {
    const url = `https://api.open-meteo.com/v1/elevation?latitude=${lat}&longitude=${lon}`;
    const res = await fetch(url);
    if (!res.ok) throw new Error(`Elevation API error: ${res.status}`);
    const data = await res.json();

    // API returns: { "elevation": [ 38.0 ] }
    if (Array.isArray(data.elevation) && data.elevation.length > 0) {
        return data.elevation[0];
    }
    return null;
}

/**
 * Wire up auto-elevation for a modal.
 * @param {string} latId       - DOM id of latitude input
 * @param {string} lonId       - DOM id of longitude input
 * @param {string} elevId      - DOM id of elevation input
 * @param {string} statusId    - DOM id of the small status <span>
 */
function setupAutoElevation(latId, lonId, elevId, statusId) {
    const latEl    = document.getElementById(latId);
    const lonEl    = document.getElementById(lonId);
    const elevEl   = document.getElementById(elevId);
    const statusEl = document.getElementById(statusId);

    if (!latEl || !lonEl || !elevEl) return;

    // Track whether the user has manually edited the elevation field.
    // If so, we don't overwrite their value automatically.
    let userEdited = false;
    elevEl.addEventListener('input', (e) => {
        // Only treat as user-edit if the event was not triggered programmatically
        if (e.isTrusted) userEdited = true;
    });

    const doFetch = debounce(async () => {
        const lat = parseFloat(latEl.value);
        const lon = parseFloat(lonEl.value);

        // Need both valid coordinates
        if (isNaN(lat) || isNaN(lon)) {
            statusEl.textContent = '';
            return;
        }

        // Skip if user manually entered an elevation
        if (userEdited) {
            statusEl.textContent = '(manual)';
            statusEl.className = 'ml-1 text-[10px] text-text-500 normal-case';
            return;
        }

        statusEl.textContent = 'calculating…';
        statusEl.className = 'ml-1 text-[10px] text-radar-400 normal-case';

        try {
            const elevation = await fetchElevation(lat, lon);
            if (elevation !== null && !userEdited) {
                elevEl.value = Number(elevation).toFixed(2);
                statusEl.textContent = '✓ from API';
                statusEl.className = 'ml-1 text-[10px] text-munti-green-400 normal-case';
                // Fade the status after 3 seconds
                setTimeout(() => {
                    if (statusEl.textContent === '✓ from API') {
                        statusEl.textContent = '';
                    }
                }, 3000);
            } else {
                statusEl.textContent = '';
            }
        } catch (err) {
            console.warn('Elevation fetch failed:', err);
            statusEl.textContent = '✗ failed';
            statusEl.className = 'ml-1 text-[10px] text-munti-red-400 normal-case';
        }
    }, 700);

    // Fire on blur and on input (debounced)
    [latEl, lonEl].forEach(el => {
        el.addEventListener('blur', doFetch);
        el.addEventListener('input', doFetch);
    });

    // Reset userEdited flag when the modal is reopened (call from openAddModal/openEdit)
    return {
        resetUserEdited: () => {
            userEdited = false;
            statusEl.textContent = '';
        }
    };
}

// Initialize both modals once the DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    window.addModalElevation  = setupAutoElevation(
        'modal_latitude', 'modal_longitude',
        'modal_elevation_height', 'modal_elevation_status'
    );
    window.editModalElevation = setupAutoElevation(
        'edit_latitude', 'edit_longitude',
        'edit_elevation_height', 'edit_elevation_status'
    );
});

</script>

@include('layouts.footer')