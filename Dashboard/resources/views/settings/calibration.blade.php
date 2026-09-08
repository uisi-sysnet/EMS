@include('layouts.header')
@include('layouts.topbar')

<style>
    .thin-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
    .thin-scrollbar::-webkit-scrollbar-track { background: #1A1A1A; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb { background: #4B5563; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb:hover { background: #6B7280; }
    .thin-scrollbar { scrollbar-width: thin; scrollbar-color: #4B5563 #1A1A1A; }

    /* Checklist tag styles */
    .checklist-tag {
        @apply inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border;
    }
    .checklist-tag-temperature {
        @apply bg-munti-red-700/20 text-munti-red-400 border-munti-red-600/30;
    }
    .checklist-tag-humidity {
        @apply bg-munti-blue-700/20 text-munti-blue-400 border-munti-blue-600/30;
    }
    .checklist-tag-pressure {
        @apply bg-munti-yellow-700/20 text-munti-yellow-400 border-munti-yellow-600/30;
    }
    .checklist-tag-pm25 {
        @apply bg-munti-purple-700/20 text-munti-purple-400 border-munti-purple-600/30;
    }
    .checklist-tag-pm10 {
        @apply bg-munti-orange-700/20 text-munti-orange-400 border-munti-orange-600/30;
    }
    .checklist-tag-co {
        @apply bg-munti-gray-700/20 text-munti-gray-400 border-munti-gray-600/30;
    }
    .checklist-tag-no2 {
        @apply bg-munti-teal-700/20 text-munti-teal-400 border-munti-teal-600/30;
    }
    .checklist-tag-o3 {
        @apply bg-munti-indigo-700/20 text-munti-indigo-400 border-munti-indigo-600/30;
    }
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

                            <!-- Add Calibration Button -->
                            <button type="button"
                                    onclick="openAddCalibrationModal()"
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
                                Add Calibration
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
                                <tr class="hover:bg-surface-700/50 transition" data-calibration-id="1">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">1</td>

                                    <!-- Source -->
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-radar-700/15 text-radar-400 border-radar-600/30">
                                            AccuStation
                                        </span>
                                    </td>

                                    <!-- File -->
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <a href="#"
                                           class="inline-flex items-center gap-1.5 text-xs text-munti-blue-400 hover:text-munti-blue-300 transition"
                                           title="View file (preview not available)">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                            </svg>
                                            accustation_data.json
                                        </a>
                                    </td>

                                    <!-- Checklist of Data -->
                                    <td class="px-4 py-2.5">
                                        <div class="flex flex-wrap gap-1 max-w-xs">
                                            <span class="checklist-tag checklist-tag-pm25">PM2.5</span>
                                            <span class="checklist-tag checklist-tag-pm10">PM10</span>
                                        </div>
                                    </td>

                                    <!-- Total Data -->
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-munti-green-700/15 text-munti-green-400 border border-munti-green-600/30">
                                            1,213
                                        </span>
                                    </td>

                                    <!-- No. of Requests/min -->
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-munti-green-700/15 text-munti-green-400 border border-munti-green-600/30">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            1 req/min
                                        </span>
                                    </td>

                                    <!-- Created At -->
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                        2026-03-12 09:45
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button"
                                                    onclick="editCalibration(1)"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-radar-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Edit Calibration">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button type="button"
                                                    onclick="deleteCalibration(1, 'accustation_data.json')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-munti-red-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Delete Calibration">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24" class="text-red-400">
                                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Row 2 (extra sample) -->
                                <tr class="hover:bg-surface-700/50 transition" data-calibration-id="2">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">2</td>

                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-munti-green-700/15 text-munti-green-400 border-munti-green-600/30">
                                            Sensor
                                        </span>
                                    </td>

                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <a href="#"
                                           class="inline-flex items-center gap-1.5 text-xs text-munti-blue-400 hover:text-munti-blue-300 transition"
                                           title="View file (preview not available)">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                            </svg>
                                            sensor_calibration.csv
                                        </a>
                                    </td>

                                    <td class="px-4 py-2.5">
                                        <div class="flex flex-wrap gap-1 max-w-xs">
                                            <span class="checklist-tag checklist-tag-temperature">Temperature</span>
                                            <span class="checklist-tag checklist-tag-humidity">Humidity</span>
                                            <span class="checklist-tag checklist-tag-pressure">Pressure</span>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-surface-700/50 text-text-300 border border-border-600/50">
                                            847
                                        </span>
                                    </td>

                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-munti-yellow-700/20 text-munti-yellow-400 border border-munti-yellow-600/30">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            12 req/min
                                        </span>
                                    </td>

                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                        2026-03-15 14:22
                                    </td>

                                    <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button"
                                                    onclick="editCalibration(2)"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-radar-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Edit Calibration">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button type="button"
                                                    onclick="deleteCalibration(2, 'sensor_calibration.csv')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-munti-red-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Delete Calibration">
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

<script>
    // Placeholder functions – wire these to your actual routes/controllers
    function openAddCalibrationModal() {
        alert('Add Calibration modal – implement as needed');
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
                // In a real app you would submit a form or call an API here
                alert(`Deleted calibration #${id}`);
            }
        });
    }
</script>

@include('layouts.footer')