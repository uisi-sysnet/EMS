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

<div id="main-content"
     class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">

    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header with Unseen Badge -->
        <div class="px-5 sm:px-8 py-4 sm:py-5 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 shrink-0">
            <div class="flex items-center gap-3">
                <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                    <span class="leading-tight uppercase">System Logs</span>
                </h2>
                @if(isset($unseenCount) && $unseenCount > 0)
                    <span class="unseen-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-600/20 text-blue-400 border border-blue-500/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 mr-1.5"></span>
                        {{ $unseenCount }} new
                    </span>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs sm:text-sm text-text-400">Filter and browse system log entries</span>
                <div class="flex items-center gap-2">
                    <button type="button" id="markAllSeenBtn" 
                            class="text-xs px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition">
                        Mark All as Archived
                    </button>
                    @if(isset($unseenCount) && $unseenCount > 0)
                        <span class="inline-flex items-center justify-center min-w-[24px] h-6 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-600/30 text-blue-300 border border-blue-500/40">
                            {{ $unseenCount }}
                        </span>
                    @else
                        <span class="inline-flex items-center justify-center min-w-[24px] h-6 px-2.5 py-0.5 rounded-full text-xs font-medium bg-surface-700/50 text-text-500 border border-border-600/50">
                            0
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Filter Bar – responsive with wrap (System Logs) -->
        <div class="shrink-0 px-4 sm:px-6 pt-4 sm:pt-5 pb-3 bg-background-900 border-b border-border-800">
            <form method="GET" action="{{ route('logs.index') }}" class="bg-surface-800 rounded-xl border border-border-700 p-3 sm:p-4 w-full">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-8 gap-2 items-end w-full">
                    <!-- Service -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Service</label>
                        <input type="text" name="service" placeholder="Service" value="{{ request('service') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- Level -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Level</label>
                        <select name="level" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                            <option value="">All</option>
                            <option value="ERROR" @selected(request('level')=='ERROR')>Error</option>
                            <option value="WARNING" @selected(request('level')=='WARNING')>Warning</option>
                            <option value="INFO" @selected(request('level')=='INFO')>Info</option>
                        </select>
                    </div>
                    <!-- Logger -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Logger</label>
                        <input type="text" name="logger" placeholder="Logger name" value="{{ request('logger') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- Thread -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Thread</label>
                        <input type="text" name="thread" placeholder="Thread" value="{{ request('thread') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- From -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">From</label>
                        <input type="date" name="from" value="{{ request('from', $defaultFrom ?? '') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- To -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">To</label>
                        <input type="date" name="to" value="{{ request('to', $defaultTo ?? '') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- Buttons -->
                    <div class="flex gap-1.5">
                        <button type="submit" class="flex-1 px-3 py-1.5 bg-radar-600 hover:bg-radar-500 text-text-100 text-xs font-semibold rounded-lg transition border border-radar-500/40 whitespace-nowrap">Filter</button>
                        <a href="{{ route('logs.index') }}" class="flex-1 px-3 py-1.5 bg-surface-700 hover:bg-surface-600 text-text-400 text-xs font-medium rounded-lg transition border border-border-600 whitespace-nowrap text-center">Reset</a>
                        <a href="{{ route('logs.export', request()->query()) }}" 
                           class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-500 text-text-100 text-xs font-semibold rounded-lg transition border border-green-500/40 whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
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
                                {{-- <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Seen</th> --}}
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-800">
                            @forelse($logs as $log)
                                @php
                                    $isUnseen = is_null($log->seen_at);
                                @endphp
                                <tr class="{{ $isUnseen ? 'log-row-unseen' : '' }} hover:bg-surface-700/60 transition"
                                    data-log-id="{{ $log->id }}"
                                    data-is-unseen="{{ $isUnseen ? 'true' : 'false' }}">
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
                                    <td class="px-6 py-2 text-text-400">
                                        <div class="log-message collapsed rounded px-1 -mx-1"
                                             title="Click to expand / collapse"
                                             onclick="this.classList.toggle('collapsed'); this.classList.toggle('expanded');">
                                            {{ $log->message }}
                                        </div>
                                    </td>
                                    {{-- <td class="seen-cell">
                                        @if($isUnseen)
                                            <span class="unseen-text">
                                                <span class="dot"></span>
                                                Unseen
                                            </span>
                                        @else
                                            <span class="seen-text">Seen</span>
                                        @endif
                                    </td> --}}
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-text-500">
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