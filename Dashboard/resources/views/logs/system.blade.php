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
</style>

<div id="main-content"
     class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">

    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header (fixed) -->
        <div class="px-5 sm:px-8 py-4 sm:py-5 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 shrink-0">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                <span class="leading-tight uppercase">System Logs</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">Filter and browse system log entries</span>
        </div>

        <!-- Filter (fixed) -->
        <div class="shrink-0 px-4 sm:px-6 pt-4 sm:pt-5 pb-3 bg-background-900 border-b border-border-800">
            <form method="GET" action="{{ route('logs.index') }}"
                  class="bg-surface-800 rounded-xl border border-border-700 p-4 sm:p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 sm:gap-4 items-end">
                    <div>
                        <label class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">Service</label>
                        <input type="text" name="service" placeholder="Service"
                               value="{{ request('service') }}"
                               class="w-full px-3 py-2 border border-border-600 rounded-lg
                                      bg-surface-900 text-text-100 placeholder-text-500
                                      focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm transition">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">Level</label>
                        <select name="level"
                                class="w-full px-3 py-2 border border-border-600 rounded-lg
                                       bg-surface-900 text-text-100
                                       focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm transition">
                            <option value="">All levels</option>
                            <option value="error" @selected(request('level')=='error')>Error</option>
                            <option value="warning" @selected(request('level')=='warning')>Warning</option>
                            <option value="info" @selected(request('level')=='info')>Info</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">Thread</label>
                        <input type="text" name="thread" placeholder="Thread"
                               value="{{ request('thread') }}"
                               class="w-full px-3 py-2 border border-border-600 rounded-lg
                                      bg-surface-900 text-text-100 placeholder-text-500
                                      focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm transition">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">From</label>
                        <input type="date" name="from" value="{{ request('from') }}"
                               class="w-full px-3 py-2 border border-border-600 rounded-lg
                                      bg-surface-900 text-text-100
                                      focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm transition">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">To</label>
                        <input type="date" name="to" value="{{ request('to') }}"
                               class="w-full px-3 py-2 border border-border-600 rounded-lg
                                      bg-surface-900 text-text-100
                                      focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm transition">
                    </div>

                    <div class="flex gap-2">
                        <button type="submit"
                                class="flex-1 px-4 py-2 bg-radar-600 hover:bg-radar-500 text-text-100 text-sm font-semibold rounded-lg transition border border-radar-500/40">
                            Filter
                        </button>
                        <a href="{{ route('logs.index') }}"
                           class="px-4 py-2 bg-surface-700 hover:bg-surface-600 text-text-400 text-sm font-medium rounded-lg transition border border-border-600 flex items-center justify-center">
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table only (scrollable) -->
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 px-4 sm:px-6 py-3">
            <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden">
                <div class="overflow-x-auto thin-scrollbar">
                    <table class="min-w-full divide-y divide-border-700 text-sm">
                        <thead class="bg-surface-900/80 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Service</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Level</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Logger</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Thread</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider">Message</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-800">
                            @forelse($logs as $log)
                                <tr class="hover:bg-surface-700/60 transition">
                                    <td class="px-4 py-2 whitespace-nowrap text-text-400 font-mono text-xs">
                                        {{ $log->created_at->format('M j, Y H:i:s') }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-text-400">
                                        {{ $log->service }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        @php
                                            $badge = match($log->level) {
                                                'ERROR' =>
                                                    'bg-munti-red-700/20 text-munti-red-400 border-munti-red-600/30',
                                                'WARNING' =>
                                                    'bg-munti-yellow-600/20 text-munti-yellow-400 border-munti-yellow-500/30',
                                                'INFO' =>
                                                    'bg-radar-600/20 text-radar-400 border-radar-500/30',
                                                default =>
                                                    'bg-surface-700 text-text-400 border-border-600'
                                            };
                                        @endphp
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $badge }}">
                                            {{ $log->level }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-text-400 text-xs">
                                        {{ $log->logger_name }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-text-400 text-xs font-mono">
                                        {{ $log->thread_name }}
                                    </td>
                                    <td class="px-4 py-2 text-text-400">
                                        <div class="log-message collapsed rounded px-1 -mx-1"
                                             title="Click to expand / collapse"
                                             onclick="this.classList.toggle('collapsed'); this.classList.toggle('expanded');">
                                            {{ $log->message }}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-text-500">
                                        No logs found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination (fixed) -->
        @if($logs->hasPages())
            <div class="shrink-0 px-4 sm:px-6 py-3 border-t border-border-800 bg-surface-800 flex justify-center">
                {{ $logs->appends(request()->query())->links('vendor.pagination.dark') }}
            </div>
        @endif
    </div>
</div>

@include('layouts.footer')