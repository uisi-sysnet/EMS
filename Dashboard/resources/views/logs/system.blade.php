@include('layouts.header')
@include('layouts.topbar')

<style>
    .thin-scrollbar::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    .thin-scrollbar::-webkit-scrollbar-track {
        background: #1A1A1A;
        border-radius: 10px;
    }
    .thin-scrollbar::-webkit-scrollbar-thumb {
        background: #4B5563;
        border-radius: 10px;
    }
    .thin-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #6B7280;
    }
    .thin-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: #4B5563 #1A1A1A;
    }

    /* Subtle blue highlight for unseen logs only */
    tr.log-row-unseen {
        background-color: rgba(59, 130, 246, 0.08) !important;
        border-left: 3px solid rgba(59, 130, 246, 0.45);
    }
    
    tr.log-row-unseen:hover {
        background-color: rgba(59, 130, 246, 0.14) !important;
    }

    /* Message expand / collapse */
    .log-message {
        max-width: 28rem;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }
    .log-message:hover {
        background-color: rgba(255, 255, 255, 0.03);
    }
    .log-message.collapsed {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .log-message.expanded {
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    /* Unseen badge pulse */
    .unseen-badge {
        animation: pulse-badge 2s infinite;
    }
    
    @keyframes pulse-badge {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    /* Seen column */
    .seen-cell {
        min-width: 70px;
        text-align: center;
        font-size: 0.75rem;
        padding: 0.5rem 1rem;
        white-space: nowrap;
    }

    .seen-cell .unseen-text {
        color: #60a5fa;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
    }

    .seen-cell .unseen-text .dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: #60a5fa;
        animation: pulse-dot 1.5s ease-in-out infinite;
    }

    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.3; transform: scale(0.8); }
    }

    .seen-cell .seen-text {
        color: #9ca3af;
    }
</style>

@php
    $fieldCls  = 'w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition';
    $labelCls  = 'block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider';
    $thCls     = 'px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap';
    $categoryBadge = [
        'system'   => 'bg-surface-700 text-text-300 border-border-600',
        'device'   => 'bg-radar-600/20 text-radar-300 border-radar-500/30',
        'security' => 'bg-munti-orange-500/15 text-munti-orange-400 border-munti-orange-500/30',
    ];
    $auditBadge = [
        'authentication' => 'bg-surface-700 text-text-300 border-border-600',
        'security'       => 'bg-munti-orange-500/15 text-munti-orange-400 border-munti-orange-500/30',
        'users'          => 'bg-blue-600/15 text-blue-300 border-blue-500/30',
        'stations'       => 'bg-radar-600/20 text-radar-300 border-radar-500/30',
        'cameras'        => 'bg-radar-600/20 text-radar-300 border-radar-500/30',
        'settings'       => 'bg-munti-yellow-600/15 text-munti-yellow-400 border-munti-yellow-500/30',
        'network'        => 'bg-munti-yellow-600/15 text-munti-yellow-400 border-munti-yellow-500/30',
        'services'       => 'bg-munti-yellow-600/15 text-munti-yellow-400 border-munti-yellow-500/30',
        'maintenance'    => 'bg-surface-700 text-text-300 border-border-600',
        'other'          => 'bg-surface-700 text-text-400 border-border-600',
    ];
@endphp

<div id="main-content"
     class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">

    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-5 sm:px-8 pt-4 sm:pt-5 border-b border-border-800 bg-surface-800 shrink-0">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg sm:text-xl font-semibold text-text-100 leading-tight uppercase">Logs</h2>
                    @if(isset($unseenCount) && $unseenCount > 0)
                        <span class="unseen-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-600/20 text-blue-400 border border-blue-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-400 mr-1.5"></span>
                            {{ $unseenCount }} new
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    @if($tab === 'logs')
                        <span class="text-xs sm:text-sm text-text-400">Service messages and device events</span>
                        <button type="button" id="markAllSeenBtn"
                                class="text-xs px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition">
                            Mark All as Archived
                        </button>
                    @else
                        <span class="text-xs sm:text-sm text-text-400">Who changed what in the dashboard</span>
                        @if(($failedToday ?? 0) > 0)
                            <a href="{{ route('logs.index', ['tab' => 'audit', 'result' => 'failed', 'from' => now()->toDateString()]) }}"
                               class="text-xs px-2.5 py-1 rounded-full border border-munti-red-600/30 bg-munti-red-700/20 text-munti-red-400 hover:bg-munti-red-700/30 transition">
                                {{ $failedToday }} failed today
                            </a>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Tabs -->
            <nav class="flex gap-1 mt-3 -mb-px" aria-label="Log type">
                @foreach(['logs' => 'Logs', 'audit' => 'Audit Log'] as $key => $label)
                    <a href="{{ route('logs.index', $key === 'audit' ? ['tab' => 'audit'] : []) }}"
                       @if($tab === $key) aria-current="page" @endif
                       class="px-4 py-2 text-sm font-medium border-b-2 transition
                              {{ $tab === $key ? 'border-radar-400 text-text-100' : 'border-transparent text-text-400 hover:text-text-200 hover:border-border-600' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>

        @if($tab === 'logs')
            <!-- ===================== LOGS TAB ===================== -->
            <div class="shrink-0 px-4 sm:px-6 pt-4 pb-3 bg-background-900 border-b border-border-800 flex flex-col gap-3">
                @if($hasCategory)
                    <!-- Category quick filter -->
                    <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Category">
                        @php $activeCat = request('category'); @endphp
                        <a href="{{ route('logs.index', request()->except(['category', 'page'])) }}"
                           class="px-3 py-1 rounded-full text-xs font-medium border transition {{ !$activeCat ? 'bg-radar-600/30 text-text-100 border-radar-500/50' : 'bg-surface-800 text-text-400 border-border-600 hover:text-text-200' }}">
                            All <span class="text-text-500 tabular-nums">{{ number_format(array_sum($categoryCounts)) }}</span>
                        </a>
                        @foreach($logCategories as $key => $label)
                            <a href="{{ route('logs.index', array_merge(request()->except(['category', 'page']), ['category' => $key])) }}"
                               class="px-3 py-1 rounded-full text-xs font-medium border transition {{ $activeCat === $key ? 'bg-radar-600/30 text-text-100 border-radar-500/50' : 'bg-surface-800 text-text-400 border-border-600 hover:text-text-200' }}">
                                {{ $label }} <span class="text-text-500 tabular-nums">{{ number_format($categoryCounts[$key] ?? 0) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <form method="GET" action="{{ route('logs.index') }}" class="bg-surface-800 rounded-xl border border-border-700 p-3 sm:p-4 w-full">
                    @if(request('category'))
                        <input type="hidden" name="category" value="{{ request('category') }}">
                    @endif
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-8 gap-2 items-end w-full">
                        <div>
                            <label for="f_search" class="{{ $labelCls }}">Message</label>
                            <input id="f_search" type="text" name="search" placeholder="Contains…" value="{{ request('search') }}" class="{{ $fieldCls }}">
                        </div>
                        <div>
                            <label for="f_service" class="{{ $labelCls }}">Service</label>
                            <input id="f_service" type="text" name="service" placeholder="Service" value="{{ request('service') }}" class="{{ $fieldCls }}">
                        </div>
                        <div>
                            <label for="f_level" class="{{ $labelCls }}">Level</label>
                            <select id="f_level" name="level" class="{{ $fieldCls }}">
                                <option value="">All</option>
                                <option value="ERROR" @selected(request('level')=='ERROR')>Error</option>
                                <option value="WARNING" @selected(request('level')=='WARNING')>Warning</option>
                                <option value="INFO" @selected(request('level')=='INFO')>Info</option>
                            </select>
                        </div>
                        <div>
                            <label for="f_logger" class="{{ $labelCls }}">Logger</label>
                            <input id="f_logger" type="text" name="logger" placeholder="Logger name" value="{{ request('logger') }}" class="{{ $fieldCls }}">
                        </div>
                        <div>
                            <label for="f_thread" class="{{ $labelCls }}">Thread</label>
                            <input id="f_thread" type="text" name="thread" placeholder="Thread" value="{{ request('thread') }}" class="{{ $fieldCls }}">
                        </div>
                        <div>
                            <label for="f_from" class="{{ $labelCls }}">From</label>
                            <input id="f_from" type="date" name="from" value="{{ request('from', $defaultFrom ?? '') }}" class="{{ $fieldCls }}">
                        </div>
                        <div>
                            <label for="f_to" class="{{ $labelCls }}">To</label>
                            <input id="f_to" type="date" name="to" value="{{ request('to', $defaultTo ?? '') }}" class="{{ $fieldCls }}">
                        </div>
                        <div class="flex gap-1.5">
                            <button type="submit" class="flex-1 px-3 py-1.5 bg-radar-600 hover:bg-radar-500 text-text-100 text-xs font-semibold rounded-lg transition border border-radar-500/40 whitespace-nowrap">Filter</button>
                            <a href="{{ route('logs.index') }}" class="flex-1 px-3 py-1.5 bg-surface-700 hover:bg-surface-600 text-text-400 text-xs font-medium rounded-lg transition border border-border-600 whitespace-nowrap text-center">Reset</a>
                            <a href="{{ route('logs.export', request()->query()) }}" title="Export CSV" aria-label="Export CSV"
                               class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-500 text-text-100 text-xs font-semibold rounded-lg transition border border-green-500/40 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 px-4 sm:px-6 py-3">
                <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden">
                    <div class="overflow-x-auto thin-scrollbar">
                        <table class="min-w-full divide-y divide-border-700 text-sm">
                            <thead class="bg-surface-900/80 sticky top-0 z-10">
                                <tr>
                                    <th class="{{ $thCls }}">Date</th>
                                    @if($hasCategory)<th class="{{ $thCls }}">Category</th>@endif
                                    <th class="{{ $thCls }}">Service</th>
                                    <th class="{{ $thCls }}">Level</th>
                                    <th class="{{ $thCls }}">Logger</th>
                                    <th class="{{ $thCls }}">Thread</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider">Message</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-800">
                                @forelse($logs as $log)
                                    @php
                                        $isUnseen = is_null($log->seen_at);
                                        $badge = match($log->level) {
                                            'ERROR', 'CRITICAL' => 'bg-munti-red-700/20 text-munti-red-400 border-munti-red-600/30',
                                            'WARNING' => 'bg-munti-yellow-600/20 text-munti-yellow-400 border-munti-yellow-500/30',
                                            'INFO' => 'bg-radar-600/20 text-radar-400 border-radar-500/30',
                                            default => 'bg-surface-700 text-text-400 border-border-600',
                                        };
                                    @endphp
                                    <tr class="{{ $isUnseen ? 'log-row-unseen' : '' }} hover:bg-surface-700/60 transition"
                                        data-log-id="{{ $log->id }}"
                                        data-is-unseen="{{ $isUnseen ? 'true' : 'false' }}">
                                        <td class="px-4 py-2 whitespace-nowrap text-text-400 font-mono text-xs">{{ $log->created_at->format('M j, Y H:i:s') }}</td>
                                        @if($hasCategory)
                                            <td class="px-4 py-2 whitespace-nowrap">
                                                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $categoryBadge[$log->category] ?? $categoryBadge['system'] }}">
                                                    {{ $logCategories[$log->category] ?? ucfirst($log->category) }}
                                                </span>
                                            </td>
                                        @endif
                                        <td class="px-4 py-2 whitespace-nowrap text-text-400">{{ $log->service }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $badge }}">{{ $log->level }}</span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-text-400 text-xs">{{ $log->logger_name }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-text-400 text-xs font-mono">{{ $log->thread_name }}</td>
                                        <td class="px-4 py-2 text-text-400">
                                            <div class="log-message collapsed rounded px-1 -mx-1"
                                                 title="Click to expand / collapse"
                                                 onclick="this.classList.toggle('collapsed'); this.classList.toggle('expanded');">
                                                {{ $log->message }}
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-4 py-10 text-center text-text-500">No logs found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($logs->hasPages())
                <div class="shrink-0 px-4 sm:px-6 py-3 border-t border-border-800 bg-surface-800 flex justify-center">
                    {{ $logs->appends(request()->query())->links('vendor.pagination.dark') }}
                </div>
            @endif

        @else
            <!-- ===================== AUDIT LOG TAB ===================== -->
            <div class="shrink-0 px-4 sm:px-6 pt-4 pb-3 bg-background-900 border-b border-border-800">
                <form method="GET" action="{{ route('logs.index') }}" class="bg-surface-800 rounded-xl border border-border-700 p-3 sm:p-4 w-full">
                    <input type="hidden" name="tab" value="audit">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-2 items-end w-full">
                        <div>
                            <label for="a_search" class="{{ $labelCls }}">Action or IP</label>
                            <input id="a_search" type="text" name="search" placeholder="Contains…" value="{{ request('search') }}" class="{{ $fieldCls }}">
                        </div>
                        <div>
                            <label for="a_user" class="{{ $labelCls }}">User</label>
                            <input id="a_user" type="text" name="user" placeholder="Username" value="{{ request('user') }}" class="{{ $fieldCls }}">
                        </div>
                        <div>
                            <label for="a_category" class="{{ $labelCls }}">Category</label>
                            <select id="a_category" name="category" class="{{ $fieldCls }}">
                                <option value="">All</option>
                                @foreach($auditCategories as $key => $label)
                                    <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="a_result" class="{{ $labelCls }}">Result</label>
                            <select id="a_result" name="result" class="{{ $fieldCls }}">
                                <option value="">All</option>
                                <option value="success" @selected(request('result') === 'success')>Success</option>
                                <option value="failed" @selected(request('result') === 'failed')>Failed</option>
                            </select>
                        </div>
                        <div>
                            <label for="a_from" class="{{ $labelCls }}">From</label>
                            <input id="a_from" type="date" name="from" value="{{ request('from') }}" class="{{ $fieldCls }}">
                        </div>
                        <div>
                            <label for="a_to" class="{{ $labelCls }}">To</label>
                            <input id="a_to" type="date" name="to" value="{{ request('to') }}" class="{{ $fieldCls }}">
                        </div>
                        <div class="flex gap-1.5">
                            <button type="submit" class="flex-1 px-3 py-1.5 bg-radar-600 hover:bg-radar-500 text-text-100 text-xs font-semibold rounded-lg transition border border-radar-500/40 whitespace-nowrap">Filter</button>
                            <a href="{{ route('logs.index', ['tab' => 'audit']) }}" class="flex-1 px-3 py-1.5 bg-surface-700 hover:bg-surface-600 text-text-400 text-xs font-medium rounded-lg transition border border-border-600 whitespace-nowrap text-center">Reset</a>
                            <a href="{{ route('logs.export', request()->query()) }}" title="Export CSV" aria-label="Export CSV"
                               class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-500 text-text-100 text-xs font-semibold rounded-lg transition border border-green-500/40 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 px-4 sm:px-6 py-3">
                <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden">
                    <div class="overflow-x-auto thin-scrollbar">
                        <table class="min-w-full divide-y divide-border-700 text-sm">
                            <thead class="bg-surface-900/80 sticky top-0 z-10">
                                <tr>
                                    <th class="{{ $thCls }}">Date</th>
                                    <th class="{{ $thCls }}">User</th>
                                    <th class="{{ $thCls }}">Category</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider">Action</th>
                                    <th class="{{ $thCls }}">Result</th>
                                    <th class="{{ $thCls }}">IP Address</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-800">
                                @forelse($auditLogs as $entry)
                                    @php
                                        $p = $entry->properties ?? [];
                                        $failed = $entry->result === 'failed';
                                        $details = array_filter([
                                            !empty($p['values']) ? collect($p['values'])->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · ') : null,
                                            !empty($p['fields']) ? 'Fields: ' . implode(', ', $p['fields']) : null,
                                            !empty($p['message']) ? 'Error: ' . $p['message'] : null,
                                        ]);
                                    @endphp
                                    <tr class="hover:bg-surface-700/60 transition {{ $failed ? 'bg-munti-red-700/5' : '' }}">
                                        <td class="px-4 py-2 whitespace-nowrap text-text-400 font-mono text-xs">{{ $entry->created_at->timezone('Asia/Manila')->format('M j, Y H:i:s') }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <div class="text-text-200 text-sm">{{ $entry->username ?? '—' }}</div>
                                            @if($entry->role)<div class="text-[11px] text-text-500">{{ $entry->role }}</div>@endif
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $auditBadge[$entry->log_name] ?? $auditBadge['other'] }}">
                                                {{ $auditCategories[$entry->log_name] ?? ucfirst($entry->log_name) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-text-300">
                                            <div>
                                                @if($failed && $entry->log_name !== 'security')
                                                    <span class="text-munti-red-400">Not completed:</span>
                                                @endif
                                                {{ $entry->description }}
                                            </div>
                                            @if($details)
                                                <div class="log-message collapsed rounded px-1 -mx-1 mt-0.5 text-[11px] text-text-500"
                                                     title="Click to expand / collapse"
                                                     onclick="this.classList.toggle('collapsed'); this.classList.toggle('expanded');">
                                                    {{ implode(' — ', $details) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium border
                                                {{ $failed ? 'bg-munti-red-700/20 text-munti-red-400 border-munti-red-600/30' : 'bg-munti-green-700/20 text-munti-green-400 border-munti-green-600/30' }}">
                                                {{ $failed ? 'Failed' : 'Success' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-text-400 text-xs font-mono">{{ $entry->ip_address }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-10 text-center text-text-500">No audit entries found. Changes made in the dashboard appear here.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($auditLogs->hasPages())
                <div class="shrink-0 px-4 sm:px-6 py-3 border-t border-border-800 bg-surface-800 flex justify-center">
                    {{ $auditLogs->appends(request()->query())->links('vendor.pagination.dark') }}
                </div>
            @endif
        @endif
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('markAllSeenBtn')?.addEventListener('click', async function() {
            const confirmResult = await Swal.fire({
                title: 'Mark All as Seen?',
                text: 'This will mark all unseen logs as seen.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, mark all',
                background: '#1f2937',
                color: '#f3f4f6'
            });
            
            if (!confirmResult.isConfirmed) return;
            
            try {
                const response = await fetch('{{ route("logs.mark-as-seen") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ ids: [] })
                });
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text);
                    throw new Error('Server returned non-JSON response');
                }
                
                const data = await response.json();
                
                if (data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        background: '#1f2937',
                        color: '#f3f4f6',
                        confirmButtonColor: '#3b82f6',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    location.reload();
                } else {
                    throw new Error(data.message || 'Failed to mark logs as seen');
                }
            } catch (err) {
                console.error('Error:', err);
                await Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: err.message || 'Failed to mark logs as seen.',
                    background: '#1f2937',
                    color: '#f3f4f6',
                    confirmButtonColor: '#3b82f6'
                });
            }
        });
    });
</script>

@include('layouts.footer')