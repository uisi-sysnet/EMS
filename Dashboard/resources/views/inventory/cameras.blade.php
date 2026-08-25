@include('layouts.header')
@include('layouts.topbar')

<style>.thin-scrollbar::-webkit-scrollbar{width:5px;height:5px}.thin-scrollbar::-webkit-scrollbar-track{background:#1A1A1A;border-radius:10px}.thin-scrollbar::-webkit-scrollbar-thumb{background:#4B5563;border-radius:10px}.thin-scrollbar::-webkit-scrollbar-thumb:hover{background:#6B7280}.thin-scrollbar{scrollbar-width:thin;scrollbar-color:#4B5563 #1A1A1A}</style>

<div id="main-content" class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2.5">
                <span class="leading-tight uppercase tracking-wide">Edit Camera</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">Update camera device details</span>
        </div>
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-6 px-5 sm:px-8">
            @if(session('success'))
                <div class="mb-6 px-4 py-3 rounded-lg border border-munti-green-600/30 bg-munti-green-700/15 text-munti-green-400 text-sm font-medium">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-6 px-4 py-3 rounded-lg border border-munti-red-600/30 bg-munti-red-700/15 text-munti-red-400 text-sm font-medium">
                    {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-6 px-4 py-3 rounded-lg border border-munti-red-600/30 bg-munti-red-700/15 text-munti-red-400 text-sm font-medium">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm">
                <div class="p-5 border-b border-border-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-radar-400"></span>
                            Edit Camera
                        </h3>
                        <a href="{{ route('inventory.cameras.index') }}" 
                           class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md bg-surface-700 hover:bg-surface-600 text-text-300 transition border border-border-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Back to List
                        </a>
                    </div>

                    <form action="{{ route('inventory.cameras.update', $camera->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="flex flex-col">
                                <label for="channel" class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    Channel <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="text" id="channel" name="channel" value="{{ old('channel', $camera->channel) }}" required maxlength="50" 
                                       class="w-full min-w-0 h-8 px-2.5 border border-border-600 rounded-md bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-xs transition @error('channel') border-munti-red-500 @enderror" 
                                       placeholder="Channel 1">
                                @error('channel')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col">
                                <label for="name" class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    Camera Name <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="text" id="name" name="name" value="{{ old('name', $camera->name) }}" required maxlength="255" 
                                       class="w-full h-8 px-2.5 border border-border-600 rounded-md bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-xs transition @error('name') border-munti-red-500 @enderror" 
                                       placeholder="Front Gate Camera"
                                       oninput="document.getElementById('slug_preview').value=this.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'')">
                                @error('name')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col">
                                <label for="ip_address" class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    IP Address <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="text" id="ip_address" name="ip_address" value="{{ old('ip_address', $camera->ip_address) }}" required maxlength="255" 
                                       class="w-full h-8 px-2.5 border border-border-600 rounded-md bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-xs transition @error('ip_address') border-munti-red-500 @enderror" 
                                       placeholder="192.168.1.10"
                                       oninput="let v=this.value.replace(/[^0-9.]/g,'');const parts=v.split('.');if(parts.length>4)v=parts.slice(0,4).join('.');v=v.split('.').slice(0,4).map(p=>p.slice(0,3)).join('.');this.value=v;">
                                @error('ip_address')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col">
                                <label for="onvif_port" class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    ONVIF Port <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="number" id="onvif_port" name="onvif_port" value="{{ old('onvif_port', $camera->onvif_port) }}" required min="1" max="65535" 
                                       class="w-full h-8 px-2.5 border border-border-600 rounded-md bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-xs transition @error('onvif_port') border-munti-red-500 @enderror" 
                                       placeholder="80">
                                @error('onvif_port')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col">
                                <label for="username" class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    Username <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="text" id="username" name="username" value="{{ old('username', $camera->username) }}" required maxlength="255" 
                                       class="w-full h-8 px-2.5 border border-border-600 rounded-md bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-xs transition @error('username') border-munti-red-500 @enderror" 
                                       placeholder="admin">
                                @error('username')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col">
                                <label for="password" class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    Password <span class="text-text-500">(leave blank to keep current)</span>
                                </label>
                                <input type="password" id="password" name="password" maxlength="255" 
                                       class="w-full h-8 px-2.5 border border-border-600 rounded-md bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-xs transition @error('password') border-munti-red-500 @enderror" 
                                       placeholder="Leave blank to keep current password">
                                @error('password')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col">
                                <label for="location" class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    Location
                                </label>
                                <input type="text" id="location" name="location" value="{{ old('location', $camera->location) }}" maxlength="255" 
                                       class="w-full h-8 px-2.5 border border-border-600 rounded-md bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-xs transition @error('location') border-munti-red-500 @enderror" 
                                       placeholder="Building A, Floor 2">
                                @error('location')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col">
                                <label for="device_type" class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    Device Type
                                </label>
                                <input type="text" id="device_type" name="device_type" value="{{ old('device_type', $camera->device_type) }}" maxlength="50" 
                                       class="w-full h-8 px-2.5 border border-border-600 rounded-md bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-xs transition @error('device_type') border-munti-red-500 @enderror" 
                                       placeholder="ONVIF">
                                @error('device_type')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col">
                                <label for="serial_number" class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    Serial Number
                                </label>
                                <input type="text" id="serial_number" name="serial_number" value="{{ old('serial_number', $camera->serial_number) }}" maxlength="50" 
                                       class="w-full h-8 px-2.5 border border-border-600 rounded-md bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-xs transition @error('serial_number') border-munti-red-500 @enderror" 
                                       placeholder="SN-2024-001">
                                @error('serial_number')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col">
                                <label for="latitude" class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    Latitude
                                </label>
                                <input type="number" step="any" min="-90" max="90" id="latitude" name="latitude" value="{{ old('latitude', $camera->latitude) }}" 
                                       class="w-full h-8 px-2.5 border border-border-600 rounded-md bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-xs transition @error('latitude') border-munti-red-500 @enderror" 
                                       placeholder="-6.2088">
                                @error('latitude')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col">
                                <label for="longitude" class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    Longitude
                                </label>
                                <input type="number" step="any" min="-180" max="180" id="longitude" name="longitude" value="{{ old('longitude', $camera->longitude) }}" 
                                       class="w-full h-8 px-2.5 border border-border-600 rounded-md bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-xs transition @error('longitude') border-munti-red-500 @enderror" 
                                       placeholder="106.8456">
                                @error('longitude')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col">
                                <label class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    Status
                                </label>
                                <div class="flex items-center h-8 px-2.5 border border-border-600 rounded-md bg-surface-900">
                                    <input type="hidden" name="enabled" value="0">
                                    <input type="checkbox" id="enabled" name="enabled" value="1" {{ old('enabled', $camera->enabled) ? 'checked' : '' }} 
                                           class="h-3.5 w-3.5 rounded border-border-600 bg-surface-900 text-munti-green-600 focus:ring-munti-green-500 focus:ring-offset-0">
                                    <label for="enabled" class="ml-1.5 text-xs text-text-300">Active</label>
                                </div>
                                @error('enabled')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col">
                                <label for="slug_preview" class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    Slug (auto)
                                </label>
                                <input type="text" id="slug_preview" value="{{ $camera->slug }}" 
                                       class="w-full h-8 px-2.5 border border-border-600 rounded-md bg-surface-800 text-text-400 text-xs cursor-not-allowed" 
                                       placeholder="auto-generated" readonly>
                                <input type="hidden" id="slug" name="slug" value="{{ old('slug', $camera->slug) }}">
                            </div>

                            <div class="flex flex-col">
                                <label class="block text-xs font-medium text-text-400 mb-1 uppercase tracking-wide">
                                    Action
                                </label>
                                <div class="flex gap-2">
                                    <button type="submit" class="flex-1 h-8 px-3 bg-radar-500 hover:bg-radar-400 text-text-100 text-xs font-semibold rounded-md transition border border-radar-400/30 flex items-center justify-center gap-1.5 whitespace-nowrap">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                        Update Camera
                                    </button>
                                    <a href="{{ route('inventory.cameras.index') }}" 
                                       class="px-3 h-8 bg-surface-700 hover:bg-surface-600 text-text-300 text-xs font-medium rounded-md transition border border-border-600 flex items-center justify-center">
                                        Cancel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="mt-4 p-3 bg-surface-700/30 rounded-lg border border-border-600">
                        <p class="text-xs text-text-400">
                            <span class="text-munti-red-400">*</span> Note: 
                            <span class="text-text-300">Slug and Serial Number must be unique across all records.</span>
                            <span class="text-text-300 ml-2">Leave password blank to keep the current password.</span>
                        </p>
                        <p class="text-xs text-text-500 mt-1">
                            Created: {{ $camera->created_at->format('Y-m-d H:i:s') }} | 
                            Last Updated: {{ $camera->updated_at->format('Y-m-d H:i:s') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('name');
    const slugPreview = document.getElementById('slug_preview');
    const slugHidden = document.getElementById('slug');

    if (nameInput && slugPreview && slugHidden) {
        nameInput.addEventListener('input', function() {
            const slug = this.value.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            slugPreview.value = slug || 'auto-generated';
            slugHidden.value = slug;
        });
    }
});
</script>

@include('layouts.footer')