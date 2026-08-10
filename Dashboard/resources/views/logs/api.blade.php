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
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    tr.log-row-unseen:hover {
        background-color: rgba(59, 130, 246, 0.14) !important;
        transform: scale(1.001);
    }

    /* Status code badges */
    .status-2xx { background: rgba(34, 197, 94, 0.2); color: #4ade80; border-color: #22c55e; }
    .status-4xx { background: rgba(234, 179, 8, 0.2); color: #facc15; border-color: #eab308; }
    .status-5xx { background: rgba(239, 68, 68, 0.2); color: #f87171; border-color: #ef4444; }
    .status-default { background: rgba(156, 163, 175, 0.2); color: #9ca3af; border-color: #6b7280; }

    .method-badge {
        font-family: monospace;
        font-weight: 600;
        padding: 0.1rem 0.5rem;
        border-radius: 0.25rem;
        border: 1px solid transparent;
    }
    .method-GET { background: #1e3a5f; color: #60a5fa; border-color: #3b82f6; }
    .method-POST { background: #1e3a3a; color: #34d399; border-color: #10b981; }
    .method-PUT { background: #3b1e3b; color: #c084fc; border-color: #a855f7; }
    .method-PATCH { background: #3b2e1e; color: #fbbf24; border-color: #f59e0b; }
    .method-DELETE { background: #3b1e1e; color: #f87171; border-color: #ef4444; }
    .method-HEAD { background: #2d2d2d; color: #9ca3af; border-color: #6b7280; }
    .method-OPTIONS { background: #2d2d2d; color: #9ca3af; border-color: #6b7280; }

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
                <span class="text-xs sm:text-sm text-text-400">Inspect incoming API requests</span>
                <button type="button" id="markAllSeenBtn" 
                        class="text-xs px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition">
                    Mark All as Seen
                </button>
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
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 px-4 sm:px-6 py-3">
            <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden">
                <div class="overflow-x-auto thin-scrollbar">
                    <table class="min-w-full divide-y divide-border-700 text-sm">
                        <thead class="bg-surface-900/80 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Client IP</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Method</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Path</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Duration (ms)</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">API Key Owner</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">API Key</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Seen</th>
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
                                        {{ \Carbon\Carbon::parse($log->getRawOriginal('created_at'), 'UTC')->setTimezone('Asia/Manila')->format('M j, Y H:i:s') }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-text-400 font-mono text-xs">
                                        {{ $log->client_ip }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <span class="method-badge method-{{ $log->method }} text-xs">
                                            {{ $log->method }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-text-400 max-w-xs truncate" title="{{ $log->path }}">
                                        {{ $log->path }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        @php
                                            $status = $log->status_code;
                                            $class = 'status-default';
                                            if ($status >= 200 && $status < 300) $class = 'status-2xx';
                                            elseif ($status >= 400 && $status < 500) $class = 'status-4xx';
                                            elseif ($status >= 500) $class = 'status-5xx';
                                        @endphp
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $class }}">
                                            {{ $status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-text-400 font-mono text-xs">
                                        {{ number_format($log->duration_ms, 2) }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-xs max-w-32 truncate {{ $log->api_key_owner === 'Blocked/IP' ? 'text-red-400 font-semibold' : ($log->api_key_owner === 'Unauthorized/None' ? 'text-orange-400 font-semibold' : 'text-text-400') }}" title="{{ $log->api_key_owner }}">
                                        {{ $log->api_key_owner }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-text-400 font-mono text-xs max-w-28 truncate" title="{{ $log->api_key_used }}">
                                        {{ $log->api_key_used }}
                                    </td>
                                    <td class="seen-cell">
                                        @if($isUnseen)
                                            <span class="unseen-text">
                                                <span class="dot"></span>
                                                Unseen
                                            </span>
                                        @else
                                            <span class="seen-text">Seen</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-10 text-center text-text-500">
                                        No API logs found.
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        if (!csrfToken) {
            console.error('CSRF token not found');
        }

        // Individual row click handler
        document.querySelectorAll('tbody tr[data-log-id]').forEach(row => {
            row.addEventListener('click', async function(e) {
                // Don't trigger if clicking on a button or link inside the row
                if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) {
                    return;
                }
                
                const logId = this.dataset.logId;
                const isUnseen = this.dataset.isUnseen === 'true';
                
                // Only mark as seen if it's currently unseen
                if (!isUnseen) {
                    return;
                }
                
                // Show loading state
                this.style.opacity = '0.6';
                this.style.cursor = 'wait';
                
                try {
                    // Method 1: Use a simple string concatenation (no double slash)
                    const url = '/api-logs/' + logId + '/mark-seen';
                    console.log('Request URL:', url);
                    
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    
                    // Check if response is OK before trying to parse JSON
                    if (!response.ok) {
                        const text = await response.text();
                        console.error('Server response:', text);
                        throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                    }
                    
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        console.error('Non-JSON response:', text);
                        throw new Error('Server returned non-JSON response');
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Update the row visually
                        this.dataset.isUnseen = 'false';
                        this.classList.remove('log-row-unseen');
                        this.style.cursor = 'default';
                        
                        // Update the seen cell
                        const seenCell = this.querySelector('.seen-cell');
                        if (seenCell) {
                            seenCell.innerHTML = `<span class="seen-text">Seen</span>`;
                        }
                        
                        // Reset opacity
                        this.style.opacity = '1';
                        
                        // Update the unseen badge in header
                        updateUnseenBadge();
                    } else {
                        throw new Error(data.message || 'Failed to mark log as seen');
                    }
                } catch (err) {
                    console.error('Error marking log as seen:', err);
                    this.style.opacity = '1';
                    this.style.cursor = 'default';
                    showToast('Failed to mark log as seen: ' + err.message, 'error');
                }
            });
        });
        
        // Update the unseen badge count
        function updateUnseenBadge() {
            const unseenRows = document.querySelectorAll('tbody tr[data-is-unseen="true"]');
            const unseenCount = unseenRows.length;
            
            const badge = document.querySelector('.unseen-badge');
            if (badge) {
                if (unseenCount > 0) {
                    badge.innerHTML = `
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 mr-1.5"></span>
                        ${unseenCount} new
                    `;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.style.display = 'none';
                }
            }
        }
        
        // Toast notification function
        function showToast(message, type = 'info') {
            const colors = {
                success: 'bg-green-600',
                error: 'bg-red-600',
                info: 'bg-blue-600'
            };
            
            const toast = document.createElement('div');
            toast.className = `fixed bottom-4 right-4 px-4 py-3 rounded-lg text-white text-sm z-50 transition-all duration-300 shadow-lg ${colors[type] || colors.info}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            // Auto dismiss
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(20px)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Mark All as Seen button
        document.getElementById('markAllSeenBtn')?.addEventListener('click', async function() {
            // Check if Swal is available
            if (typeof Swal === 'undefined') {
                console.error('SweetAlert2 not loaded');
                showToast('SweetAlert2 not loaded', 'error');
                return;
            }
            
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
                const response = await fetch('/api-logs/mark-seen', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ ids: [] })
                });
                
                if (!response.ok) {
                    const text = await response.text();
                    console.error('Server response:', text);
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                
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