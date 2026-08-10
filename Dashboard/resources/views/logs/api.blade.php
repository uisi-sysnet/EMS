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

    /* ============================================ */
    /* IMPROVED UNSEEN LOG HIGHLIGHT               */
    /* ============================================ */
    tr.log-row-unseen {
        background: linear-gradient(
            90deg,
            rgba(59, 130, 246, 0.12) 0%,
            rgba(59, 130, 246, 0.05) 100%
        ) !important;
        position: relative;
    }

    tr.log-row-unseen td {
        background: transparent !important;
    }

    /* Strong left accent bar */
    tr.log-row-unseen::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(to bottom, #3b82f6, #60a5fa);
        border-radius: 0 3px 3px 0;
        z-index: 2;
        box-shadow: 0 0 8px rgba(59, 130, 246, 0.4);
    }

    tr.log-row-unseen:hover {
        background: linear-gradient(
            90deg,
            rgba(59, 130, 246, 0.18) 0%,
            rgba(59, 130, 246, 0.08) 100%
        ) !important;
    }

    /* Status badges */
    .status-2xx { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.35); }
    .status-4xx { background: rgba(234, 179, 8, 0.15); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.35); }
    .status-5xx { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.35); }
    .status-default { background: rgba(156, 163, 175, 0.12); color: #9ca3af; border: 1px solid rgba(156, 163, 175, 0.25); }

    /* Method badges */
    .method-badge {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-weight: 600;
        font-size: 0.7rem;
        padding: 0.15rem 0.55rem;
        border-radius: 0.3rem;
        letter-spacing: 0.02em;
        border: 1px solid transparent;
        display: inline-block;
        min-width: 52px;
        text-align: center;
    }
    .method-GET    { background: #1e3a5f; color: #60a5fa; border-color: #3b82f6; }
    .method-POST   { background: #1e3a3a; color: #34d399; border-color: #10b981; }
    .method-PUT    { background: #3b1e3b; color: #c084fc; border-color: #a855f7; }
    .method-PATCH  { background: #3b2e1e; color: #fbbf24; border-color: #f59e0b; }
    .method-DELETE { background: #3b1e1e; color: #f87171; border-color: #ef4444; }
    .method-HEAD,
    .method-OPTIONS { background: #2d2d2d; color: #9ca3af; border-color: #6b7280; }

    /* Unseen badge pulse */
    .unseen-badge {
        animation: pulse-badge 2s infinite;
    }
    @keyframes pulse-badge {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.55; }
    }

    /* Seen At column */
    .seen-at-cell {
        min-width: 90px;
        text-align: center;
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
        white-space: nowrap;
    }

    .seen-at-cell .unseen-text {
        color: #60a5fa;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        letter-spacing: 0.01em;
    }

    .seen-at-cell .unseen-text .dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background-color: #3b82f6;
        box-shadow: 0 0 6px rgba(59, 130, 246, 0.7);
        animation: pulse-dot 1.6s ease-in-out infinite;
    }

    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.35; transform: scale(0.75); }
    }

    .seen-at-cell .seen-text {
        color: #9ca3af;
        font-variant-numeric: tabular-nums;
    }

    /* Table refinements */
    .api-logs-table th {
        font-size: 0.7rem;
        letter-spacing: 0.04em;
        padding-top: 0.85rem;
        padding-bottom: 0.85rem;
    }

    .api-logs-table td {
        vertical-align: middle;
    }

    .api-logs-table tbody tr {
        transition: background-color 0.15s ease;
    }

    .api-logs-table tbody tr:not(.log-row-unseen):hover {
        background-color: rgba(255, 255, 255, 0.03);
    }
</style>

<div id="main-content"
     class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">

    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header with Unseen Badge -->
        <div class="px-5 sm:px-8 py-4 sm:py-5 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 shrink-0">
            <div class="flex items-center gap-3">
                <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                    <span class="leading-tight uppercase">API Logs</span>
                </h2>
                @if(isset($unseenCount) && $unseenCount > 0)
                    <span class="unseen-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-600/20 text-blue-400 border border-blue-500/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 mr-1.5"></span>
                        {{ $unseenCount }} new
                    </span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <button type="button" id="markAllSeenBtn" 
                        class="text-xs px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition">
                    Mark All as Seen
                </button>
                <span class="text-xs sm:text-sm text-text-400">Inspect incoming API requests</span>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="shrink-0 px-4 sm:px-6 pt-4 sm:pt-5 pb-3 bg-background-900 border-b border-border-800">
            <form method="GET" action="{{ route('api-logs.index') }}" class="bg-surface-800 rounded-xl border border-border-700 p-3 sm:p-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-2">
                    <!-- Client IP -->
                    <div class="min-w-0">
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Client IP</label>
                        <input type="text" name="client_ip" placeholder="e.g. 192.168.1.1" value="{{ request('client_ip') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- Method -->
                    <div class="min-w-0">
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Method</label>
                        <select name="method" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                            <option value="">All</option>
                            @foreach(['GET','POST','PUT','PATCH','DELETE','HEAD','OPTIONS'] as $m)
                                <option value="{{ $m }}" @selected(request('method') == $m)>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <!-- Path -->
                    <div class="min-w-0">
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Path</label>
                        <input type="text" name="path" placeholder="/api/users" value="{{ request('path') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- Status -->
                    <div class="min-w-0">
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Status</label>
                        <input type="text" name="status_code" placeholder="e.g. 200" value="{{ request('status_code') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- From -->
                    <div class="min-w-0">
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">From</label>
                        <input type="date" name="from" value="{{ request('from', $defaultFrom ?? '') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- To -->
                    <div class="min-w-0">
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">To</label>
                        <input type="date" name="to" value="{{ request('to', $defaultTo ?? '') }}" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>
                    <!-- Buttons -->
                    <div class="flex gap-1.5 items-end">
                        <button type="submit" class="flex-1 px-3 py-1.5 bg-radar-600 hover:bg-radar-500 text-text-100 text-xs font-semibold rounded-lg transition border border-radar-500/40 whitespace-nowrap">Filter</button>
                        <a href="{{ route('api-logs.index') }}" class="flex-1 px-3 py-1.5 bg-surface-700 hover:bg-surface-600 text-text-400 text-xs font-medium rounded-lg transition border border-border-600 whitespace-nowrap text-center">Reset</a>
                        <a href="{{ route('api-logs.export', request()->query()) }}" 
                        class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-500 text-text-100 text-xs font-semibold rounded-lg transition border border-green-500/40">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table (scrollable) -->
        <table class="min-w-full divide-y divide-border-700 text-sm api-logs-table">
            <thead class="bg-surface-900/90 sticky top-0 z-10 backdrop-blur-sm">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Client IP</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Method</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Path</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Duration</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">API Key Owner</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">API Key</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Seen At</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-800">
                @forelse($logs as $log)
                    @php
                        $isUnseen = is_null($log->seen_at);
                    @endphp
                    <tr class="{{ $isUnseen ? 'log-row-unseen' : '' }}"
                        data-log-id="{{ $log->id }}"
                        data-is-unseen="{{ $isUnseen ? 'true' : 'false' }}">
                        <td class="px-4 py-2.5 whitespace-nowrap text-text-400 font-mono text-xs">
                            {{ \Carbon\Carbon::parse($log->getRawOriginal('created_at'), 'UTC')->setTimezone('Asia/Manila')->format('M j, Y H:i:s') }}
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap text-text-300 font-mono text-xs">
                            {{ $log->client_ip }}
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            <span class="method-badge method-{{ $log->method }}">
                                {{ $log->method }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-text-300 max-w-[220px] truncate font-mono text-xs" title="{{ $log->path }}">
                            {{ $log->path }}
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            @php
                                $status = $log->status_code;
                                $class = 'status-default';
                                if ($status >= 200 && $status < 300) $class = 'status-2xx';
                                elseif ($status >= 400 && $status < 500) $class = 'status-4xx';
                                elseif ($status >= 500) $class = 'status-5xx';
                            @endphp
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $class }}">
                                {{ $status }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap text-text-400 font-mono text-xs tabular-nums">
                            {{ number_format($log->duration_ms, 2) }}
                            <span class="text-text-500 text-[10px] ml-0.5">ms</span>
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap text-xs max-w-[140px] truncate
                            {{ $log->api_key_owner === 'Blocked/IP' ? 'text-red-400 font-semibold' : ($log->api_key_owner === 'Unauthorized/None' ? 'text-orange-400 font-semibold' : 'text-text-400') }}"
                            title="{{ $log->api_key_owner }}">
                            {{ $log->api_key_owner }}
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap text-text-400 font-mono text-xs max-w-[120px] truncate" title="{{ $log->api_key_used }}">
                            {{ $log->api_key_used }}
                        </td>
                        <td class="seen-at-cell">
                            @if($isUnseen)
                                <span class="unseen-text">
                                    <span class="dot"></span>
                                    Unseen
                                </span>
                            @else
                                <span class="seen-text">
                                    {{ $log->seen_at->setTimezone('Asia/Manila')->format('h:i A') }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-text-500">
                            No API logs found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Pagination -->
        @if($logs->hasPages())
            <div class="shrink-0 px-4 sm:px-6 py-3 border-t border-border-800 bg-surface-800 flex justify-center">
                {{ $logs->appends(request()->query())->links('vendor.pagination.dark') }}
            </div>
        @endif
    </div>
</div>

<script>
    // ============ MARK AS SEEN FUNCTIONALITY ============
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
                const response = await fetch('{{ route("api-logs.mark-as-seen") }}', {
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
        
        // Debug: Check what rows have the class
        const unseenRows = document.querySelectorAll('.log-row-unseen');
        console.log('Unseen rows found:', unseenRows.length);
        unseenRows.forEach((row, i) => {
            console.log(`Row ${i + 1}:`, {
                id: row.dataset.logId,
                isUnseen: row.dataset.isUnseen,
                classList: row.className,
                bgColor: getComputedStyle(row).backgroundColor
            });
        });
    });
</script>

@include('layouts.footer')