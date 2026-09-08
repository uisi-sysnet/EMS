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
            <span class="text-xs sm:text-sm text-text-400">View calibration records and data sources</span>
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
                            External API Records
                        </h3>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-500">{{ $calibrations->count() }} Record(s)</span>
                        </div>
                    </div>

                    <div class="overflow-x-auto thin-scrollbar flex-1">
                        @if($calibrations->count())
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
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border-800">
                                    @foreach($calibrations as $calibration)
                                        <tr class="hover:bg-surface-700/50 transition" data-calibration-id="{{ $calibration->id }}">
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">{{ $loop->iteration }}</td>
                                            
                                            {{-- Source --}}
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border
                                                    {{ $calibration->source === 'sensor' 
                                                        ? 'bg-munti-green-700/15 text-munti-green-400 border-munti-green-600/30' 
                                                        : ($calibration->source === 'manual' 
                                                            ? 'bg-munti-yellow-700/15 text-munti-yellow-400 border-munti-yellow-600/30'
                                                            : 'bg-radar-700/15 text-radar-400 border-radar-600/30') }}">
                                                    {{ ucfirst($calibration->source) }}
                                                </span>
                                            </td>

                                            {{-- File --}}
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                <a href="#" 
                                                   class="inline-flex items-center gap-1.5 text-xs text-munti-blue-400 hover:text-munti-blue-300 transition"
                                                   title="View file (preview not available)">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                    </svg>
                                                    {{ basename($calibration->file_path) }}
                                                </a>
                                            </td>

                                            {{-- Checklist of Data --}}
                                            <td class="px-4 py-2.5">
                                                <div class="flex flex-wrap gap-1 max-w-xs">
                                                    @foreach($calibration->checklist ?? [] as $item)
                                                        @php
                                                            $tagClass = match(strtolower($item)) {
                                                                'temperature' => 'checklist-tag-temperature',
                                                                'humidity' => 'checklist-tag-humidity',
                                                                'pressure' => 'checklist-tag-pressure',
                                                                'pm2.5' => 'checklist-tag-pm25',
                                                                'pm10' => 'checklist-tag-pm10',
                                                                'co' => 'checklist-tag-co',
                                                                'no2' => 'checklist-tag-no2',
                                                                'o3' => 'checklist-tag-o3',
                                                                default => 'checklist-tag-temperature',
                                                            };
                                                        @endphp
                                                        <span class="checklist-tag {{ $tagClass }}">
                                                            {{ $item }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </td>

                                            {{-- Total Data --}}
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium 
                                                    {{ ($calibration->total_data ?? 0) > 1000 
                                                        ? 'bg-munti-green-700/15 text-munti-green-400 border border-munti-green-600/30' 
                                                        : 'bg-surface-700/50 text-text-300 border border-border-600/50' }}">
                                                    {{ number_format($calibration->total_data ?? 0) }}
                                                </span>
                                            </td>

                                            {{-- No. of Requests/min --}}
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium 
                                                    {{ ($calibration->requests_per_min ?? 0) > 50 
                                                        ? 'bg-munti-yellow-700/20 text-munti-yellow-400 border border-munti-yellow-600/30' 
                                                        : 'bg-munti-green-700/15 text-munti-green-400 border border-munti-green-600/30' }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    {{ $calibration->requests_per_min ?? 0 }} req/min
                                                </span>
                                            </td>

                                            {{-- Created At --}}
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                                {{ $calibration->created_at ? $calibration->created_at->format('Y-m-d H:i') : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="flex items-center justify-center h-32 text-sm text-text-500">
                                No calibration records found.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('layouts.footer')