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
        scroll-color: #4B5563 #1A1A1A;
    }

    .event-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .event-created { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
    .event-updated { background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
    .event-deleted { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
    .event-restored { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
    .event-default { background: rgba(156, 163, 175, 0.2); color: #9ca3af; border: 1px solid rgba(156, 163, 175, 0.3); }

    .log-message {
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
        max-width: 200px;
    }
    .log-message.expanded {
        white-space: normal;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .changes-preview {
        font-size: 0.75rem;
        color: #9ca3af;
        max-width: 300px;
    }
    .changes-preview .added {
        color: #4ade80;
    }
    .changes-preview .removed {
        color: #f87171;
    }

    /* Detail Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    .modal-overlay.active {
        display: flex;
    }
    .modal-content {
        background: #1f2937;
        border-radius: 1rem;
        border: 1px solid #374151;
        max-width: 800px;
        width: 95%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 2rem;
        position: relative;
    }
    .modal-content .close-btn {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: none;
        border: none;
        color: #9ca3af;
        font-size: 1.5rem;
        cursor: pointer;
        transition: color 0.2s;
    }
    .modal-content .close-btn:hover {
        color: #f3f4f6;
    }
    .modal-content .detail-label {
        color: #6b7280;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-top: 1rem;
    }
    .modal-content .detail-value {
        color: #f3f4f6;
        font-size: 0.9rem;
        padding: 0.5rem 0;
        border-bottom: 1px solid #374151;
    }
    .modal-content .detail-value:last-child {
        border-bottom: none;
    }
    .modal-content .changes-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-top: 0.5rem;
    }
    .modal-content .change-box {
        background: #111827;
        border-radius: 0.5rem;
        padding: 0.75rem;
        border: 1px solid #374151;
    }
    .modal-content .change-box .change-title {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 0.25rem;
    }
    .modal-content .change-box .change-value {
        font-size: 0.8rem;
        color: #f3f4f6;
        word-break: break-all;
        font-family: monospace;
    }

    .badge-log-name {
        padding: 0.2rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.65rem;
        font-weight: 500;
        background: #374151;
        color: #d1d5db;
        border: 1px solid #4b5563;
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
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-600/20 text-blue-400 border border-blue-500/30">
                    {{ $logs->total() }} entries
                </span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs sm:text-sm text-text-400">Track all database changes</span>
                <a href="{{ route('database-logs.export', request()->query()) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-500 text-text-100 text-xs font-semibold rounded-lg transition border border-green-500/40 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export CSV
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="shrink-0 px-4 sm:px-6 pt-4 sm:pt-5 pb-3 bg-background-900 border-b border-border-800">
            <form method="GET" action="{{ route('database-logs.index') }}" class="bg-surface-800 rounded-xl border border-border-700 p-3 sm:p-4 w-full">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-2 items-end w-full">
                    <!-- Search -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Search</label>
                        <input type="text" name="search" placeholder="Search logs..." value="{{ request('search') }}"
                               class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>

                    <!-- Log Name -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Log Name</label>
                        <select name="log_name" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                            <option value="">All</option>
                            @foreach($logNames as $name)
                                <option value="{{ $name }}" @selected(request('log_name') == $name)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Event -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Event</label>
                        <select name="event" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                            <option value="">All</option>
                            @foreach($events as $event)
                                <option value="{{ $event }}" @selected(request('event') == $event)>{{ $event }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Causer Type -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Causer Type</label>
                        <select name="causer_type" class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                            <option value="">All</option>
                            @foreach($causerTypes as $type)
                                <option value="{{ $type }}" @selected(request('causer_type') == $type)>{{ class_basename($type) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- From -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">From</label>
                        <input type="date" name="from" value="{{ request('from') }}"
                               class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>

                    <!-- To -->
                    <div>
                        <label class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">To</label>
                        <input type="date" name="to" value="{{ request('to') }}"
                               class="w-full px-2 py-1.5 text-xs border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-1.5 col-span-1 sm:col-span-2 lg:col-span-4 xl:col-span-6">
                        <button type="submit" class="flex-1 px-3 py-1.5 bg-radar-600 hover:bg-radar-500 text-text-100 text-xs font-semibold rounded-lg transition border border-radar-500/40 whitespace-nowrap">
                            Filter
                        </button>
                        <a href="{{ route('database-logs.index') }}" class="flex-1 px-3 py-1.5 bg-surface-700 hover:bg-surface-600 text-text-400 text-xs font-medium rounded-lg transition border border-border-600 whitespace-nowrap text-center">
                            Reset
                        </a>
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
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Log</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Event</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Causer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Subject</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-text-400 uppercase tracking-wider whitespace-nowrap">Changes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-800">
                            @forelse($logs as $log)
                                <tr class="hover:bg-surface-700/60 transition cursor-pointer"
                                    onclick="showLogDetail({{ $log->id }})"
                                    data-log-id="{{ $log->id }}">
                                    <td class="px-4 py-2 whitespace-nowrap text-text-400 font-mono text-xs">
                                        {{ $log->created_at->format('M j, Y H:i:s') }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <span class="badge-log-name">{{ $log->log_name ?? 'default' }}</span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <span class="event-badge event-{{ $log->event ?? 'default' }}">
                                            {{ $log->event ?? 'event' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-text-400">
                                        <div class="log-message collapsed rounded px-1 -mx-1"
                                             title="Click to expand / collapse"
                                             onclick="event.stopPropagation(); this.classList.toggle('collapsed'); this.classList.toggle('expanded');">
                                            {{ $log->description }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-text-400 text-xs">
                                        {{ $log->causer?->name ?? 'System' }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-text-400 text-xs">
                                        <span title="{{ $log->subject_type ?? 'N/A' }}">
                                            {{ $log->subject ? class_basename($log->subject_type) . ' #' . $log->subject_id : 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        @php
                                            $changes = null;
                                            if ($log->properties) {
                                                $props = $log->properties->toArray();
                                                if (isset($props['attributes']) || isset($props['old'])) {
                                                    $changes = [
                                                        'new' => $props['attributes'] ?? [],
                                                        'old' => $props['old'] ?? []
                                                    ];
                                                }
                                            }
                                        @endphp
                                        @if($changes)
                                            <div class="changes-preview">
                                                @foreach($changes['new'] ?? [] as $key => $value)
                                                    @if(isset($changes['old'][$key]) && $changes['old'][$key] != $value)
                                                        <span class="text-text-500">{{ $key }}:</span>
                                                        <span class="removed">{{ $changes['old'][$key] ?? 'null' }}</span>
                                                        <span class="text-text-500">→</span>
                                                        <span class="added">{{ $value }}</span>
                                                        <br>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-text-500 text-xs">No changes</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-text-500">
                                        No activity logs found.
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

<!-- Detail Modal -->
<div id="logDetailModal" class="modal-overlay" onclick="if(event.target === this) closeModal()">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal()">×</button>
        <div id="modalBody">
            <div class="text-text-500 text-center py-8">Loading...</div>
        </div>
    </div>
</div>

<script>
    async function showLogDetail(id) {
        const modal = document.getElementById('logDetailModal');
        const body = document.getElementById('modalBody');

        try {
            modal.classList.add('active');
            body.innerHTML = '<div class="text-text-500 text-center py-8">Loading...</div>';

            const response = await fetch(`/database-logs/${id}`);
            const data = await response.json();

            let changesHtml = '';
            if (data.changes) {
                if (data.changes.old || data.changes.new) {
                    changesHtml = `
                        <div class="changes-grid">
                            ${data.changes.old ? `
                                <div class="change-box">
                                    <div class="change-title">Old Values</div>
                                    <div class="change-value">${JSON.stringify(data.changes.old, null, 2)}</div>
                                </div>
                            ` : ''}
                            ${data.changes.new ? `
                                <div class="change-box">
                                    <div class="change-title">New Values</div>
                                    <div class="change-value">${JSON.stringify(data.changes.new, null, 2)}</div>
                                </div>
                            ` : ''}
                        </div>
                    `;
                }
            }

            body.innerHTML = `
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <span class="event-badge event-${data.event || 'default'}">${data.event || 'event'}</span>
                        <span class="badge-log-name ml-2">${data.log_name || 'default'}</span>
                    </div>
                    <span class="text-text-400 text-sm font-mono">#${data.id}</span>
                </div>

                <div class="detail-label">Description</div>
                <div class="detail-value">${data.description}</div>

                <div class="detail-label">Causer</div>
                <div class="detail-value">${data.causer_name}</div>

                <div class="detail-label">Subject</div>
                <div class="detail-value">${data.subject_type ? class_basename(data.subject_type) + ' #' + data.subject_id : 'N/A'}</div>

                <div class="detail-label">Created At</div>
                <div class="detail-value">${data.created_at}</div>

                ${changesHtml ? `
                    <div class="detail-label">Changes</div>
                    ${changesHtml}
                ` : ''}

                ${data.properties ? `
                    <div class="detail-label">Full Properties</div>
                    <div class="detail-value" style="font-family:monospace;font-size:0.8rem;white-space:pre-wrap;background:#111827;padding:0.75rem;border-radius:0.5rem;margin-top:0.25rem;">
                        ${JSON.stringify(data.properties, null, 2)}
                    </div>
                ` : ''}
            `;

        } catch (error) {
            console.error('Error loading log detail:', error);
            body.innerHTML = `
                <div class="text-munti-red-400 text-center py-8">
                    <p class="text-lg">Failed to load log details</p>
                    <p class="text-sm text-text-500 mt-2">${error.message}</p>
                </div>
            `;
        }
    }

    function closeModal() {
        document.getElementById('logDetailModal').classList.remove('active');
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    function class_basename(str) {
        if (!str) return 'N/A';
        const parts = str.split('\\');
        return parts[parts.length - 1];
    }
</script>

@include('layouts.footer')