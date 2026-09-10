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

    .json-viewer {
        background: #0d1117;
        color: #e6edf3;
        font-family: 'JetBrains Mono', 'Fira Code', monospace;
        font-size: 13px;
        line-height: 1.6;
        padding: 1rem;
        border-radius: 0.5rem;
        max-height: 60vh;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-all;
    }
    .json-viewer .json-key { color: #ff7b72; }
    .json-viewer .json-string { color: #a5d6ff; }
    .json-viewer .json-number { color: #79c0ff; }
    .json-viewer .json-boolean { color: #ffa657; }
    .json-viewer .json-null { color: #8b949e; }
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
                                            {{ $cal->file_path ? basename($cal->file_path) : '—' }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($cal->checklist ?? [] as $item)
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
                                            <button type="button" onclick="viewJson({{ $cal->id }})"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-munti-blue-400 hover:bg-surface-700/70 transition"
                                                    title="View JSON">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                            <button type="button" onclick="editCalibration({{ $cal->id }})"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-radar-400 hover:bg-surface-700/70 transition"
                                                    title="Edit">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button type="button" onclick="deleteCalibration({{ $cal->id }}, '{{ $cal->file_path ? basename($cal->file_path) : 'Record #' . $cal->id }}')"
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

<!-- ==================== VIEW JSON MODAL ==================== -->
<div id="viewJsonModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeViewJsonModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-4xl bg-surface-900 border border-border-700 rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-border-700 bg-surface-800 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-text-100">JSON Data</h3>
                <button type="button" onclick="closeViewJsonModal()" class="p-1.5 rounded-lg text-text-400 hover:text-text-100 hover:bg-surface-700 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6">
                <div class="json-viewer" id="jsonContent"></div>
            </div>
            <div class="px-6 py-4 border-t border-border-700 bg-surface-800/60 flex items-center justify-end">
                <button type="button" onclick="closeViewJsonModal()"
                        class="h-9 px-4 text-sm font-medium text-text-300 bg-surface-700 border border-border-600 rounded-lg hover:bg-surface-600 transition">
                    Close
                </button>
                <button type="button" onclick="copyJsonToClipboard()"
                        class="ml-3 h-9 px-4 text-sm font-medium text-white bg-munti-blue-600 hover:bg-munti-blue-500 rounded-lg transition">
                    Copy JSON
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== ADD EXTERNAL API MODAL ==================== -->
<div id="addApiModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeAddCalibrationModal()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-6xl bg-surface-900 border border-border-700 rounded-2xl shadow-2xl overflow-hidden">

            <div class="px-6 py-4 border-b border-border-700 bg-surface-800 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-text-100">Add External API</h3>
                <button type="button" onclick="closeAddCalibrationModal()" class="p-1.5 rounded-lg text-text-400 hover:text-text-100 hover:bg-surface-700 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">

                    <!-- COLUMN 1 -->
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
                                    <p class="text-text-500 italic">Select an API source above to view its documentation and required parameters.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMN 2 -->
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

                    <!-- COLUMN 3 -->
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">Bearer Token</label>
                        <input type="password" id="apiKey" placeholder="Enter your Bearer token"
                               class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 placeholder-text-500 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">

                        <div class="mt-4 flex-1 p-4 bg-surface-800/50 border border-border-700 rounded-lg text-xs text-text-500">
                            <p class="font-medium text-text-400 mb-2">Security Note</p>
                            <p>Your token is stored encrypted and never exposed in the frontend after saving.</p>
                        </div>
                    </div>

                    <!-- COLUMN 4 -->
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">Fields to Map to Database</label>
                        <div class="flex-1 p-3 bg-surface-800/50 border border-border-700 rounded-lg overflow-y-auto thin-scrollbar" style="max-height: 300px;">
                            <div class="grid grid-cols-1 gap-y-1.5">
                                @php
                                    $fields = [
                                        'pm25' => 'PM2.5', 'pm10' => 'PM10', 'tsp' => 'TSP',
                                        'ozone' => 'Ozone', 'carbon_monoxide' => 'Carbon Monoxide',
                                        'sulfur_dioxide' => 'Sulfur Dioxide', 'nitrogen_dioxide' => 'Nitrogen Dioxide',
                                        'temperature' => 'Temperature', 'humidity' => 'Humidity',
                                        'rain' => 'Rain', 'wind_speed' => 'Wind Speed',
                                        'wind_direction' => 'Wind Direction', 'air_pressure' => 'Air Pressure',
                                        'noise' => 'Noise', 'lead' => 'Lead', 'lead_temperature' => 'Lead Temperature',
                                    ];
                                @endphp
                                @foreach($fields as $value => $label)
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="add_params" value="{{ $value }}" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>{{ $label }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        <p class="text-[10px] text-text-500 mt-1.5">Select fields from the API response to store.</p>
                    </div>

                </div>

                <!-- Test Result Panel (Add) -->
                <div id="addTestResult" class="hidden mt-4 p-3 rounded-lg border text-xs"></div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-border-700 bg-surface-800/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <button type="button" onclick="testAddApi()" id="addTestBtn"
                        class="inline-flex items-center justify-center gap-2 h-9 px-4 text-sm font-medium text-munti-yellow-400 bg-munti-yellow-700/20 border border-munti-yellow-600/30 rounded-lg hover:bg-munti-yellow-700/30 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span>Test API</span>
                </button>
                <div class="flex items-center gap-3 sm:justify-end">
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
</div>

<!-- ==================== EDIT EXTERNAL API MODAL ==================== -->
<div id="editApiModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeEditCalibrationModal()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-6xl bg-surface-900 border border-border-700 rounded-2xl shadow-2xl overflow-hidden">

            <div class="px-6 py-4 border-b border-border-700 bg-surface-800 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-text-100">Edit External API</h3>
                <button type="button" onclick="closeEditCalibrationModal()" class="p-1.5 rounded-lg text-text-400 hover:text-text-100 hover:bg-surface-700 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">

                    <!-- COLUMN 1 -->
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
                                <div id="editDocContent" class="space-y-3"></div>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMN 2 -->
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

                    <!-- COLUMN 3 -->
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">Bearer Token</label>
                        <input type="password" id="editApiKey" placeholder="Leave blank to keep current token"
                               class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 placeholder-text-500 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">

                        <div class="mt-4 flex-1 p-4 bg-surface-800/50 border border-border-700 rounded-lg text-xs text-text-500">
                            <p class="font-medium text-text-400 mb-2">Security Note</p>
                            <p>Your token is stored encrypted and never exposed in the frontend after saving.</p>
                        </div>
                    </div>

                    <!-- COLUMN 4 -->
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">Fields to Map to Database</label>
                        <div class="flex-1 p-3 bg-surface-800/50 border border-border-700 rounded-lg overflow-y-auto thin-scrollbar" style="max-height: 300px;">
                            <div class="grid grid-cols-1 gap-y-1.5">
                                @foreach($fields as $value => $label)
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="{{ $value }}" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition">
                                    <span>{{ $label }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        <p class="text-[10px] text-text-500 mt-1.5">Select fields from the API response to store.</p>
                    </div>

                </div>

                <!-- Test Result Panel (Edit) -->
                <div id="editTestResult" class="hidden mt-4 p-3 rounded-lg border text-xs"></div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-border-700 bg-surface-800/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <button type="button" onclick="testEditApi()" id="editTestBtn"
                        class="inline-flex items-center justify-center gap-2 h-9 px-4 text-sm font-medium text-munti-yellow-400 bg-munti-yellow-700/20 border border-munti-yellow-600/30 rounded-lg hover:bg-munti-yellow-700/30 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span>Test API</span>
                </button>
                <div class="flex items-center gap-3 sm:justify-end">
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

    let addApiTested = false;
    let editApiTested = false;

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

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function sendRequest(method, url, data, successCallback, errorCallback) {
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw err; });
            }
            return response.json();
        })
        .then(data => {
            if (successCallback) successCallback(data);
        })
        .catch(error => {
            if (errorCallback) errorCallback(error);
            else {
                let msg = error.errors ? Object.values(error.errors).flat().join('\n') : error.message || 'Something went wrong.';
                Swal.fire('Error', msg, 'error');
            }
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // ========== TEST API ==========
    function renderTestResult(containerId, result) {
        const container = document.getElementById(containerId);
        container.classList.remove('hidden', 'bg-munti-green-700/10', 'bg-munti-red-700/10',
            'border-munti-green-600/30', 'border-munti-red-600/30', 'text-munti-green-300', 'text-munti-red-300');

        if (result.success) {
            container.classList.add('bg-munti-green-700/10', 'border-munti-green-600/30', 'text-munti-green-300');
            let previewHtml = '';
            if (result.preview) {
                const previewStr = typeof result.preview === 'string'
                    ? result.preview
                    : JSON.stringify(result.preview, null, 2);
                const truncated = previewStr.length > 1500
                    ? previewStr.substring(0, 1500) + '\n... (truncated)'
                    : previewStr;
                previewHtml = `<pre class="mt-2 p-2 bg-black/40 rounded text-[11px] whitespace-pre-wrap break-all max-h-48 overflow-y-auto thin-scrollbar">${escapeHtml(truncated)}</pre>`;
            }
            container.innerHTML = `
                <div class="flex items-center gap-2 font-medium">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>${result.message}</span>
                    <span class="ml-auto text-[10px] opacity-75">HTTP ${result.status || '—'} · ${result.duration_ms || 0} ms</span>
                </div>
                ${previewHtml}
            `;
        } else {
            container.classList.add('bg-munti-red-700/10', 'border-munti-red-600/30', 'text-munti-red-300');
            container.innerHTML = `
                <div class="flex items-center gap-2 font-medium">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span>${result.message || 'API test failed.'}</span>
                    ${result.status ? `<span class="ml-auto text-[10px] opacity-75">HTTP ${result.status}${result.duration_ms ? ' · ' + result.duration_ms + ' ms' : ''}</span>` : ''}
                </div>
                ${result.error ? `<pre class="mt-2 p-2 bg-black/40 rounded text-[11px] whitespace-pre-wrap break-all">${escapeHtml(result.error)}</pre>` : ''}
            `;
        }
    }

    function runApiTest({ source, url, token, authType, resultContainer, buttonEl, isEdit }) {
        const container = document.getElementById(resultContainer);
        container.classList.add('hidden');
        container.innerHTML = '';

        if (!url || !token) {
            Swal.fire('Validation Error', 'Please fill in the API URL and Bearer Token before testing.', 'warning');
            return;
        }

        const originalHtml = buttonEl.innerHTML;
        buttonEl.disabled = true;
        buttonEl.innerHTML = `
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span>Testing...</span>
        `;

        sendRequest(
            'POST',
            '/settings/calibration/test',
            { source, api_url: url, api_token: token, auth_type: authType },
            (data) => {
                renderTestResult(resultContainer, data);
                if (isEdit) {
                    editApiTested = data.success;
                } else {
                    addApiTested = data.success;
                }
                buttonEl.disabled = false;
                buttonEl.innerHTML = originalHtml;
            },
            (error) => {
                renderTestResult(resultContainer, {
                    success: false,
                    message: error.message || 'Test request failed.',
                    error: error.errors ? Object.values(error.errors).flat().join('\n') : null,
                });
                if (isEdit) {
                    editApiTested = false;
                } else {
                    addApiTested = false;
                }
                buttonEl.disabled = false;
                buttonEl.innerHTML = originalHtml;
            }
        );
    }

    function testAddApi() {
        runApiTest({
            source: document.getElementById('apiSource').value,
            url: document.getElementById('apiUrl').value,
            token: document.getElementById('apiKey').value,
            authType: document.getElementById('authType').value,
            resultContainer: 'addTestResult',
            buttonEl: document.getElementById('addTestBtn'),
            isEdit: false,
        });
    }

    function testEditApi() {
        runApiTest({
            source: document.getElementById('editApiSource').value,
            url: document.getElementById('editApiUrl').value,
            token: document.getElementById('editApiKey').value,
            authType: document.getElementById('editAuthType').value,
            resultContainer: 'editTestResult',
            buttonEl: document.getElementById('editTestBtn'),
            isEdit: true,
        });
    }

    // ========== VIEW JSON ==========
    let currentJsonData = null;

    function viewJson(id) {
        fetch(`/settings/calibration/${id}`, {
            headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
        })
        .then(res => {
            if (!res.ok) throw new Error('Failed to fetch JSON');
            return res.json();
        })
        .then(data => {
            currentJsonData = data;
            displayJson(data);
            document.getElementById('viewJsonModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        })
        .catch(err => {
            Swal.fire('Error', 'Could not load JSON data.', 'error');
        });
    }

    function displayJson(data) {
        const container = document.getElementById('jsonContent');
        const jsonString = JSON.stringify(data, null, 4);
        const highlighted = jsonString
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"([^"]+)":/g, '<span class="json-key">"$1"</span>:')
            .replace(/: "([^"]+)"/g, ': <span class="json-string">"$1"</span>')
            .replace(/: (\d+\.?\d*)/g, ': <span class="json-number">$1</span>')
            .replace(/: (true|false)/g, ': <span class="json-boolean">$1</span>')
            .replace(/: (null)/g, ': <span class="json-null">$1</span>');
        container.innerHTML = highlighted;
    }

    function closeViewJsonModal() {
        document.getElementById('viewJsonModal').classList.add('hidden');
        document.body.style.overflow = '';
        currentJsonData = null;
    }

    function copyJsonToClipboard() {
        if (!currentJsonData) return;
        const jsonString = JSON.stringify(currentJsonData, null, 4);
        navigator.clipboard.writeText(jsonString).then(() => {
            const btn = document.querySelector('#viewJsonModal .bg-munti-blue-600');
            const originalText = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = originalText; }, 2000);
        }).catch(() => {
            alert('Could not copy JSON. Please select and copy manually.');
        });
    }

    // ========== ADD MODAL ==========
    function openAddCalibrationModal() {
        addApiTested = false;
        document.getElementById('addTestResult').classList.add('hidden');
        document.getElementById('addTestResult').innerHTML = '';
        document.getElementById('addApiModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeAddCalibrationModal() {
        document.getElementById('addApiModal').classList.add('hidden');
        document.body.style.overflow = '';
        document.getElementById('apiSource').value = '';
        document.getElementById('apiUrl').value = '';
        document.getElementById('apiKey').value = '';
        document.getElementById('addTestResult').classList.add('hidden');
        document.getElementById('addTestResult').innerHTML = '';
        addApiTested = false;
        updateDocContent(document.getElementById('docContent'), '');
    }

    document.getElementById('apiSource')?.addEventListener('change', function () {
        addApiTested = false;
        const docDiv = document.getElementById('docContent');
        updateDocContent(docDiv, this.value);
        const urlField = document.getElementById('apiUrl');
        if (this.value === 'accuweather') {
            urlField.placeholder = 'http://dataservice.accuweather.com/currentconditions/v1/{locationKey}';
        } else {
            urlField.placeholder = 'https://api.example.com/v1/endpoint';
        }
    });

    function saveExternalApi() {
        if (!addApiTested) {
            Swal.fire('Test Required', 'Please run a successful API test before saving.', 'warning');
            return;
        }

        const source = document.getElementById('apiSource').value;
        const url = document.getElementById('apiUrl').value;
        const token = document.getElementById('apiKey').value;
        const authType = document.getElementById('authType').value;

        const checklist = Array.from(document.querySelectorAll('input[name="add_params"]:checked'))
                               .map(cb => cb.value);

        if (!source || !url || !token) {
            Swal.fire('Validation Error', 'Please fill in all fields.', 'warning');
            return;
        }

        const payload = {
            source,
            api_url: url,
            api_token: token,
            auth_type: authType,
            checklist,
            total_data: 0,
            requests_per_min: 0,
            file_path: null,
        };

        sendRequest('POST', '/settings/calibration', payload, (data) => {
            Swal.fire('Success', data.message, 'success');
            closeAddCalibrationModal();
            window.location.reload();
        });
    }

    // ========== EDIT MODAL ==========
    function editCalibration(id) {
        editApiTested = false;
        document.getElementById('editTestResult').classList.add('hidden');
        document.getElementById('editTestResult').innerHTML = '';

        fetch(`/settings/calibration/${id}`, {
            headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
        })
        .then(res => {
            if (!res.ok) throw new Error('Failed to fetch record');
            return res.json();
        })
        .then(data => {
            document.getElementById('editApiSource').value = data.source;
            document.getElementById('editApiUrl').value = data.api_url;
            document.getElementById('editApiKey').value = '';

            const checklist = data.checklist || [];
            document.querySelectorAll('input[name="edit_params"]').forEach(cb => {
                cb.checked = checklist.includes(cb.value);
            });

            const docDiv = document.getElementById('editDocContent');
            updateDocContent(docDiv, data.source);

            document.getElementById('editApiModal').dataset.id = id;
            document.getElementById('editApiModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        })
        .catch(err => {
            Swal.fire('Error', 'Could not load record for editing.', 'error');
        });
    }

    function closeEditCalibrationModal() {
        document.getElementById('editApiModal').classList.add('hidden');
        document.body.style.overflow = '';
        document.getElementById('editTestResult').classList.add('hidden');
        document.getElementById('editTestResult').innerHTML = '';
        editApiTested = false;
    }

    document.getElementById('editApiSource')?.addEventListener('change', function () {
        editApiTested = false;
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
        if (!editApiTested) {
            Swal.fire('Test Required', 'Please run a successful API test before updating.', 'warning');
            return;
        }

        const id = document.getElementById('editApiModal').dataset.id;
        if (!id) {
            Swal.fire('Error', 'No record ID found.', 'error');
            return;
        }

        const source = document.getElementById('editApiSource').value;
        const url = document.getElementById('editApiUrl').value;
        const token = document.getElementById('editApiKey').value;
        const authType = document.getElementById('editAuthType').value;

        const checklist = Array.from(document.querySelectorAll('input[name="edit_params"]:checked'))
                               .map(cb => cb.value);

        if (!source || !url) {
            Swal.fire('Validation Error', 'Please fill in required fields.', 'warning');
            return;
        }

        const payload = {
            source,
            api_url: url,
            auth_type: authType,
            checklist,
        };

        if (token && token.trim() !== '') {
            payload.api_token = token;
        }

        sendRequest('PUT', `/settings/calibration/${id}`, payload, (data) => {
            Swal.fire('Success', data.message, 'success');
            closeEditCalibrationModal();
            window.location.reload();
        });
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
                sendRequest('DELETE', `/settings/calibration/${id}`, {}, (data) => {
                    Swal.fire('Deleted!', data.message, 'success');
                    window.location.reload();
                });
            }
        });
    }

    // ========== CLOSE ON ESC ==========
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeViewJsonModal();
            closeAddCalibrationModal();
            closeEditCalibrationModal();
        }
    });
</script>

@include('layouts.footer')