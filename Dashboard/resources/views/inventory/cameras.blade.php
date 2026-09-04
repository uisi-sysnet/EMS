@include('layouts.header')
@include('layouts.topbar')

<style>
.thin-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
.thin-scrollbar::-webkit-scrollbar-track { background: #1A1A1A; border-radius: 10px; }
.thin-scrollbar::-webkit-scrollbar-thumb { background: #4B5563; border-radius: 10px; }
.thin-scrollbar::-webkit-scrollbar-thumb:hover { background: #6B7280; }
.thin-scrollbar { scrollbar-width: thin; scrollbar-color: #4B5563 #1A1A1A; }
</style>

<!-- Add Camera Modal -->
<div id="addModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-surface-800 rounded-2xl border border-border-700 shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-y-auto thin-scrollbar">
        <div class="sticky top-0 bg-surface-800/95 backdrop-blur-sm px-6 py-4 border-b border-border-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-munti-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add New Camera
            </h3>
            <button type="button" onclick="closeAddModal()" class="p-2 rounded-lg hover:bg-surface-700 text-text-400 hover:text-text-100 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        <form action="{{ route('inventory.cameras.store') }}" method="POST" class="p-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Channel -->
                <div class="flex flex-col">
                    <label for="modal_channel" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Channel <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text"
                        id="modal_channel"
                        name="channel"
                        value="1" 
                        required
                        maxlength="14"
                        pattern="[A-Za-z0-9]{1,14}"
                        oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g, '').slice(0, 14)"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="1">
                </div>

                <!-- Camera Name -->
                <div class="flex flex-col">
                    <label for="modal_name" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Camera Name <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text" 
                        id="modal_name" 
                        name="name" 
                        required
                        maxlength="30" 
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="Front Gate Camera"
                        oninput="generateSlugFromName(this.value)">
                </div>

                <!-- Device Type -->
                <div class="flex flex-col">
                    <label for="modal_device_type" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Device Type <span class="text-munti-red-400">*</span>
                    </label>
                    <select id="modal_device_type"
                            name="device_type"
                            required
                            class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                        <option value="">Select Device Type</option>
                        <option value="PTZ">PTZ</option>
                        <option value="Bullet">Bullet</option>
                        <option value="Dome">Dome</option>
                    </select>
                </div>

                <!-- Serial Number -->
                <div class="flex flex-col">
                    <label for="modal_serial_number" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Serial Number
                    </label>
                    <input type="text"
                        id="modal_serial_number"
                        name="serial_number"
                        maxlength="30"
                        pattern="[A-Za-z0-9]{1,30}"
                        inputmode="text"
                        autocomplete="off"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="SN2024001"
                        oninput="this.value=this.value.replace(/[^A-Za-z0-9]/g,'').slice(0,30)">
                </div>

                <!-- IP Address -->
                <div class="flex flex-col">
                    <label for="modal_ip_address" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        IP Address <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text"
                        id="modal_ip_address"
                        name="ip_address"
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

                <!-- ONVIF Port -->
                <div class="flex flex-col">
                    <label for="modal_onvif_port" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        ONVIF Port <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="number"
                        id="modal_onvif_port"
                        name="onvif_port"
                        required
                        min="1"
                        max="65535"
                        maxlength="4"
                        oninput="this.value=this.value.slice(0,4)"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="80">
                </div>

                <!-- Username -->
                <div class="flex flex-col">
                    <label for="modal_username" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Username <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text"
                        id="modal_username"
                        name="username"
                        required
                        maxlength="30"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="admin">
                </div>

                <!-- Password -->
                <div class="flex flex-col">
                    <label for="modal_password" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Password <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="password"
                        id="modal_password"
                        name="password"
                        required
                        maxlength="30"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="••••••••">
                </div>

                <!-- Location -->
                <div class="flex flex-col">
                    <label for="modal_location" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Location <span class="text-munti-red-400">*</span>
                    </label>
                    <select id="modal_location"
                            name="location"
                            required
                            class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                        <option value="">Select Location</option>
                        @foreach($station_names as $station_name)
                            <option value="{{ $station_name }}">{{ $station_name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Enabled (Hidden - Default: true) -->
                <input type="hidden" name="enabled" value="1">

                <!-- Slug (Hidden) -->
                <input type="hidden"
                    id="modal_slug"
                    name="slug"
                    value="">
            </div>

            <!-- Note about uniqueness -->
            <div class="mt-4 p-3 bg-surface-700/30 rounded-lg border border-border-600">
                <p class="text-xs text-text-400">
                    <span class="text-munti-red-400">*</span> Note: 
                    <span class="text-text-300">Slug and Serial Number must be unique across all records.</span>
                    <span class="text-text-300 ml-2">The slug is auto-generated from the camera name.</span>
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
                    Create Camera
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Camera Modal --}}
<div id="editModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-surface-800 rounded-2xl border border-border-700 shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-y-auto thin-scrollbar">
        <div class="sticky top-0 bg-surface-800/95 backdrop-blur-sm px-6 py-4 border-b border-border-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-radar-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Camera
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

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Edit Channel --}}
                <div class="flex flex-col">
                    <label for="edit_channel" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Channel <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text"
                        id="edit_channel"
                        name="channel"
                        required
                        maxlength="14"
                        pattern="[A-Za-z0-9]{1,14}"
                        oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g, '').slice(0, 14)"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="Channel 1">
                </div>

                {{-- Edit Camera Name --}}
                <div class="flex flex-col">
                    <label for="edit_name" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Camera Name <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text"
                        id="edit_name"
                        name="name"
                        required
                        maxlength="30"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="Front Gate Camera"
                        oninput="generateEditSlugFromName(this.value)">
                </div>

                {{-- Edit Device Type --}}
                <div class="flex flex-col">
                    <label for="edit_device_type" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Device Type <span class="text-munti-red-400">*</span>
                    </label>
                    <select id="edit_device_type"
                            name="device_type"
                            required
                            class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                        <option value="">Select Device Type</option>
                        <option value="PTZ">PTZ</option>
                        <option value="Bullet">Bullet</option>
                        <option value="Dome">Dome</option>
                    </select>
                </div>

                {{-- Edit Serial Number --}}
                <div class="flex flex-col">
                    <label for="edit_serial_number" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Serial Number
                    </label>
                    <input type="text"
                        id="edit_serial_number"
                        name="serial_number"
                        maxlength="30"
                        pattern="[A-Za-z0-9]{1,30}"
                        inputmode="text"
                        autocomplete="off"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="SN2024001"
                        oninput="this.value=this.value.replace(/[^A-Za-z0-9]/g,'').slice(0,30)">
                </div>

                {{-- Edit IP Address --}}
                <div class="flex flex-col">
                    <label for="edit_ip_address" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        IP Address <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text"
                        id="edit_ip_address"
                        name="ip_address"
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

                {{-- Edit ONVIF Port --}}
                <div class="flex flex-col">
                    <label for="edit_onvif_port" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        ONVIF Port <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="number"
                        id="edit_onvif_port"
                        name="onvif_port"
                        required
                        min="1"
                        max="65535"
                        maxlength="4"
                        oninput="this.value=this.value.slice(0,4)"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="80">
                </div>

                {{-- Edit Username --}}
                <div class="flex flex-col">
                    <label for="edit_username" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Username <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text"
                        id="edit_username"
                        name="username"
                        required
                        maxlength="30"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="admin">
                </div>

                {{-- Edit Password --}}
                <div class="flex flex-col">
                    <label for="edit_password" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Password <span class="text-text-500">(leave blank to keep current)</span>
                    </label>
                    <input type="password"
                        id="edit_password"
                        name="password"
                        maxlength="30"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="Leave blank to keep current password">
                </div>

                {{-- Edit Location --}}
                <div class="flex flex-col">
                    <label for="edit_location" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Location <span class="text-munti-red-400">*</span>
                    </label>
                    <select id="edit_location"
                            name="location"
                            required
                            class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                        <option value="">Select Location</option>
                        @foreach($station_names as $station_name)
                            <option value="{{ $station_name }}">{{ $station_name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Edit Latitude --}}
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
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="14.5995">
                </div>

                {{-- Edit Longitude --}}
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
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="120.9842">
                </div>

                {{-- Edit Enabled (Hidden) --}}
                <input type="hidden" name="enabled" value="1">

                {{-- Edit Slug --}}
                <div class="flex flex-col hidden">
                    <label for="edit_slug" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Slug <span class="text-text-500">(auto-generated)</span>
                    </label>
                    <input type="text"
                        id="edit_slug"
                        name="slug"
                        maxlength="30"
                        readonly
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-800 text-text-400 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition cursor-not-allowed"
                        placeholder="Auto-generated from name">
                </div>
            </div>

            {{-- Note about uniqueness --}}
            <div class="mt-4 p-3 bg-surface-700/30 rounded-lg border border-border-600">
                <p class="text-xs text-text-400">
                    <span class="text-munti-red-400">*</span> Note: 
                    <span class="text-text-300">Slug and Serial Number must be unique across all records.</span>
                    <span class="text-text-300 ml-2">The slug is auto-generated from the camera name.</span>
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
                    Update Camera
                </button>
            </div>
        </form>
    </div>
</div>

<div id="main-content" class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        {{-- Header --}}
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2.5">
                <span class="leading-tight uppercase tracking-wide">Manage Cameras</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">Create and manage your camera devices</span>
        </div>

        {{-- Content --}}
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-6 px-5 sm:px-8">

            {{-- Success Message --}}
            @if(session('success'))
                <div class="mb-6 px-4 py-3 rounded-lg border border-munti-green-600/30 bg-munti-green-700/15 text-munti-green-400 text-sm font-medium">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Error Message --}}
            @if(session('error'))
                <div class="mb-6 px-4 py-3 rounded-lg border border-munti-red-600/30 bg-munti-red-700/15 text-munti-red-400 text-sm font-medium">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm">

                {{-- Cameras Table --}}
                <div class="flex-1 flex flex-col min-h-0">
                    <div class="px-5 py-3 border-b border-border-700 bg-surface-900/40 flex items-center justify-between">
                        <h4 class="text-xs font-semibold text-text-400 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-munti-green-400"></span>
                            Existing Cameras
                        </h4>

                        {{-- Action Buttons --}}
                        <div class="flex items-center gap-1.5">

                            <div class="flex items-center gap-3">
                                <span class="text-xs text-text-500">{{ $cameras->count() }} Camera(s)</span>
                            </div>

                            {{-- Download Format --}}
                            <a href="{{ route('cameras.download-format') }}"
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
                            <a href="{{ route('inventory.cameras.export') }}"
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
                                action="{{ route('inventory.cameras.import') }}"
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

                            {{-- Add Camera Button --}}
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
                                Add Camera
                            </button>

                        </div>
                    </div>

                    <div class="overflow-x-auto thin-scrollbar flex-1">
                        @if($cameras->count())
                            <table class="min-w-full divide-y divide-border-700">
                                <thead class="bg-surface-900/60 text-[11px] uppercase tracking-wider text-text-500 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium">No.</th>
                                        <th class="px-4 py-3 text-left font-medium">Channel</th>
                                        <th class="px-4 py-3 text-left font-medium">Name</th>
                                        {{-- <th class="px-4 py-3 text-left font-medium">Slug</th> --}}
                                        <th class="px-4 py-3 text-left font-medium">IP Address</th>
                                        <th class="px-4 py-3 text-left font-medium">ONVIF Port</th>
                                        <th class="px-4 py-3 text-left font-medium">Location</th>
                                        <th class="px-4 py-3 text-left font-medium">Device Type</th>
                                        <th class="px-4 py-3 text-left font-medium">Status</th>
                                        <th class="px-4 py-3 text-left font-medium">Serial #</th>
                                        <th class="px-4 py-3 text-left font-medium">Last Sync</th>
                                        <th class="px-4 py-3 text-center font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border-800">
                                    @foreach($cameras as $camera)
                                        <tr class="hover:bg-surface-700/50 transition" data-camera-id="{{ $camera->id }}">
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">{{ $loop->iteration }}</td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">{{ $camera->channel }}</td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-200">{{ $camera->name }}</td>
                                            {{-- <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-radar-400">{{ $camera->slug }}</td> --}}
                                            <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-text-300">{{ $camera->ip_address }}</td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">{{ $camera->onvif_port ?? '-' }}</td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">{{ $camera->location ?? '-' }}</td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">{{ $camera->device_type ?? '-' }}</td>
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $camera->enabled ? 'bg-munti-green-700/15 text-munti-green-400 border-munti-green-600/30' : 'bg-munti-red-700/15 text-munti-red-400 border-munti-red-600/30' }}">
                                                    {{ $camera->enabled ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">{{ $camera->serial_number ?? '-' }}</td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">{{ $camera->last_synced_at ? $camera->last_synced_at->format('Y-m-d H:i') : '-' }}</td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    {{-- Edit Button --}}
                                                    <button type="button" 
                                                            onclick="editCamera('{{ $camera->id }}')" 
                                                            class="p-1.5 rounded-lg text-text-400 hover:text-radar-400 hover:bg-surface-700/70 transition-all duration-200 group" 
                                                            title="Edit Camera">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                    </button>

                                                    {{-- Delete Button --}}
                                                    <button type="button" 
                                                            onclick="deleteCamera('{{ $camera->id }}', '{{ $camera->name }}')" 
                                                            class="p-1.5 rounded-lg text-text-400 hover:text-munti-red-400 hover:bg-surface-700/70 transition-all duration-200 group" 
                                                            title="Delete Camera">
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
                                No cameras found. Add one!
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Include SweetAlert2 for delete confirmation --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

// Add Camera Modal
function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditModal();
        closeAddModal(); // Add this line
    }
});

// Close add modal on backdrop click
document.getElementById('addModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeAddModal();
    }
});

function generateSlugFromName(name) {
    // Get the hidden slug input field
    const slugInput = document.getElementById('modal_slug');
    
    // Generate slug: lowercase, replace spaces with hyphens, remove special chars
    const slug = name
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '') // Remove special characters except spaces and hyphens
        .trim()
        .replace(/\s+/g, '-') // Replace spaces with hyphens
        .replace(/-+/g, '-') // Replace multiple hyphens with single hyphen
        .slice(0, 30); // Limit to 30 characters
    
    // Set the slug value
    slugInput.value = slug;
}

// Edit Camera
function editCamera(cameraId) {
    const modal = document.getElementById('editModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    const submitBtn = document.querySelector('#editForm button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Loading...';

    fetch(`/inventory/cameras/${cameraId}/edit`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            document.getElementById('edit_channel').value = data.channel || '';
            document.getElementById('edit_name').value = data.name || '';
            document.getElementById('edit_ip_address').value = data.ip_address || '';
            document.getElementById('edit_onvif_port').value = data.onvif_port || 80;
            document.getElementById('edit_username').value = data.username || '';
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_location').value = data.location || '';
            document.getElementById('edit_device_type').value = data.device_type || '';
            document.getElementById('edit_serial_number').value = data.serial_number || '';
            document.getElementById('edit_latitude').value = data.latitude || '';
            document.getElementById('edit_longitude').value = data.longitude || '';
            document.getElementById('edit_slug').value = data.slug || '';

            document.getElementById('editForm').action = `/inventory/cameras/${cameraId}`;
            
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Update Camera';
        })
        .catch(error => {
            console.error('Error:', error);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Update Camera';
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load camera data. Please refresh and try again.',
                background: '#1f2937',
                color: '#f3f4f6',
                iconColor: '#ef4444'
            });
        });
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Auto-generate slug from name for Edit Modal
function generateEditSlugFromName(name) {
    const slug = name
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '') // Remove special characters except spaces and hyphens
        .trim()
        .replace(/\s+/g, '-') // Replace spaces with hyphens
        .replace(/-+/g, '-') // Replace multiple hyphens with single hyphen
        .slice(0, 30); // Limit to 30 characters
    
    const slugInput = document.getElementById('edit_slug');
    if (slugInput) {
        slugInput.value = slug;
    }
}

// Delete Camera
function deleteCamera(cameraId, cameraName) {
    Swal.fire({
        title: 'Delete Camera?',
        html: `Are you sure you want to delete camera <strong>"${cameraName}"</strong>?<br><span style="color: #ef4444;">This action cannot be undone!</span>`,
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
            form.action = `/inventory/cameras/${cameraId}`;

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

// Auto-generate slug from name in edit modal
document.addEventListener('DOMContentLoaded', function() {
    const editNameInput = document.getElementById('edit_name');
    const editSlugPreview = document.getElementById('edit_slug_preview');
    const editSlugHidden = document.getElementById('edit_slug');

    if (editNameInput && editSlugPreview && editSlugHidden) {
        editNameInput.addEventListener('input', function() {
            const slug = this.value.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            editSlugPreview.value = slug || 'auto-generated';
            editSlugHidden.value = slug;
        });
    }
});

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
</script>

@include('layouts.footer')