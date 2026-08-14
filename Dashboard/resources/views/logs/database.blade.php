{{-- resources/views/logs/database.blade.php --}}
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

    /* Event badges */
    .event-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .event-created {
        background: rgba(34, 197, 94, 0.2);
        color: #4ade80;
        border: 1px solid rgba(34, 197, 94, 0.3);
    }
    .event-updated {
        background: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }
    .event-deleted {
        background: rgba(239, 68, 68, 0.2);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }
    .event-restored {
        background: rgba(168, 85, 247, 0.2);
        color: #a78bfa;
        border: 1px solid rgba(168, 85, 247, 0.3);
    }

    /* Changes expansion */
    .changes-container {
        max-width: 28rem;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }
    .changes-container:hover {
        background-color: rgba(255, 255, 255, 0.03);
    }
    .changes-container.collapsed {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .changes-container.expanded {
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .changes-container .change-item {
        display: inline-block;
        margin: 0.125rem 0.25rem;
        padding: 0.125rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-family: monospace;
    }

    .change-item .old-value {
        color: #f87171;
        text-decoration: line-through;
    }
    .change-item .new-value {
        color: #4ade80;
    }
    .change-item .arrow {
        color: #9ca3af;
        margin: 0 0.25rem;
    }

    /* Properties display */
    .property-item {
        display: inline-block;
        margin: 0.125rem 0.25rem;
        padding: 0.125rem 0.5rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 4px;
        font-size: 0.7rem;
        font-family: monospace;
        color: #d1d5db;
    }
    .property-item .key {
        color: #60a5fa;
    }
    .property-item .value {
        color: #fcd34d;
    }

    /* Highlight row for important events */
    tr.row-deleted {
        background-color: rgba(239, 68, 68, 0.05) !important;
        border-left: 3px solid rgba(239, 68, 68, 0.3);
    }
    tr.row-deleted:hover {
        background-color: rgba(239, 68, 68, 0.1) !important;
    }
</style>

<div id="main-content"
     class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">

    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-5 sm:px-8 py-4 sm:py-5 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 shrink-0">
            <div class="flex items-center gap-3">
                <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                    <span class="leading-tight uppercase">Database Activity Logs</span>
                </h2>
                @if(isset($totalCount) && $totalCount > 0)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-600/20 text-blue-400 border border-blue-500/30">
                        {{ $totalCount }} entries
                    </span>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs sm:text-sm text-text-400">Track all database changes</span>
                <a href="{{ route('logs.database.export', request()->query()) }}" 
                   class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-500 text-text-100 text-xs font-semibold rounded-lg transition border border-green-500/40">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="shrink-0 px-4 sm:px-6 pt-4 sm:pt-5 pb-3 bg-background-900 border-b border-border-800">
            <form method="GET" action="{{ route('logs.database.index') }}" class="bg-surface-800 rounded-xl border border-border-700 p-3 sm:p-4 w-full">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-2 items-end w-full">
                    <!-- Event -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Event</label>
                        <select name="event" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                            <option value="">All Events</option>
                            <option value="created" @selected(request('event')=='created')>Created</option>
                            <option value="updated" @selected(request('event')=='updated')>Updated</option>
                            <option value="deleted" @selected(request('event')=='deleted')>Deleted</option>
                            <option value="restored" @selected(request('event')=='restored')>Restored</option>
                        </select>
                    </div>
                    <!-- Subject Type -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Model</label>
                        <input type="text" name="subject_type" placeholder="Model name" value="{{ request('subject_type') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- Causer -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">User</label>
                        <input type="text" name="causer" placeholder="Username or ID" value="{{ request('causer') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- From -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">From</label>
                        <input type="datetime-local" name="from" value="{{ request('from') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- To -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">To</label>
                        <input type="datetime-local" name="to" value="{{ request('to') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- Buttons -->
                    <div class="flex gap-1.5">
                        <button type="submit" class="flex-1 px-3 py-1.5 bg-radar-600 hover:bg-radar-500 text-text-100 text-xs font-semibold rounded-lg transition border border-radar-500/40 whitespace-nowrap">Filter</button>
                        <a href="{{ route('logs.database.index') }}" class="flex-1 px-3 py-1.5 bg-surface-700 hover:bg-surface-600 text-text-400 text-xs font-medium rounded-lg transition border border-border-600 whitespace-nowrap text-center">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 px-4 sm:px-6 py-3">
            <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden">
                <div class="overflow-x-auto thin-scrollbar">
                    <table class="min-w-full divide-y divide-border-700 text-sm">
                        <thead class="bg-surface-900/80 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Event</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Subject</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">User</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider">Changes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-800">
                            @forelse($logs as $log)
                                @php
                                    $rowClass = match($log->event) {
                                        'deleted' => 'row-deleted',
                                        default => ''
                                    };
                                @endphp
                                <tr class="{{ $rowClass }} hover:bg-surface-700/60 transition">
                                    <td class="px-4 py-2 whitespace-nowrap text-text-400 font-mono text-xs">
                                        {{ $log->created_at->format('M j, Y H:i:s') }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <span class="event-badge event-{{ $log->event }}">
                                            {{ $log->event ?? 'unknown' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-text-400 max-w-xs truncate" title="{{ $log->description }}">
                                        {{ $log->description }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <div class="text-text-400 text-xs">
                                            <div class="font-medium text-text-300">
                                                {{ class_basename($log->subject_type) }}
                                            </div>
                                            <div class="text-text-500">
                                                ID: {{ $log->subject_id }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        @if($log->causer)
                                            <div class="text-text-300 text-sm">
                                                {{ $log->causer->name ?? 'Unknown' }}
                                            </div>
                                            <div class="text-text-500 text-xs">
                                                {{ class_basename($log->causer_type) }}
                                            </div>
                                        @else
                                            <span class="text-text-500 text-sm">System</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-text-400">
                                        @php
                                            $changes = $log->changes;
                                            $hasChanges = !empty($changes);
                                        @endphp
                                        @if($hasChanges && is_array($changes))
                                            <div class="changes-container collapsed rounded px-1 -mx-1"
                                                 title="Click to expand / collapse"
                                                 onclick="this.classList.toggle('collapsed'); this.classList.toggle('expanded');">
                                                @if($log->event === 'updated')
                                                    @foreach($changes as $field => $change)
                                                        @if(is_array($change) && isset($change['old']) && isset($change['new']))
                                                            <span class="change-item">
                                                                <span class="text-text-400">{{ $field }}</span>
                                                                <span class="old-value">{{ $change['old'] ?? 'null' }}</span>
                                                                <span class="arrow">→</span>
                                                                <span class="new-value">{{ $change['new'] ?? 'null' }}</span>
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                @elseif($log->event === 'created')
                                                    @foreach($changes as $key => $value)
                                                        <span class="property-item">
                                                            <span class="key">{{ $key }}</span>
                                                            <span>=</span>
                                                            <span class="value">{{ $value ?? 'null' }}</span>
                                                        </span>
                                                    @endforeach
                                                @elseif($log->event === 'deleted')
                                                    @foreach($changes as $key => $value)
                                                        <span class="property-item">
                                                            <span class="key">{{ $key }}</span>
                                                            <span>=</span>
                                                            <span class="value">{{ $value ?? 'null' }}</span>
                                                        </span>
                                                    @endforeach
                                                @else
                                                    <span class="text-text-500 text-sm">No detailed changes</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-text-500 text-sm">No data</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-text-500">
                                        No database activity logs found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        @if($logs->hasPages())
            <div class="shrink-0 px-4 sm:px-6 py-3 border-t border-border-800 bg-surface-800 flex justify-center">
                {{ $logs->appends(request()->query())->links('vendor.pagination.dark') }}
            </div>
        @endif
    </div>
</div>

@include('layouts.footer')