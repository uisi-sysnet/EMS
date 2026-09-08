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
</style>

<div id="main-content" class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2.5">
                <span class="leading-tight uppercase tracking-wide">Calibration Management</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">View, edit and manage calibration records</span>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-6 px-5 sm:px-8">
            <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm">

                <!-- Table Section -->
                <div class="flex-1 flex flex-col min-h-0">
                    <div class="px-5 py-3 border-b border-border-700 bg-surface-900/40 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider flex items-center gap-2">
                            External API Records
                        </h3>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-500">2 Record(s)</span>

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
                                <!-- Row 1 -->
                                <tr class="hover:bg-surface-700/50 transition">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">1</td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 text-xs text-text-200 transition">
                                            AccuStation
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <a href="#" class="inline-flex items-center gap-1.5 text-xs text-text-200 transition">
                                            JSON
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex flex-wrap gap-1 max-w-xs text-text-200">
                                            <span class="checklist-tag checklist-tag-pm25">PM2.5</span>
                                            <span class="checklist-tag checklist-tag-pm10">PM10</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 text-xs text-text-200 transition">
                                            1,213
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 text-xs text-text-200 transition">
                                            1 req/min
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">2026-03-12 09:45</td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" onclick="editCalibration(1)" class="p-1.5 rounded-lg text-text-400 hover:text-radar-400 hover:bg-surface-700/70 transition" title="Edit">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button type="button" onclick="deleteCalibration(1, 'accustation_data.json')" class="p-1.5 rounded-lg text-text-400 hover:text-munti-red-400 hover:bg-surface-700/70 transition" title="Delete">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24" class="text-red-400">
                                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
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
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeAddCalibrationModal()"></div>

    <!-- Modal Panel -->
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-5xl bg-surface-900 border border-border-700 rounded-2xl shadow-2xl overflow-hidden">

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
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                    <!-- LEFT COLUMN: Dropdown + Documentation -->
                    <div class="flex flex-col gap-4">
                        <!-- Dropdown -->
                        <div>
                            <label class="block text-xs font-medium text-text-400 mb-1.5">Select API Source</label>
                            <select id="apiSource"
                                    class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">
                                <option value="">-- Choose API --</option>
                                <option value="accustation">AccuStation</option>
                                <option value="openweather">OpenWeather</option>
                                <option value="iqair">IQAir</option>
                                <option value="custom">Custom API</option>
                            </select>
                        </div>

                        <!-- Documentation Box -->
                        <div class="flex-1 flex flex-col">
                            <label class="block text-xs font-medium text-text-400 mb-1.5">Documentation</label>
                            <div class="flex-1 min-h-[280px] p-4 bg-surface-800 border border-border-600 rounded-lg overflow-y-auto thin-scrollbar text-sm text-text-300 leading-relaxed">
                                <p class="text-text-500 italic">Select an API source above to view its documentation and required parameters.</p>

                                <!-- Example content (shown dynamically via JS) -->
                                <div id="docContent" class="hidden space-y-3">
                                    <p><strong class="text-text-200">Base Endpoint:</strong></p>
                                    <code class="block text-xs bg-surface-900 px-2 py-1.5 rounded text-munti-blue-300">https://api.example.com/v1/data</code>

                                    <p class="mt-3"><strong class="text-text-200">Required Parameters:</strong></p>
                                    <ul class="list-disc list-inside space-y-1 text-xs">
                                        <li><code class="text-munti-yellow-400">api_key</code> – Your authentication key</li>
                                        <li><code class="text-munti-yellow-400">location</code> – Latitude,Longitude</li>
                                        <li><code class="text-munti-yellow-400">fields</code> – Comma-separated metrics</li>
                                    </ul>

                                    <p class="mt-3"><strong class="text-text-200">Rate Limit:</strong> 60 requests / minute</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MIDDLE COLUMN: API URL -->
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">API URL</label>
                        <input type="url"
                               id="apiUrl"
                               placeholder="https://api.example.com/v1/endpoint"
                               class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 placeholder-text-500 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">

                        <!-- Extra helper area under URL (optional) -->
                        <div class="mt-4 flex-1 p-4 bg-surface-800/50 border border-border-700 rounded-lg text-xs text-text-500">
                            <p class="font-medium text-text-400 mb-2">Tips</p>
                            <ul class="space-y-1.5 list-disc list-inside">
                                <li>Include the full endpoint path</li>
                                <li>Use HTTPS whenever possible</li>
                                <li>Query parameters can be added later</li>
                            </ul>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: API Key -->
                    <div class="flex flex-col">
                        <label class="block text-xs font-medium text-text-400 mb-1.5">API Key</label>
                        <input type="password"
                               id="apiKey"
                               placeholder="Enter your API key"
                               class="w-full h-10 px-3 text-sm bg-surface-800 border border-border-600 rounded-lg text-text-100 placeholder-text-500 focus:outline-none focus:ring-2 focus:ring-munti-blue-500/50 focus:border-munti-blue-500 transition">

                        <!-- Extra helper area under Key -->
                        <div class="mt-4 flex-1 p-4 bg-surface-800/50 border border-border-700 rounded-lg text-xs text-text-500">
                            <p class="font-medium text-text-400 mb-2">Security Note</p>
                            <p>Your API key is stored encrypted and never exposed in the frontend after saving.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-border-700 bg-surface-800/60 flex items-center justify-end gap-3">
                <button type="button"
                        onclick="closeAddCalibrationModal()"
                        class="h-9 px-4 text-sm font-medium text-text-300 bg-surface-700 border border-border-600 rounded-lg hover:bg-surface-600 transition">
                    Cancel
                </button>
                <button type="button"
                        onclick="saveExternalApi()"
                        class="h-9 px-5 text-sm font-medium text-white bg-munti-green-600 hover:bg-munti-green-500 rounded-lg transition">
                    Save API
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function openAddCalibrationModal() {
        document.getElementById('addApiModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeAddCalibrationModal() {
        document.getElementById('addApiModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Simple demo: show documentation when source changes
    document.getElementById('apiSource')?.addEventListener('change', function () {
        const doc = document.getElementById('docContent');
        if (this.value) {
            doc.classList.remove('hidden');
        } else {
            doc.classList.add('hidden');
        }
    });

    function saveExternalApi() {
        const source = document.getElementById('apiSource').value;
        const url = document.getElementById('apiUrl').value;
        const key = document.getElementById('apiKey').value;

        if (!source || !url || !key) {
            alert('Please fill in all fields.');
            return;
        }

        // TODO: replace with real AJAX / form submit
        alert(`Saved!\nSource: ${source}\nURL: ${url}`);
        closeAddCalibrationModal();
    }

    function editCalibration(id) {
        alert(`Edit Calibration #${id} – implement as needed`);
    }

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
                alert(`Deleted calibration #${id}`);
            }
        });
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAddCalibrationModal();
    });
</script>

@include('layouts.footer')