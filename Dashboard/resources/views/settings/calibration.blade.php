@include('layouts.header')
@include('layouts.topbar')

<style>
    .thin-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
    .thin-scrollbar::-webkit-scrollbar-track { background: #1A1A1A; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb { background: #4B5563; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb:hover { background: #6B7280; }
    .thin-scrollbar { scrollbar-width: thin; scrollbar-color: #4B5563 #1A1A1A; }

    .checklist-tag {
        @apply inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border;
    }
    .checklist-tag-temperature { @apply bg-munti-red-700/20 text-munti-red-400 border-munti-red-600/30; }
    .checklist-tag-humidity { @apply bg-munti-blue-700/20 text-munti-blue-400 border-munti-blue-600/30; }
    .checklist-tag-pressure { @apply bg-munti-yellow-700/20 text-munti-yellow-400 border-munti-yellow-600/30; }
    .checklist-tag-pm25 { @apply bg-munti-purple-700/20 text-munti-purple-400 border-munti-purple-600/30; }
    .checklist-tag-pm10 { @apply bg-munti-orange-700/20 text-munti-orange-400 border-munti-orange-600/30; }
    .checklist-tag-co { @apply bg-munti-gray-700/20 text-munti-gray-400 border-munti-gray-600/30; }
    .checklist-tag-no2 { @apply bg-munti-teal-700/20 text-munti-teal-400 border-munti-teal-600/30; }
    .checklist-tag-o3 { @apply bg-munti-indigo-700/20 text-munti-indigo-400 border-munti-indigo-600/30; }
    .checklist-tag-default { @apply bg-munti-gray-700/20 text-munti-gray-400 border-munti-gray-600/30; }
</style>

<div id="main-content" class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2.5">
                <span class="leading-tight uppercase tracking-wide">Calibration API Management</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">View, edit and manage API for a new calibration reference</span>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-6 px-5 sm:px-8">
            <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm">

                <!-- Table Section -->
                <div class="flex-1 flex flex-col min-h-0">
                    <div class="px-5 py-3 border-b border-border-700 bg-surface-900/40 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider flex items-center gap-2">
                            Calibration API Records
                        </h3>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-500">{{ $calibrations->count() }} Record(s)</span>

                            <button type="button"
                                    onclick="openAddCalibrationModal()"
                                    class="inline-flex items-center gap-1.5 h-8 px-2.5 text-xs font-medium text-munti-green-400 bg-munti-green-700/20 border border-munti-green-600/30 rounded-md hover:bg-munti-green-700/30 transition whitespace-nowrap">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Add API
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto thin-scrollbar flex-1">
                        <table class="min-w-full divide-y divide-border-700">
                            <thead class="bg-surface-900/60 text-[11px] uppercase tracking-wider text-text-500 sticky top-0 z-10">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">No.</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Source</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">File</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Checklist of Data</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Total Data</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">No. of Requests/min</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Created At</th>
                                    <th scope="col" class="px-4 py-3 text-center font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-800">
                                @forelse($calibrations as $index => $cal)
                                <tr class="hover:bg-surface-700/50 transition">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">{{ $index + 1 }}</td>

                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 text-xs text-text-200 transition">
                                            {{ $cal->source }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 text-xs text-text-200 transition">
                                            {{ basename($cal->file_path) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($cal->checklist as $item)
                                                @php
                                                    $slug = strtolower(str_replace([' ', '.'], '-', $item));
                                                    $tagClass = 'checklist-tag-default';
                                                    if (in_array($slug, ['temperature', 'humidity', 'pressure', 'pm25', 'pm10', 'co', 'no2', 'o3'])) {
                                                        $tagClass = 'checklist-tag-' . $slug;
                                                    }
                                                @endphp
                                                <span class="checklist-tag {{ $tagClass }}">{{ $item }}</span>
                                            @endforeach
                                        </div>
                                    </td>

                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 text-xs text-text-200 transition">
                                            {{ number_format($cal->total_data) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 text-xs text-text-200 transition">
                                            {{ $cal->requests_per_min }} req/min
                                        </span>
                                    </td>

                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                        {{ $cal->created_at->format('Y-m-d H:i') }}
                                    </td>

                                    <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" onclick="editCalibration({{ $cal->id }})"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-radar-400 hover:bg-surface-700/70 transition"
                                                    title="Edit">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button type="button" onclick="deleteCalibration({{ $cal->id }}, '{{ basename($cal->file_path) }}')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-munti-red-400 hover:bg-surface-700/70 transition"
                                                    title="Delete">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24" class="text-red-400">
                                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-text-500 text-sm">
                                        No calibration records found.
                                    </td>
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

<!-- ==================== ADD EXTERNAL API MODAL ==================== -->
<div id="addApiModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeAddCalibrationModal()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-6xl bg-surface-900 border border-border-700 rounded-2xl shadow-2xl overflow-hidden">

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-border-700 bg-surface-800 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-text-100">Add External API</h3>
                <button type="button" onclick="closeAddCalibrationModal()" class="p-1.5 rounded-lg text-text-400 hover:text-text-100 hover:bg-surface-700 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">

                    <!-- COLUMN 1: API Source + Auth Type (Bearer Token) + Documentation -->
                    <div class="flex flex-col gap-4">
                        <div>
                            <label class="block text-xs font-medium text-text-400 mb-1.5">Select API Source</label>
                            <select id="apiSource"
                                    class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">
                                <option value="">Choose API</option>
                                <option value="accustation">AccuStation</option>
                                <option value="openweather">OpenWeather</option>
                                <option value="iqair">IQAir</option>
                                <option value="accuweather">AccuWeather</option>
                                <option value="custom">Custom API</option>
                            </select>
                        </div>

                        <!-- Authentication Type – fixed to Bearer Token -->
                        <div>
                            <label class="block text-xs font-medium text-text-400 mb-1.5">Authentication Type</label>
                            <div class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 flex items-center">
                                <span class="text-text-300">Bearer Token</span>
                                <input type="hidden" id="authType" value="bearer_token">
                            </div>
                        </div>

                        <div class="flex-1 flex flex-col">
                            <div class="flex-1 min-h-[250px] p-4 bg-surface-800 border border-border-600 rounded-lg overflow-y-auto thin-scrollbar text-sm text-text-300 leading-relaxed">
                                <label class="block text-xs font-medium text-text-400 mb-1.5">Documentation</label>
                                <div id="docContent" class="space-y-3">
                                    <!-- Dynamic content inserted by JavaScript -->
                                    <p class="text-text-500 italic">Select an API source above to view its documentation and required parameters.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMN 2: API URL + Tips -->
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">API URL</label>
                        <input type="url" id="apiUrl" placeholder="https://api.example.com/v1/endpoint"
                               class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 placeholder-text-500 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">

                        <div class="mt-4 flex-1 p-4 bg-surface-800/50 border border-border-700 rounded-lg text-xs text-text-500">
                            <p class="font-medium text-text-400 mb-2">Tips</p>
                            <ul class="space-y-1.5 list-disc list-inside">
                                <li>Include the full endpoint path</li>
                                <li>Use HTTPS whenever possible</li>
                                <li>Query parameters can be added later</li>
                            </ul>
                        </div>
                    </div>

                    <!-- COLUMN 3: Bearer Token (API Key) + Security Note -->
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">Bearer Token</label>
                        <input type="password" id="apiKey" placeholder="Enter your Bearer token"
                               class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 placeholder-text-500 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">

                        <div class="mt-4 flex-1 p-4 bg-surface-800/50 border border-border-700 rounded-lg text-xs text-text-500">
                            <p class="font-medium text-text-400 mb-2">Security Note</p>
                            <p>Your token is stored encrypted and never exposed in the frontend after saving.</p>
                        </div>
                    </div>

                    <!-- COLUMN 4: Fields to Map to Database (Checkboxes) -->
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">Fields to Map to Database</label>
                        <div class="flex-1 p-3 bg-surface-800/50 border border-border-700 rounded-lg overflow-y-auto thin-scrollbar" style="max-height: 300px;">
                            <div class="grid grid-cols-1 gap-y-1.5">
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="pm25" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>PM2.5</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="pm10" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>PM10</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="tsp" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>TSP</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="ozone" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Ozone</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="carbon_monoxide" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Carbon Monoxide</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="sulfur_dioxide" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Sulfur Dioxide</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="nitrogen_dioxide" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Nitrogen Dioxide</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="temperature" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Temperature</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="humidity" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Humidity</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="rain" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Rain</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="wind_speed" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Wind Speed</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="wind_direction" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Wind Direction</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="air_pressure" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Air Pressure</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="noise" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Noise</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="lead" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Lead</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="lead_temperature" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Lead Temperature</span>
                                </label>
                            </div>
                        </div>
                        <p class="text-[10px] text-text-500 mt-1.5">Select fields from the API response to store.</p>
                    </div>

                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-border-700 bg-surface-800/60 flex items-center justify-end gap-3">
                <button type="button" onclick="closeAddCalibrationModal()"
                        class="h-9 px-4 text-sm font-medium text-text-300 bg-surface-700 border border-border-600 rounded-lg hover:bg-surface-600 transition">
                    Cancel
                </button>
                <button type="button" onclick="saveExternalApi()"
                        class="h-9 px-5 text-sm font-medium text-white bg-munti-green-600 hover:bg-munti-green-500 rounded-lg transition">
                    Save API
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== EDIT EXTERNAL API MODAL ==================== -->
<div id="editApiModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeEditCalibrationModal()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-6xl bg-surface-900 border border-border-700 rounded-2xl shadow-2xl overflow-hidden">

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-border-700 bg-surface-800 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-text-100">Edit External API</h3>
                <button type="button" onclick="closeEditCalibrationModal()" class="p-1.5 rounded-lg text-text-400 hover:text-text-100 hover:bg-surface-700 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">

                    <!-- COLUMN 1: API Source + Auth Type (Bearer Token) + Documentation -->
                    <div class="flex flex-col gap-4">
                        <div>
                            <label class="block text-xs font-medium text-text-400 mb-1.5">Select API Source</label>
                            <select id="editApiSource"
                                    class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">
                                <option value="">-- Choose API --</option>
                                <option value="accustation">AccuStation</option>
                                <option value="openweather">OpenWeather</option>
                                <option value="iqair">IQAir</option>
                                <option value="accuweather">AccuWeather</option>
                                <option value="custom">Custom API</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-text-400 mb-1.5">Authentication Type</label>
                            <div class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 flex items-center">
                                <span class="text-text-300">Bearer Token</span>
                                <input type="hidden" id="editAuthType" value="bearer_token">
                            </div>
                        </div>

                        <div class="flex-1 flex flex-col">
                            <div class="flex-1 min-h-[250px] p-4 bg-surface-800 border border-border-600 rounded-lg overflow-y-auto thin-scrollbar text-sm text-text-300 leading-relaxed">
                                <label class="block text-xs font-medium text-text-400 mb-1.5">Documentation</label>
                                <div id="editDocContent" class="space-y-3">
                                    <!-- Dynamic content -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMN 2: API URL + Tips -->
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">API URL</label>
                        <input type="url" id="editApiUrl" placeholder="https://api.example.com/v1/endpoint"
                               class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 placeholder-text-500 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">

                        <div class="mt-4 flex-1 p-4 bg-surface-800/50 border border-border-700 rounded-lg text-xs text-text-500">
                            <p class="font-medium text-text-400 mb-2">Tips</p>
                            <ul class="space-y-1.5 list-disc list-inside">
                                <li>Include the full endpoint path</li>
                                <li>Use HTTPS whenever possible</li>
                                <li>Query parameters can be added later</li>
                            </ul>
                        </div>
                    </div>

                    <!-- COLUMN 3: Bearer Token -->
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">Bearer Token</label>
                        <input type="password" id="editApiKey" placeholder="Enter your Bearer token"
                               class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 placeholder-text-500 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">

                        <div class="mt-4 flex-1 p-4 bg-surface-800/50 border border-border-700 rounded-lg text-xs text-text-500">
                            <p class="font-medium text-text-400 mb-2">Security Note</p>
                            <p>Your token is stored encrypted and never exposed in the frontend after saving.</p>
                        </div>
                    </div>

                    <!-- COLUMN 4: Fields to Map to Database (Checkboxes) -->
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">Fields to Map to Database</label>
                        <div class="flex-1 p-3 bg-surface-800/50 border border-border-700 rounded-lg overflow-y-auto thin-scrollbar" style="max-height: 300px;">
                            <div class="grid grid-cols-1 gap-y-1.5">
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="pm25" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>PM2.5</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="pm10" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>PM10</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="tsp" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>TSP</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="ozone" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Ozone</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="carbon_monoxide" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Carbon Monoxide</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="sulfur_dioxide" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Sulfur Dioxide</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="nitrogen_dioxide" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Nitrogen Dioxide</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="temperature" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Temperature</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="humidity" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Humidity</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="rain" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Rain</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="wind_speed" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Wind Speed</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="wind_direction" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Wind Direction</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="air_pressure" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Air Pressure</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="noise" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Noise</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="lead" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Lead</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="lead_temperature" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>Lead Temperature</span>
                                </label>
                            </div>
                        </div>
                        <p class="text-[10px] text-text-500 mt-1.5">Select fields from the API response to store.</p>
                    </div>

                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-border-700 bg-surface-800/60 flex items-center justify-end gap-3">
                <button type="button" onclick="closeEditCalibrationModal()"
                        class="h-9 px-4 text-sm font-medium text-text-300 bg-surface-700 border border-border-600 rounded-lg hover:bg-surface-600 transition">
                    Cancel
                </button>
                <button type="button" onclick="updateExternalApi()"
                        class="h-9 px-5 text-sm font-medium text-white bg-munti-blue-600 hover:bg-munti-blue-500 rounded-lg transition">
                    Update API
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // ========== HELPERS ==========
    const docMap = {
        accustation: {
            endpoint: 'https://api.accustation.com/v1/data',
            rate: '60 requests / minute',
            params: 'apikey, station_id',
            auth: 'Bearer Token'
        },
        openweather: {
            endpoint: 'https://api.openweathermap.org/data/2.5/weather',
            rate: '60 calls/minute (free tier)',
            params: 'appid, q, units',
            auth: 'Bearer Token'
        },
        iqair: {
            endpoint: 'https://api.iqair.com/v2/',
            rate: '10,000 calls/month (free)',
            params: 'api_key, city',
            auth: 'Bearer Token'
        },
        accuweather: {
            endpoint: 'http://dataservice.accuweather.com/',
            rate: '500 calls/day (Free tier) / higher for paid plans',
            params: 'locationKey, metric etc. (token sent as Bearer)',
            auth: 'Bearer Token',
            endpoints: 'Current Conditions, Hourly/Daily Forecasts, Alerts, Indices, and more'
        },
        custom: {
            endpoint: 'Your custom endpoint',
            rate: 'Depends on your service',
            params: 'Define your own',
            auth: 'Bearer Token'
        }
    };

    function updateDocContent(docDiv, source) {
        if (source && docMap[source]) {
            const info = docMap[source];
            let html = `
                <p><strong class="text-text-200">Base Endpoint:</strong></p>
                <code class="block text-xs bg-surface-900 px-2 py-1.5 rounded text-munti-blue-300">${info.endpoint}</code>
                <p class="mt-3"><strong class="text-text-200">Rate Limit:</strong> ${info.rate}</p>
                <p class="mt-2"><strong class="text-text-200">Required Parameters:</strong> ${info.params}</p>
                <p class="mt-2"><strong class="text-text-200">Authentication:</strong> ${info.auth}</p>
            `;
            if (info.endpoints) {
                html += `<p class="mt-2"><strong class="text-text-200">Available Endpoints:</strong> ${info.endpoints}</p>`;
            }
            docDiv.innerHTML = html;
            docDiv.classList.remove('hidden');
        } else {
            docDiv.innerHTML = `<p class="text-text-500 italic">Select an API source above to view its documentation and required parameters.</p>`;
            docDiv.classList.add('hidden');
        }
    }

    // ========== ADD MODAL ==========
    function openAddCalibrationModal() {
        document.getElementById('addApiModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeAddCalibrationModal() {
        document.getElementById('addApiModal').classList.add('hidden');
        document.body.style.overflow = '';
        // reset form
        document.getElementById('apiSource').value = '';
        document.getElementById('apiUrl').value = '';
        document.getElementById('apiKey').value = '';
        // authType hidden remains 'bearer_token'
        updateDocContent(document.getElementById('docContent'), '');
    }

    document.getElementById('apiSource')?.addEventListener('change', function () {
        const docDiv = document.getElementById('docContent');
        updateDocContent(docDiv, this.value);
        // Optionally pre-fill URL placeholder for AccuWeather
        const urlField = document.getElementById('apiUrl');
        if (this.value === 'accuweather') {
            urlField.placeholder = 'http://dataservice.accuweather.com/currentconditions/v1/{locationKey}';
        } else {
            urlField.placeholder = 'https://api.example.com/v1/endpoint';
        }
    });

    function saveExternalApi() {
        const source = document.getElementById('apiSource').value;
        const url = document.getElementById('apiUrl').value;
        const token = document.getElementById('apiKey').value;
        const authType = document.getElementById('authType').value; // 'bearer_token'

        if (!source || !url || !token) {
            alert('Please fill in all fields.');
            return;
        }

        // TODO: Send to backend (POST /settings/calibration)
        alert(`Saved!\nSource: ${source}\nURL: ${url}\nAuth Type: ${authType}`);
        closeAddCalibrationModal();
        // window.location.reload();
    }

    // ========== EDIT MODAL ==========
    function editCalibration(id) {
        // In a real app, fetch the record via AJAX (GET /settings/calibration/{id})
        // For demo, we use sample data.
        const sampleData = {
            1: {
                source: 'accustation',
                url: 'https://api.accustation.com/v1/data',
                token: 'sk_live_************************',
                authType: 'bearer_token'
            },
            2: {
                source: 'openweather',
                url: 'https://api.openweathermap.org/data/2.5/weather',
                token: 'ow_1234567890',
                authType: 'bearer_token'
            },
            3: {
                source: 'accuweather',
                url: 'http://dataservice.accuweather.com/currentconditions/v1/12345',
                token: 'accu_abcdef12345',
                authType: 'bearer_token'
            }
        };

        const data = sampleData[id] || sampleData[1];

        document.getElementById('editApiSource').value = data.source;
        document.getElementById('editApiUrl').value = data.url;
        document.getElementById('editApiKey').value = data.token;
        // editAuthType hidden already has 'bearer_token'

        // Update documentation
        const docDiv = document.getElementById('editDocContent');
        updateDocContent(docDiv, data.source);

        document.getElementById('editApiModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeEditCalibrationModal() {
        document.getElementById('editApiModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Event listeners for edit modal
    document.getElementById('editApiSource')?.addEventListener('change', function () {
        const docDiv = document.getElementById('editDocContent');
        updateDocContent(docDiv, this.value);
        const urlField = document.getElementById('editApiUrl');
        if (this.value === 'accuweather') {
            urlField.placeholder = 'http://dataservice.accuweather.com/currentconditions/v1/{locationKey}';
        } else {
            urlField.placeholder = 'https://api.example.com/v1/endpoint';
        }
    });

    function updateExternalApi() {
        const source = document.getElementById('editApiSource').value;
        const url = document.getElementById('editApiUrl').value;
        const token = document.getElementById('editApiKey').value;
        const authType = document.getElementById('editAuthType').value; // 'bearer_token'

        if (!source || !url || !token) {
            alert('Please fill in all fields.');
            return;
        }

        alert(`Updated!\nSource: ${source}\nURL: ${url}\nAuth Type: ${authType}`);
        closeEditCalibrationModal();
        // window.location.reload();
    }

    // ========== DELETE ==========
    function deleteCalibration(id, fileName) {
        Swal.fire({
            title: 'Delete Calibration?',
            html: `Are you sure you want to delete the calibration record for <strong>"${fileName}"</strong>?<br>
                   <span style="color: #ef4444;">This action cannot be undone!</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            background: '#1f2937',
            color: '#f3f4f6',
            iconColor: '#ef4444'
        }).then((result) => {
            if (result.isConfirmed) {
                // TODO: Send DELETE request
                alert(`Deleted calibration #${id}`);
                // window.location.reload();
            }
        });
    }

    // ========== CLOSE ON ESC ==========
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAddCalibrationModal();
            closeEditCalibrationModal();
        }
    });
</script>

@include('layouts.footer')