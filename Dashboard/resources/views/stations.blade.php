@include('layouts.header')
@include('layouts.topbar')

<style>
    .thin-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
    .thin-scrollbar::-webkit-scrollbar-track { background: #1A1A1A; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb { background: #4B5563; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb:hover { background: #6B7280; }
    .thin-scrollbar { scrollbar-width: thin; scrollbar-color: #4B5563 #1A1A1A; }
</style>

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
                    <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-radar-400"></span>
                        Add New Station
                    </h3>

                    <form action="{{ route('stations.store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Station MN -->
                            <div class="flex flex-col">
                                <label for="station_mn" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Station MN <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="text" id="station_mn" name="station_mn" value="{{ old('station_mn') }}" required
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('station_mn') border-munti-red-500 @enderror">
                                @error('station_mn')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Station Name -->
                            <div class="flex flex-col">
                                <label for="station_name" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Station Name
                                </label>
                                <input type="text" id="station_name" name="station_name" value="{{ old('station_name') }}"
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('station_name') border-munti-red-500 @enderror">
                                @error('station_name')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Latitude -->
                            <div class="flex flex-col">
                                <label for="latitude" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Latitude
                                </label>
                                <input type="number" step="any" id="latitude" name="latitude" value="{{ old('latitude') }}"
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
                                <input type="number" step="any" id="longitude" name="longitude" value="{{ old('longitude') }}"
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('longitude') border-munti-red-500 @enderror">
                                @error('longitude')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Lead IP -->
                            <div class="flex flex-col">
                                <label for="lead_ip" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Lead IP
                                </label>
                                <input type="text" id="lead_ip" name="lead_ip" value="{{ old('lead_ip') }}"
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('lead_ip') border-munti-red-500 @enderror">
                                @error('lead_ip')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Lead Port -->
                            <div class="flex flex-col">
                                <label for="lead_port" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Lead Port
                                </label>
                                <input type="number" id="lead_port" name="lead_port" value="{{ old('lead_port') }}"
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('lead_port') border-munti-red-500 @enderror">
                                @error('lead_port')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Lead Slave -->
                            <div class="flex flex-col">
                                <label for="lead_slave" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Lead Slave
                                </label>
                                <input type="number" id="lead_slave" name="lead_slave" value="{{ old('lead_slave') }}"
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('lead_slave') border-munti-red-500 @enderror">
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
                                    <div class="flex items-center flex-1 h-11 px-3.5 border border-border-600 rounded-lg bg-surface-900">
                                        <input type="hidden" name="enabled" value="0">
                                        <input type="checkbox" id="enabled" name="enabled" value="1"
                                            {{ old('enabled', true) ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-border-600 bg-surface-900 text-munti-green-600 focus:ring-munti-green-500 focus:ring-offset-0">
                                        <label for="enabled" class="ml-2 text-sm text-text-300 select-none cursor-pointer">Enabled</label>
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
                    </form>
                </div>

                <!-- Table Section -->
                <div class="flex-1 flex flex-col min-h-0">
                    <div class="px-5 py-3 border-b border-border-700 bg-surface-900/40">
                        <h4 class="text-xs font-semibold text-text-400 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-munti-green-400"></span>
                            Existing Stations
                        </h4>
                    </div>

                    <div class="overflow-x-auto thin-scrollbar flex-1">
                        @if($stations->count())
                            <table class="min-w-full divide-y divide-border-700">
                                <thead class="bg-surface-900/60 text-[11px] uppercase tracking-wider text-text-500">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">MN</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Name</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Enabled</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Latitude</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Longitude</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Lead IP</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Lead Port</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Lead Slave</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Updated At</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border-800">
                                    @foreach($stations as $station)
                                        <tr class="hover:bg-surface-700/50 transition">
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
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $station->latitude }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $station->longitude }}
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
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                                {{ $station->updated_at ? $station->updated_at->format('Y-m-d H:i') : '' }}
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

@include('layouts.footer')