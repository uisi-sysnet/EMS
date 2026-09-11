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
        font-family: 'JetBrains Mono', 'Fira Code', ui-monospace, monospace;
        font-size: 12.5px;
        line-height: 1.65;
        border-radius: 0.5rem;
        overflow-x: auto;
        white-space: pre;
        word-break: normal;
        counter-reset: line;
    }
    @media (min-width: 640px) {
        .json-viewer { font-size: 13px; }
    }
    .json-viewer.with-lines .json-line::before {
        counter-increment: line;
        content: counter(line);
        display: inline-block;
        width: 3em;
        padding-right: 1em;
        margin-right: 0.75em;
        text-align: right;
        color: #4b5563;
        border-right: 1px solid #1f2937;
        user-select: none;
    }
    .json-viewer .json-line {
        display: block;
        padding: 0 0.75rem;
    }
    .json-viewer .json-line:hover { background: rgba(255,255,255,0.03); }
    .json-viewer .json-key { color: #ff7b72; }
    .json-viewer .json-string { color: #a5d6ff; }
    .json-viewer .json-number { color: #79c0ff; }
    .json-viewer .json-boolean { color: #ffa657; }
    .json-viewer .json-null { color: #8b949e; font-style: italic; }
    .json-viewer .json-punct { color: #8b949e; }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.02em;
        border: 1px solid;
    }
    .status-2xx { background: rgba(34,197,94,0.12); color: #4ade80; border-color: rgba(34,197,94,0.3); }
    .status-3xx { background: rgba(59,130,246,0.12); color: #60a5fa; border-color: rgba(59,130,246,0.3); }
    .status-4xx { background: rgba(249,115,22,0.12); color: #fb923c; border-color: rgba(249,115,22,0.3); }
    .status-5xx { background: rgba(239,68,68,0.12);  color: #f87171; border-color: rgba(239,68,68,0.3); }
    .status-err { background: rgba(239,68,68,0.12);  color: #f87171; border-color: rgba(239,68,68,0.3); }

    .json-skeleton {
        display: inline-block;
        height: 12px;
        width: 100%;
        border-radius: 4px;
        background: linear-gradient(90deg, #1f2937 25%, #374151 50%, #1f2937 75%);
        background-size: 200% 100%;
        animation: shimmer 1.4s infinite;
    }
    @keyframes shimmer {
        0%   { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
</style>

<div id="main-content" class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2.5">
                <span class="leading-tight uppercase tracking-wide">Calibration API Management</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">View, edit and manage API for a new calibration reference</span>
        </div>

        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-4 sm:py-6 px-4 sm:px-8">
            <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm">

                <div class="flex-1 flex flex-col min-h-0">
                    <div class="px-4 sm:px-5 py-3 border-b border-border-700 bg-surface-900/40 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider flex items-center gap-2">
                            Calibration API Records
                        </h3>
                        <div class="flex items-center gap-3 justify-between sm:justify-end">
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
                                        <span class="inline-flex items-center gap-1.5 text-xs text-text-200">{{ $cal->source }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 text-xs text-text-200">{{ $cal->file_path ? basename($cal->file_path) : '—' }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <div class="flex flex-wrap gap-1 max-w-md">
                                            @foreach($cal->checklist ?? [] as $item)
                                                @php
                                                    $slug = strtolower(str_replace([' ', '.'], '-', $item));
                                                    $tagClass = 'checklist-tag-default';
                                                    if (in_array($slug, ['temperature', 'humidity', 'pressure', 'pm25', 'pm10', 'co', 'no2', 'o3'])) {
                                                        $tagClass = 'checklist-tag-' . $slug;
                                                    }
                                                @endphp
                                                <span class="checklist-tag text-xs text-text-200 {{ $tagClass }}" title="{{ $item }}">{{ $item }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-200">{{ number_format($cal->total_data) }}</td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-200">{{ $cal->requests_per_min }} req/min</td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">{{ $cal->created_at->format('Y-m-d H:i') }}</td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" onclick="viewJson({{ $cal->id }})"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-munti-blue-400 hover:bg-surface-700/70 transition"
                                                    title="Inspect live API response">
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

<!-- ==================== VIEW API RESPONSE MODAL ==================== -->
<div id="viewJsonModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeViewJsonModal()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
        <div class="relative w-full h-full sm:h-auto sm:max-w-5xl bg-surface-900 border-0 sm:border border-border-700 rounded-none sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-full">

            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-700 bg-gradient-to-r from-surface-800 to-surface-800/60 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-munti-blue-600/20 border border-munti-blue-600/30 flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-text-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm sm:text-base font-semibold text-text-100 truncate">API Response Inspector</h3>
                        <p class="text-[11px] text-text-500 truncate" id="jsonModalSubtitle">Live response from saved endpoint</p>
                    </div>
                </div>
                <button type="button" onclick="closeViewJsonModal()" class="p-1.5 rounded-lg text-text-400 hover:text-text-100 hover:bg-surface-700 transition shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div id="jsonStatusStrip" class="hidden px-4 sm:px-6 py-2.5 border-b border-border-700 bg-surface-800/40 flex-wrap items-center gap-x-4 gap-y-1.5 text-[11px] shrink-0"></div>

            <div class="px-4 sm:px-6 py-2 border-b border-border-700 bg-surface-800/30 flex items-center justify-between gap-2 shrink-0">
                <div class="flex items-center gap-1">
                    <button type="button" id="jsonViewPretty" onclick="setJsonViewMode('pretty')"
                            class="px-2.5 py-1 text-[11px] font-medium rounded-md text-text-100 bg-munti-blue-600/15 border border-munti-blue-600/30">
                        Pretty
                    </button>
                    <button type="button" id="jsonViewRaw" onclick="setJsonViewMode('raw')"
                            class="px-2.5 py-1 text-[11px] font-medium rounded-md text-text-100 hover:bg-surface-700 transition">
                        Raw
                    </button>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" onclick="toggleLineNumbers()" id="jsonToggleLines"
                            class="p-1.5 rounded-md text-text-400 hover:text-text-100 hover:bg-surface-700 transition" title="Toggle line numbers">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <button type="button" onclick="viewJsonReload()" id="jsonReloadBtn"
                            class="p-1.5 rounded-md text-text-400 hover:text-munti-green-400 hover:bg-surface-700 transition" title="Reload">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="p-3 sm:p-4 overflow-y-auto thin-scrollbar flex-1 bg-[#0d1117]">
                <div id="jsonContent" class="min-h-[200px]"></div>
            </div>

            <div class="px-4 sm:px-6 py-3 sm:py-4 border-t border-border-700 bg-surface-800/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-3 shrink-0">
                <div class="text-[11px] text-text-500" id="jsonFooterInfo"></div>
                <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3 w-full sm:w-auto">
                    <button type="button" onclick="closeViewJsonModal()"
                            class="w-full sm:w-auto h-9 px-4 text-sm font-medium text-text-300 bg-surface-700 border border-border-600 rounded-lg hover:bg-surface-600 transition">
                        Close
                    </button>
                    <button type="button" onclick="copyJsonToClipboard()" id="jsonCopyBtn"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 h-9 px-4 text-sm font-medium text-white bg-munti-blue-600 hover:bg-munti-blue-500 rounded-lg transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <span>Copy JSON</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== ADD EXTERNAL API MODAL ==================== -->
<div id="addApiModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeAddCalibrationModal()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
        <div class="relative w-full h-full sm:h-auto sm:max-w-6xl bg-surface-900 border-0 sm:border border-border-700 rounded-none sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-full">

            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-700 bg-surface-800 flex items-center justify-between shrink-0">
                <h3 class="text-base sm:text-lg font-semibold text-text-100">Add External API</h3>
                <button type="button" onclick="closeAddCalibrationModal()" class="p-1.5 rounded-lg text-text-400 hover:text-text-100 hover:bg-surface-700 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-4 sm:p-6 overflow-y-auto thin-scrollbar flex-1">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">

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
                            <div class="flex-1 min-h-[150px] sm:min-h-[250px] p-3 sm:p-4 bg-surface-800 border border-border-600 rounded-lg overflow-y-auto thin-scrollbar text-sm text-text-300 leading-relaxed">
                                <label class="block text-xs font-medium text-text-400 mb-1.5">Documentation</label>
                                <div id="docContent" class="space-y-3">
                                    <p class="text-text-500 italic">Select an API source above to view its documentation and required parameters.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">API URL</label>
                        <input type="url" id="apiUrl" placeholder="https://api.example.com/v1/endpoint"
                               class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 placeholder-text-500 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">

                        <div class="mt-4 flex-1 p-3 sm:p-4 bg-surface-800/50 border border-border-700 rounded-lg text-xs text-text-500">
                            <p class="font-medium text-text-400 mb-2">Tips</p>
                            <ul class="space-y-1.5 list-disc list-inside">
                                <li>Include the full endpoint path</li>
                                <li>Use HTTPS whenever possible</li>
                                <li>Query parameters can be added later</li>
                            </ul>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">Bearer Token</label>
                        <input type="password" id="apiKey" placeholder="Enter your Bearer token"
                               class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 placeholder-text-500 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">

                        <div class="mt-4 flex-1 p-3 sm:p-4 bg-surface-800/50 border border-border-700 rounded-lg text-xs text-text-500">
                            <p class="font-medium text-text-400 mb-2">Security Note</p>
                            <p>Your token is stored encrypted and never exposed in the frontend after saving.</p>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-medium text-text-400">Fields to Map to Database</label>
                            <button type="button" id="addResetFieldsBtn" onclick="resetFieldList('add')"
                                    class="hidden text-[10px] text-munti-blue-400 hover:text-munti-blue-300 underline">
                                Reset
                            </button>
                        </div>
                        <div class="flex-1 p-3 bg-surface-800/50 border border-border-700 rounded-lg overflow-y-auto thin-scrollbar" style="max-height: 260px;">
                            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-y-1.5 gap-x-3" id="addFieldList">
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
                                    <input type="checkbox" name="add_params" value="{{ $value }}" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition shrink-0">
                                    <span>{{ $label }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        <p class="text-[10px] text-text-500 mt-1.5" id="addFieldListHint">Click <strong class="text-munti-yellow-400">Test API</strong> to load real fields from the response.</p>
                    </div>

                </div>

                <div id="addTestResult" class="hidden mt-4 p-3 rounded-lg border text-xs"></div>
            </div>

            <div class="px-4 sm:px-6 py-3 sm:py-4 border-t border-border-700 bg-surface-800/60 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-3 shrink-0">
                <button type="button" onclick="testAddApi()" id="addTestBtn"
                        class="inline-flex items-center justify-center gap-2 h-9 px-4 text-sm font-medium text-munti-yellow-400 bg-munti-yellow-700/20 border border-munti-yellow-600/30 rounded-lg hover:bg-munti-yellow-700/30 transition disabled:opacity-50 disabled:cursor-not-allowed w-full sm:w-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span>Test API</span>
                </button>
                <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3 w-full sm:w-auto">
                    <button type="button" onclick="closeAddCalibrationModal()"
                            class="w-full sm:w-auto h-9 px-4 text-sm font-medium text-text-300 bg-surface-700 border border-border-600 rounded-lg hover:bg-surface-600 transition">
                        Cancel
                    </button>
                    <button type="button" id="addSaveBtn" onclick="saveExternalApi()"
                            class="w-full sm:w-auto h-9 px-5 text-sm font-medium text-white bg-munti-green-600 hover:bg-munti-green-500 rounded-lg transition">
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

    <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
        <div class="relative w-full h-full sm:h-auto sm:max-w-6xl bg-surface-900 border-0 sm:border border-border-700 rounded-none sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-full">

            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-700 bg-surface-800 flex items-center justify-between shrink-0">
                <h3 class="text-base sm:text-lg font-semibold text-text-100">Edit External API</h3>
                <button type="button" onclick="closeEditCalibrationModal()" class="p-1.5 rounded-lg text-text-400 hover:text-text-100 hover:bg-surface-700 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-4 sm:p-6 overflow-y-auto thin-scrollbar flex-1">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">

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
                            <div class="flex-1 min-h-[150px] sm:min-h-[250px] p-3 sm:p-4 bg-surface-800 border border-border-600 rounded-lg overflow-y-auto thin-scrollbar text-sm text-text-300 leading-relaxed">
                                <label class="block text-xs font-medium text-text-400 mb-1.5">Documentation</label>
                                <div id="editDocContent" class="space-y-3"></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">API URL</label>
                        <input type="url" id="editApiUrl" placeholder="https://api.example.com/v1/endpoint"
                               class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 placeholder-text-500 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">

                        <div class="mt-4 flex-1 p-3 sm:p-4 bg-surface-800/50 border border-border-700 rounded-lg text-xs text-text-500">
                            <p class="font-medium text-text-400 mb-2">Tips</p>
                            <ul class="space-y-1.5 list-disc list-inside">
                                <li>Include the full endpoint path</li>
                                <li>Use HTTPS whenever possible</li>
                                <li>Query parameters can be added later</li>
                            </ul>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">Bearer Token</label>
                        <input type="password" id="editApiKey" placeholder="Leave blank to keep current token"
                               class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 placeholder-text-500 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">

                        <div class="mt-4 flex-1 p-3 sm:p-4 bg-surface-800/50 border border-border-700 rounded-lg text-xs text-text-500">
                            <p class="font-medium text-text-400 mb-2">Security Note</p>
                            <p>Your token is stored encrypted and never exposed in the frontend after saving.</p>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-medium text-text-400">Fields to Map to Database</label>
                            <div class="flex items-center gap-2">
                                <button type="button" id="editRefreshFieldsBtn" onclick="refreshEditFields()"
                                        class="hidden text-[10px] text-munti-green-400 hover:text-munti-green-300 underline">
                                    Refresh
                                </button>
                                <button type="button" id="editResetFieldsBtn" onclick="resetFieldList('edit')"
                                        class="hidden text-[10px] text-munti-blue-400 hover:text-munti-blue-300 underline">
                                    Reset
                                </button>
                            </div>
                        </div>
                        <div class="flex-1 p-3 bg-surface-800/50 border border-border-700 rounded-lg overflow-y-auto thin-scrollbar" style="max-height: 260px;">
                            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-y-1.5 gap-x-3" id="editFieldList">
                                @foreach($fields as $value => $label)
                                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                                    <input type="checkbox" name="edit_params" value="{{ $value }}" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition shrink-0">
                                    <span>{{ $label }}</span>
                                </label>
                                @endforeach
                            </div>
                            <!-- Inline loader overlay -->
                            <div id="editFieldsLoader" class="hidden items-center gap-2 pt-2 text-[11px] text-text-500">
                                <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                <span>Loading live fields…</span>
                            </div>
                        </div>
                        <p class="text-[10px] text-text-500 mt-1.5" id="editFieldListHint">Fields will be loaded from the live API endpoint automatically.</p>
                    </div>

                </div>

                <div id="editTestResult" class="hidden mt-4 p-3 rounded-lg border text-xs"></div>
            </div>

            <div class="px-4 sm:px-6 py-3 sm:py-4 border-t border-border-700 bg-surface-800/60 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-3 shrink-0">
                <button type="button" onclick="testEditApi()" id="editTestBtn"
                        class="inline-flex items-center justify-center gap-2 h-9 px-4 text-sm font-medium text-munti-yellow-400 bg-munti-yellow-700/20 border border-munti-yellow-600/30 rounded-lg hover:bg-munti-yellow-700/30 transition disabled:opacity-50 disabled:cursor-not-allowed w-full sm:w-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span>Test API</span>
                </button>
                <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3 w-full sm:w-auto">
                    <button type="button" onclick="closeEditCalibrationModal()"
                            class="w-full sm:w-auto h-9 px-4 text-sm font-medium text-text-300 bg-surface-700 border border-border-600 rounded-lg hover:bg-surface-600 transition">
                        Cancel
                    </button>
                    <button type="button" id="editSaveBtn" onclick="updateExternalApi()"
                            class="w-full sm:w-auto h-9 px-5 text-sm font-medium text-white bg-munti-blue-600 hover:bg-munti-blue-500 rounded-lg transition">
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
        accustation: { endpoint: 'https://api.accustation.com/v1/data', rate: '60 requests / minute', params: 'apikey, station_id', auth: 'Bearer Token' },
        openweather: { endpoint: 'https://api.openweathermap.org/data/2.5/weather', rate: '60 calls/minute (free tier)', params: 'appid, q, units', auth: 'Bearer Token' },
        iqair: { endpoint: 'https://api.iqair.com/v2/', rate: '10,000 calls/month (free)', params: 'api_key, city', auth: 'Bearer Token' },
        accuweather: {
            endpoint: 'http://dataservice.accuweather.com/',
            rate: '500 calls/day (Free tier) / higher for paid plans',
            params: 'locationKey, metric etc. (token sent as Bearer)',
            auth: 'Bearer Token',
            endpoints: 'Current Conditions, Hourly/Daily Forecasts, Alerts, Indices, and more'
        },
        custom: { endpoint: 'Your custom endpoint', rate: 'Depends on your service', params: 'Define your own', auth: 'Bearer Token' }
    };

    const DEFAULT_FIELDS = {
        add: [
            { value: 'pm25', label: 'PM2.5' }, { value: 'pm10', label: 'PM10' }, { value: 'tsp', label: 'TSP' },
            { value: 'ozone', label: 'Ozone' }, { value: 'carbon_monoxide', label: 'Carbon Monoxide' },
            { value: 'sulfur_dioxide', label: 'Sulfur Dioxide' }, { value: 'nitrogen_dioxide', label: 'Nitrogen Dioxide' },
            { value: 'temperature', label: 'Temperature' }, { value: 'humidity', label: 'Humidity' },
            { value: 'rain', label: 'Rain' }, { value: 'wind_speed', label: 'Wind Speed' },
            { value: 'wind_direction', label: 'Wind Direction' }, { value: 'air_pressure', label: 'Air Pressure' },
            { value: 'noise', label: 'Noise' }, { value: 'lead', label: 'Lead' }, { value: 'lead_temperature', label: 'Lead Temperature' },
        ],
    };
    DEFAULT_FIELDS.edit = DEFAULT_FIELDS.add;

    let addApiTested = false;
    let editApiTested = false;
    let editCurrentId = null;
    let editSavedChecklist = [];   // saved selection to keep checked
    let editAvailableFields = [];  // fields currently shown in the edit modal

    function updateDocContent(docDiv, source) {
        if (source && docMap[source]) {
            const info = docMap[source];
            let html = `
                <p><strong class="text-text-200">Base Endpoint:</strong></p>
                <code class="block text-xs bg-surface-900 px-2 py-1.5 rounded text-munti-blue-300 break-all">${info.endpoint}</code>
                <p class="mt-3"><strong class="text-text-200">Rate Limit:</strong> ${info.rate}</p>
                <p class="mt-2"><strong class="text-text-200">Required Parameters:</strong> ${info.params}</p>
                <p class="mt-2"><strong class="text-text-200">Authentication:</strong> ${info.auth}</p>
            `;
            if (info.endpoints) html += `<p class="mt-2"><strong class="text-text-200">Available Endpoints:</strong> ${info.endpoints}</p>`;
            docDiv.innerHTML = html;
            docDiv.classList.remove('hidden');
        } else {
            docDiv.innerHTML = `<p class="text-text-500 italic">Select an API source above to view its documentation and required parameters.</p>`;
            docDiv.classList.add('hidden');
        }
    }

    function getCsrfToken() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; }

    function sendRequest(method, url, data, successCallback, errorCallback) {
        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
            body: JSON.stringify(data),
        })
        .then(response => { if (!response.ok) return response.json().then(err => { throw err; }); return response.json(); })
        .then(data => { if (successCallback) successCallback(data); })
        .catch(error => {
            if (errorCallback) errorCallback(error);
            else {
                let msg = error.errors ? Object.values(error.errors).flat().join('\n') : error.message || 'Something went wrong.';
                Swal.fire('Error', msg, 'error');
            }
        });
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function escapeAttr(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ========== DYNAMIC FIELD LIST ==========
    // checkedValues: array of values that should be checked (may include values not in `fields`)
    function renderDynamicFields(prefix, fields, checkedValues) {
        const container = document.getElementById(prefix + 'FieldList');
        const hint = document.getElementById(prefix + 'FieldListHint');
        const resetBtn = document.getElementById(prefix + 'ResetFieldsBtn');
        if (!container) return;

        // If checkedValues not provided, keep the current checkbox state
        let checked;
        if (Array.isArray(checkedValues)) {
            checked = checkedValues.slice();
        } else {
            checked = Array.from(container.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value);
        }

        // Build a complete list: all fields (from API) + any extra checked values that aren't in the list
        const combined = [];
        const seen = new Set();
        (fields || []).forEach(f => { if (!seen.has(f)) { seen.add(f); combined.push(f); } });
        checked.forEach(f => { if (!seen.has(f)) { seen.add(f); combined.push(f); } });

        if (!combined.length) {
            hint.innerHTML = 'No fields detected from API response.';
            hint.className = 'text-[10px] text-munti-red-300 mt-1.5';
            return;
        }

        const inputName = prefix + '_params';
        container.innerHTML = combined.map(field => {
            const isChecked = checked.includes(field);
            return `
                <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                    <input type="checkbox" name="${inputName}" value="${escapeAttr(field)}" ${isChecked ? 'checked' : ''} class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition shrink-0">
                    <span class="font-mono text-[11px] break-all">${escapeHtml(field)}</span>
                </label>
            `;
        }).join('');

        const checkedCount = combined.filter(f => checked.includes(f)).length;
        hint.innerHTML = `<span class="text-munti-green-300">${combined.length} field(s)</span> available · <span class="text-munti-blue-300">${checkedCount} selected</span>.`;
        hint.className = 'text-[10px] text-text-500 mt-1.5';

        if (resetBtn) resetBtn.classList.remove('hidden');
    }

    function resetFieldList(prefix) {
        const container = document.getElementById(prefix + 'FieldList');
        const hint = document.getElementById(prefix + 'FieldListHint');
        const resetBtn = document.getElementById(prefix + 'ResetFieldsBtn');
        if (!container) return;

        const inputName = prefix + '_params';
        container.innerHTML = DEFAULT_FIELDS[prefix].map(f => `
            <label class="flex items-center gap-2 text-sm text-text-300 hover:text-text-200 cursor-pointer transition">
                <input type="checkbox" name="${inputName}" value="${escapeAttr(f.value)}" class="w-3.5 h-3.5 rounded border-border-600 bg-surface-700 text-munti-blue-500 focus:ring-2 focus:ring-munti-blue-500/50 focus:ring-offset-0 transition shrink-0">
                <span>${escapeHtml(f.label)}</span>
            </label>
        `).join('');

        hint.innerHTML = `Click <strong class="text-munti-yellow-400">Test API</strong> to load real fields from the response.`;
        hint.className = 'text-[10px] text-text-500 mt-1.5';

        if (resetBtn) resetBtn.classList.add('hidden');
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
                const previewStr = typeof result.preview === 'string' ? result.preview : JSON.stringify(result.preview, null, 2);
                const truncated = previewStr.length > 1500 ? previewStr.substring(0, 1500) + '\n... (truncated)' : previewStr;
                previewHtml = `<pre class="mt-2 p-2 bg-black/40 rounded text-[11px] whitespace-pre-wrap break-all max-h-48 overflow-y-auto thin-scrollbar">${escapeHtml(truncated)}</pre>`;
            }
            container.innerHTML = `
                <div class="flex flex-wrap items-center gap-2 font-medium">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <span>${result.message}</span>
                    <span class="ml-auto text-[10px] opacity-75">HTTP ${result.status || '—'} · ${result.duration_ms || 0} ms</span>
                </div>
                ${previewHtml}
            `;
        } else {
            container.classList.add('bg-munti-red-700/10', 'border-munti-red-600/30', 'text-munti-red-300');
            container.innerHTML = `
                <div class="flex flex-wrap items-center gap-2 font-medium">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
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
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
            <span>Testing...</span>
        `;

        sendRequest('POST', '/settings/calibration/test',
            { source, api_url: url, api_token: token, auth_type: authType },
            (data) => {
                renderTestResult(resultContainer, data);
                const prefix = isEdit ? 'edit' : 'add';
                if (isEdit) { editApiTested = data.success; } else { addApiTested = data.success; }

                if (data.success && Array.isArray(data.fields) && data.fields.length) {
                    // For add: preserve existing checked state
                    // For edit: use saved checklist as baseline if present, else preserve
                    const checked = (isEdit && editSavedChecklist.length)
                        ? editSavedChecklist
                        : Array.from(document.querySelectorAll(`input[name="${prefix}_params"]:checked`)).map(cb => cb.value);
                    renderDynamicFields(prefix, data.fields, checked);
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
                if (isEdit) { editApiTested = false; } else { addApiTested = false; }
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
        // If user provided a new token, test with it. Otherwise, fall back to stored token via fetch-response.
        const token = document.getElementById('editApiKey').value;
        if (!token || token.trim() === '') {
            // Use stored token
            refreshEditFields();
            return;
        }
        runApiTest({
            source: document.getElementById('editApiSource').value,
            url: document.getElementById('editApiUrl').value,
            token: token,
            authType: document.getElementById('editAuthType').value,
            resultContainer: 'editTestResult',
            buttonEl: document.getElementById('editTestBtn'),
            isEdit: true,
        });
    }

    // Refresh edit fields from the live API (uses stored token via /fetch-response)
    function refreshEditFields() {
        if (!editCurrentId) return;

        const loader = document.getElementById('editFieldsLoader');
        loader.classList.remove('hidden');
        loader.classList.add('flex');

        const container = document.getElementById('editTestResult');
        container.classList.add('hidden');
        container.innerHTML = '';

        fetch(`/settings/calibration/${editCurrentId}/fetch-response`, {
            headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
        })
        .then(res => { if (!res.ok) throw new Error('Failed to fetch API fields'); return res.json(); })
        .then(data => {
            loader.classList.add('hidden');
            loader.classList.remove('flex');

            // Show a mini status in the test result panel
            renderTestResult('editTestResult', {
                success: data.success,
                status: data.status,
                duration_ms: data.duration_ms,
                message: data.success ? 'Live fields loaded from API.' : (data.message || 'Could not load fields.'),
                error: data.error || null,
            });
            editApiTested = !!data.success;

            if (Array.isArray(data.fields) && data.fields.length) {
                editAvailableFields = data.fields;
                renderDynamicFields('edit', data.fields, editSavedChecklist);
            }
        })
        .catch(err => {
            loader.classList.add('hidden');
            loader.classList.remove('flex');
            renderTestResult('editTestResult', { success: false, message: err.message || 'Could not load fields.' });
        });
    }

    // ========== VIEW API RESPONSE (LIVE) ==========
    let currentJsonData = null;
    let currentJsonId   = null;
    let jsonViewMode    = 'pretty';
    let jsonShowLines   = true;

    function viewJson(id) {
        currentJsonId = id;
        showJsonLoading();
        document.getElementById('viewJsonModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        loadJsonResponse(id);
    }

    function viewJsonReload() {
        if (!currentJsonId) return;
        showJsonLoading();
        loadJsonResponse(currentJsonId);
    }

    function showJsonLoading() {
        const container = document.getElementById('jsonContent');
        document.getElementById('jsonStatusStrip').classList.add('hidden');
        document.getElementById('jsonFooterInfo').textContent = '';
        document.getElementById('jsonModalSubtitle').textContent = 'Fetching…';
        container.innerHTML = `
            <div class="p-4 space-y-2">
                <div class="json-skeleton" style="width: 40%"></div>
                <div class="json-skeleton" style="width: 70%"></div>
                <div class="json-skeleton" style="width: 55%"></div>
                <div class="json-skeleton" style="width: 80%"></div>
                <div class="json-skeleton" style="width: 30%"></div>
            </div>
        `;
    }

    function loadJsonResponse(id) {
        fetch(`/settings/calibration/${id}/fetch-response`, {
            headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
        })
        .then(res => { if (!res.ok) throw new Error('Failed to fetch API response'); return res.json(); })
        .then(data => { currentJsonData = data; renderJsonResponse(data); })
        .catch(err => {
            currentJsonData = { success: false, message: err.message || 'Could not load API response.' };
            renderJsonResponse(currentJsonData);
        });
    }

    function renderJsonResponse(response) { renderJsonStatusStrip(response); renderJsonBody(response); renderJsonFooter(response); }

    function renderJsonStatusStrip(response) {
        const strip = document.getElementById('jsonStatusStrip');
        const status = response.status;
        let badgeClass = 'status-err', badgeLabel = 'ERROR';
        if (status) {
            if (status >= 200 && status < 300) { badgeClass = 'status-2xx'; badgeLabel = 'OK'; }
            else if (status >= 300 && status < 400) { badgeClass = 'status-3xx'; badgeLabel = 'REDIRECT'; }
            else if (status >= 400 && status < 500) { badgeClass = 'status-4xx'; badgeLabel = 'CLIENT ERROR'; }
            else if (status >= 500) { badgeClass = 'status-5xx'; badgeLabel = 'SERVER ERROR'; }
        }
        const items = [];
        items.push(`<span class="status-badge ${badgeClass}"><span class="w-1.5 h-1.5 rounded-full bg-current"></span>${badgeLabel}${status ? ' · ' + status : ''}</span>`);
        if (response.duration_ms !== undefined) items.push(`<span class="text-text-400"><span class="text-text-500">Time:</span> <span class="text-text-200">${response.duration_ms} ms</span></span>`);
        if (response.source) items.push(`<span class="text-text-400"><span class="text-text-500">Source:</span> <span class="text-text-200">${escapeHtml(response.source)}</span></span>`);
        if (response.fetched_at) items.push(`<span class="text-text-400"><span class="text-text-500">Fetched:</span> <span class="text-text-200">${escapeHtml(response.fetched_at)}</span></span>`);
        strip.innerHTML = items.join('');
        strip.classList.remove('hidden');
        strip.classList.add('flex');
    }

    function renderJsonBody(response) {
        const container = document.getElementById('jsonContent');
        let payload;
        if (response.data !== undefined) payload = response.data;
        else if (response.error) payload = { error: response.error };
        else if (response.message) payload = { message: response.message };
        else payload = null;

        document.getElementById('jsonModalSubtitle').textContent = response.url ? response.url : 'Live response from saved endpoint';

        if (payload === null || payload === undefined) {
            container.innerHTML = `<div class="flex items-center justify-center py-10 text-text-500 text-sm italic">No data returned.</div>`;
            return;
        }

        let prettyJson;
        if (typeof payload === 'string') { try { prettyJson = JSON.stringify(JSON.parse(payload), null, 4); } catch { prettyJson = payload; } }
        else { prettyJson = JSON.stringify(payload, null, 4); }

        const rawJson = typeof payload === 'string' ? payload : JSON.stringify(payload);
        const viewer = document.createElement('div');
        viewer.className = 'json-viewer' + (jsonShowLines ? ' with-lines' : '');
        viewer.id = 'jsonViewerBody';

        if (jsonViewMode === 'pretty') {
            viewer.innerHTML = prettyJson.split('\n').map(line => `<span class="json-line">${syntaxHighlightLine(line)}</span>`).join('');
        } else {
            viewer.innerHTML = `<span class="json-line">${escapeHtml(rawJson)}</span>`;
        }

        container.innerHTML = '';
        container.appendChild(viewer);
    }

    function renderJsonFooter(response) {
        const footer = document.getElementById('jsonFooterInfo');
        const lines = (document.getElementById('jsonViewerBody')?.textContent || '').split('\n').length;
        const bytes = new Blob([JSON.stringify(currentJsonData)]).size;
        footer.innerHTML = `
            <span class="text-text-500">Lines:</span> <span class="text-text-300">${lines}</span>
            <span class="mx-2 text-border-700">·</span>
            <span class="text-text-500">Size:</span> <span class="text-text-300">${formatBytes(bytes)}</span>
            <span class="mx-2 text-border-700">·</span>
            <span class="text-text-500">Format:</span> <span class="text-text-300">${jsonViewMode === 'pretty' ? 'Pretty' : 'Raw'}</span>
        `;
    }

    function syntaxHighlightLine(line) {
        let escaped = escapeHtml(line);
        escaped = escaped.replace(/^(\s*)"([^"]+)"(\s*:)/, (m, sp, key, colon) => `${sp}<span class="json-key">"${key}"</span><span class="json-punct">${colon}</span>`);
        escaped = escaped.replace(/: "([^"]*)"/g, ': <span class="json-string">"$1"</span>');
        escaped = escaped.replace(/: (-?\d+\.?\d*(?:[eE][+-]?\d+)?)/g, ': <span class="json-number">$1</span>');
        escaped = escaped.replace(/: (true|false)/g, ': <span class="json-boolean">$1</span>');
        escaped = escaped.replace(/: (null)/g, ': <span class="json-null">$1</span>');
        escaped = escaped.replace(/([{}[\],])/g, '<span class="json-punct">$1</span>');
        return escaped;
    }

    function setJsonViewMode(mode) {
        jsonViewMode = mode;
        const pretty = document.getElementById('jsonViewPretty');
        const raw = document.getElementById('jsonViewRaw');
        const activeClass = ['text-munti-blue-300', 'bg-munti-blue-600/15', 'border-munti-blue-600/30'];
        const inactiveClass = ['text-text-400'];
        if (mode === 'pretty') {
            pretty.classList.add(...activeClass); pretty.classList.remove(...inactiveClass);
            raw.classList.remove(...activeClass); raw.classList.add(...inactiveClass);
        } else {
            raw.classList.add(...activeClass); raw.classList.remove(...inactiveClass);
            pretty.classList.remove(...activeClass); pretty.classList.add(...inactiveClass);
        }
        if (currentJsonData) { renderJsonBody(currentJsonData); renderJsonFooter(currentJsonData); }
    }

    function toggleLineNumbers() {
        jsonShowLines = !jsonShowLines;
        const viewer = document.getElementById('jsonViewerBody');
        if (viewer) viewer.classList.toggle('with-lines', jsonShowLines);
    }

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function closeViewJsonModal() {
        document.getElementById('viewJsonModal').classList.add('hidden');
        document.body.style.overflow = '';
        currentJsonData = null;
        currentJsonId = null;
        document.getElementById('jsonContent').innerHTML = '';
        document.getElementById('jsonStatusStrip').classList.add('hidden');
        document.getElementById('jsonFooterInfo').textContent = '';
    }

    function copyJsonToClipboard() {
        if (!currentJsonData) return;
        const jsonString = JSON.stringify(currentJsonData, null, 4);
        const btn = document.getElementById('jsonCopyBtn');
        const originalHtml = btn.innerHTML;
        navigator.clipboard.writeText(jsonString).then(() => {
            btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg><span>Copied!</span>`;
            btn.classList.remove('bg-munti-blue-600', 'hover:bg-munti-blue-500');
            btn.classList.add('bg-munti-green-600', 'hover:bg-munti-green-500');
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.classList.add('bg-munti-blue-600', 'hover:bg-munti-blue-500');
                btn.classList.remove('bg-munti-green-600', 'hover:bg-munti-green-500');
            }, 2000);
        }).catch(() => { Swal.fire('Error', 'Could not copy JSON. Please select and copy manually.', 'error'); });
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
        resetFieldList('add');
    }

    document.getElementById('apiSource')?.addEventListener('change', function () {
        addApiTested = false;
        updateDocContent(document.getElementById('docContent'), this.value);
        const urlField = document.getElementById('apiUrl');
        urlField.placeholder = this.value === 'accuweather'
            ? 'http://dataservice.accuweather.com/currentconditions/v1/{locationKey}'
            : 'https://api.example.com/v1/endpoint';
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
        const checklist = Array.from(document.querySelectorAll('input[name="add_params"]:checked')).map(cb => cb.value);

        if (!source || !url || !token) {
            Swal.fire('Validation Error', 'Please fill in all fields.', 'warning');
            return;
        }
        const payload = { source, api_url: url, api_token: token, auth_type: authType, checklist, total_data: 0, requests_per_min: 0, file_path: null };
        sendRequest('POST', '/settings/calibration', payload, (data) => {
            Swal.fire('Success', data.message, 'success');
            closeAddCalibrationModal();
            window.location.reload();
        });
    }

    // ========== EDIT MODAL ==========
    function editCalibration(id) {
        editApiTested = false;
        editCurrentId = id;
        editSavedChecklist = [];
        editAvailableFields = [];

        document.getElementById('editTestResult').classList.add('hidden');
        document.getElementById('editTestResult').innerHTML = '';

        const loader = document.getElementById('editFieldsLoader');
        loader.classList.add('hidden');
        loader.classList.remove('flex');

        // Reset field list to defaults while loading
        resetFieldList('edit');

        fetch(`/settings/calibration/${id}`, {
            headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
        })
        .then(res => { if (!res.ok) throw new Error('Failed to fetch record'); return res.json(); })
        .then(data => {
            document.getElementById('editApiSource').value = data.source;
            document.getElementById('editApiUrl').value = data.api_url;
            document.getElementById('editApiKey').value = '';

            editSavedChecklist = Array.isArray(data.checklist) ? data.checklist.slice() : [];

            updateDocContent(document.getElementById('editDocContent'), data.source);

            document.getElementById('editApiModal').dataset.id = id;
            document.getElementById('editApiModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            // Auto-load live fields so ALL fields are visible immediately
            refreshEditFields();
        })
        .catch(err => { Swal.fire('Error', 'Could not load record for editing.', 'error'); });
    }

    function closeEditCalibrationModal() {
        document.getElementById('editApiModal').classList.add('hidden');
        document.body.style.overflow = '';
        document.getElementById('editTestResult').classList.add('hidden');
        document.getElementById('editTestResult').innerHTML = '';
        editApiTested = false;
        editCurrentId = null;
        editSavedChecklist = [];
        editAvailableFields = [];
    }

    document.getElementById('editApiSource')?.addEventListener('change', function () {
        editApiTested = false;
        updateDocContent(document.getElementById('editDocContent'), this.value);
        const urlField = document.getElementById('editApiUrl');
        urlField.placeholder = this.value === 'accuweather'
            ? 'http://dataservice.accuweather.com/currentconditions/v1/{locationKey}'
            : 'https://api.example.com/v1/endpoint';
    });

    function updateExternalApi() {
        if (!editApiTested) {
            Swal.fire('Test Required', 'Please run a successful API test before updating.', 'warning');
            return;
        }
        const id = document.getElementById('editApiModal').dataset.id;
        if (!id) { Swal.fire('Error', 'No record ID found.', 'error'); return; }

        const source = document.getElementById('editApiSource').value;
        const url = document.getElementById('editApiUrl').value;
        const token = document.getElementById('editApiKey').value;
        const authType = document.getElementById('editAuthType').value;
        const checklist = Array.from(document.querySelectorAll('input[name="edit_params"]:checked')).map(cb => cb.value);

        if (!source || !url) { Swal.fire('Validation Error', 'Please fill in required fields.', 'warning'); return; }

        const payload = { source, api_url: url, auth_type: authType, checklist };
        if (token && token.trim() !== '') payload.api_token = token;

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
            html: `Are you sure you want to delete the calibration record for <strong>"${fileName}"</strong>?<br><span style="color: #ef4444;">This action cannot be undone!</span>`,
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
            if (typeof Swal !== 'undefined' && Swal.isVisible()) return;
            closeViewJsonModal();
            closeAddCalibrationModal();
            closeEditCalibrationModal();
        }
    });
</script>

@include('layouts.footer')