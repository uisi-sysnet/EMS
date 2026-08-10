@include('layouts.header')
@include('layouts.topbar')

<style>
    .thin-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
    .thin-scrollbar::-webkit-scrollbar-track { background: #1A1A1A; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb { background: #4B5563; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb:hover { background: #6B7280; }
    .thin-scrollbar { scrollbar-width: thin; scrollbar-color: #4B5563 #1A1A1A; }

    
    /* Highlight for unseen logs - BLUE background */
    .log-row-unseen {
        background-color: rgba(59, 130, 246, 0.15) !important;
        border-left: 3px solid #3b82f6;
    }
    
    .log-row-unseen:hover {
        background-color: rgba(59, 130, 246, 0.25) !important;
    }
    
    /* Unseen badge pulse animation */
    .unseen-badge {
        animation: pulse-badge 2s infinite;
    }
    
    @keyframes pulse-badge {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

</style>

<div id="main-content" class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header with Unseen Badge -->
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div class="flex items-center gap-3">
                <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2.5">
                    <span class="leading-tight uppercase tracking-wide">API Logs</span>
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
                <span class="text-xs sm:text-sm text-text-400">View API request logs</span>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-6 px-5 sm:px-8">

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 h-full">

                <!-- ==================== LEFT: ALL API IN ONE CARD ==================== -->
                <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm h-full">

                    <!-- Card Header -->
                    <div class="px-5 py-3.5 border-b border-border-700 bg-surface-900/70 flex items-center gap-2">
                        <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider">API Keys</h3>
                    </div>

                    <!-- Form Section -->
                    <div class="p-5 border-b border-border-700">
                        <form id="apiKeyForm" class="space-y-4">
                            <!-- Owner + Key in the same row -->
                            <div class="flex flex-col sm:flex-row gap-3 items-end">
                                <!-- Owner -->
                                <div class="flex-1 w-full min-w-0">
                                    <label for="ownerLabel" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                        Owner Label
                                    </label>
                                    <input type="text" id="ownerLabel" placeholder="e.g. Dashboard Client" required
                                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg
                                            bg-surface-900 text-text-100 placeholder-text-500
                                            focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                                </div>

                                <!-- Generated Key (always visible) -->
                                <div class="flex-1 w-full min-w-0">
                                    <label for="generatedKey" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                        API Key
                                    </label>
                                    <div class="flex gap-2">
                                        <input type="text" id="generatedKey" readonly
                                            class="flex-1 min-w-0 px-3.5 py-2.5 border border-border-600 rounded-lg
                                                bg-surface-800 text-text-100 font-mono text-sm
                                                focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 transition cursor-default">
                                        <button type="button" id="generateBtn"
                                                class="px-3 py-2 bg-surface-700 hover:bg-surface-600 text-text-300 rounded-lg border border-border-600 transition flex items-center shrink-0"
                                                title="Regenerate key">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" id="saveBtn"
                                    class="w-full px-4 py-2.5 bg-munti-green-600 hover:bg-munti-green-500 text-text-100 font-semibold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed border border-munti-green-500/30 flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Save Key
                            </button>
                        </form>
                        <div id="apiStatus" class="mt-3 text-sm font-medium text-center min-h-[1.25rem]"></div>
                    </div>

                    <!-- Table Section -->
                    <div class="overflow-x-auto">
                        @if($logs->isNotEmpty())
                            <table class="min-w-full divide-y divide-border-700">
                                <thead class="bg-surface-900/60 text-[11px] uppercase tracking-wider text-text-500">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">
                                            <input type="checkbox" id="selectAllLogs" class="rounded border-border-600 bg-surface-900 text-radar-600">
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Date</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Client IP</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Method</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Path</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Status</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Duration</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">API Key</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium">Seen At</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border-800">
                                    @foreach($logs as $log)
                                        @php
                                            $isUnseen = is_null($log->seen_at);
                                        @endphp
                                        <tr class="hover:bg-surface-700/50 transition {{ $isUnseen ? 'log-row-unseen' : '' }}">
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                <input type="checkbox" class="log-checkbox rounded border-border-600 bg-surface-900 text-radar-600" 
                                                    data-id="{{ $log->id }}" {{ $isUnseen ? 'data-unseen="true"' : '' }}>
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $log->created_at->setTimezone('Asia/Manila')->format('Y-m-d h:i:s A') }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                                {{ $log->client_ip }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs">
                                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                                    @if($log->method == 'GET') bg-blue-600/20 text-blue-400
                                                    @elseif($log->method == 'POST') bg-green-600/20 text-green-400
                                                    @elseif($log->method == 'PUT') bg-yellow-600/20 text-yellow-400
                                                    @elseif($log->method == 'DELETE') bg-red-600/20 text-red-400
                                                    @else bg-gray-600/20 text-gray-400 @endif">
                                                    {{ $log->method }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5 text-xs text-text-400 max-w-[200px] truncate" title="{{ $log->path }}">
                                                {{ $log->path }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                    @if($log->status_code >= 200 && $log->status_code < 300) bg-green-600/20 text-green-400
                                                    @elseif($log->status_code >= 400 && $log->status_code < 500) bg-yellow-600/20 text-yellow-400
                                                    @elseif($log->status_code >= 500) bg-red-600/20 text-red-400
                                                    @else bg-gray-600/20 text-gray-400 @endif">
                                                    {{ $log->status_code }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-400">
                                                {{ number_format($log->duration_ms, 2) }}ms
                                            </td>
                                            <td class="px-4 py-2.5 text-xs text-text-400 max-w-[150px] truncate" title="{{ $log->api_key_owner ?? 'N/A' }}">
                                                {{ $log->api_key_owner ?? 'N/A' }}
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-xs">
                                                @if($isUnseen)
                                                    <span class="text-blue-400 font-medium flex items-center gap-1">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse"></span>
                                                        Unseen
                                                    </span>
                                                @else
                                                    <span class="text-text-500">
                                                        {{ $log->seen_at->setTimezone('Asia/Manila')->format('h:i A') }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="flex items-center justify-center h-32 text-sm text-text-500">
                                No API logs found
                            </div>
                        @endif
                    </div>
                </div>

                <!-- ==================== RIGHT: ALL IP IN ONE CARD ==================== -->
                <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm h-full">

                    <!-- Card Header -->
                    <div class="px-5 py-3.5 border-b border-border-700 bg-surface-900/70 flex items-center gap-2">
                        <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider">Allowed Networks</h3>
                    </div>

                    <!-- Form Section -->
                    <div class="p-5 border-b border-border-700">
                        <form id="allowedIpForm" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
                                <!-- CIDR -->
                                <div class="sm:col-span-5">
                                    <label for="cidr" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">IP/Network</label>
                                    <input type="text" id="cidr" placeholder="192.168.1.0/24" required
                                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg
                                                bg-surface-900 text-text-100 placeholder-text-500
                                                focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition font-mono">
                                </div>

                                <!-- Label -->
                                <div class="sm:col-span-5">
                                    <label for="label" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">Label</label>
                                    <input type="text" id="label" placeholder="Office Network" required
                                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg
                                                bg-surface-900 text-text-100 placeholder-text-500
                                                focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                                </div>

                                <!-- Enabled -->
                                <div class="sm:col-span-2 flex items-center gap-2 pb-2.5">
                                    <input type="checkbox" id="enabled" checked
                                        class="h-4 w-4 rounded border-border-600 bg-surface-900 text-munti-green-600 focus:ring-munti-green-500 focus:ring-offset-0">
                                    <label for="enabled" class="text-sm text-text-300 select-none cursor-pointer">Enabled</label>
                                </div>
                            </div>

                            <button type="submit" id="saveIpBtn"
                                    class="w-full px-4 py-2.5 bg-munti-green-600 hover:bg-munti-green-500 text-text-100 font-semibold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed border border-munti-green-500/30 flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Add Allowed IP
                            </button>
                        </form>
                        <div id="networkStatus" class="mt-3 text-sm font-medium text-center min-h-[1.25rem]"></div>
                    </div>

                    <!-- Table Section -->
                    <div class="flex-1 flex flex-col min-h-0">
                        <div class="px-5 py-3 border-b border-border-700 bg-surface-900/40">
                            <h4 class="text-xs font-semibold text-text-400 uppercase tracking-wider flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-radar-400"></span>
                                Current Allowed IPs
                            </h4>
                        </div>

                        <div class="overflow-x-auto thin-scrollbar flex-1">
                            @if($ips->isNotEmpty())
                                <table class="min-w-full divide-y divide-border-700">
                                    <thead class="bg-surface-900/60 text-[11px] uppercase tracking-wider text-text-500">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left font-medium">IP/Network</th>
                                            <th scope="col" class="px-4 py-3 text-left font-medium">Label</th>
                                            <th scope="col" class="px-4 py-3 text-left font-medium">Status</th>
                                            {{-- <th scope="col" class="px-4 py-3 text-left font-medium">Created</th> --}}
                                            <th scope="col" class="px-4 py-3 text-right font-medium">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border-800">
                                        @foreach($ips as $ip)
                                            <tr class="hover:bg-surface-700/50 transition">
                                                <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-munti-green-400">
                                                    {{ $ip->cidr }}
                                                </td>
                                                <td class="px-4 py-2.5 text-xs text-text-200">
                                                    {{ $ip->label }}
                                                </td>
                                                <td class="px-4 py-2.5 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border
                                                        {{ $ip->enabled
                                                            ? 'bg-munti-green-700/15 text-munti-green-400 border-munti-green-600/30'
                                                            : 'bg-munti-red-700/15 text-munti-red-400 border-munti-red-600/30' }}">
                                                        {{ $ip->enabled ? 'Enabled' : 'Disabled' }}
                                                    </span>
                                                </td>
                                                {{-- <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                                    {{ \Carbon\Carbon::parse($ip->created_at)->format('Y-m-d h:i A') }}
                                                </td> --}}
                                                <td class="px-4 py-2.5 whitespace-nowrap text-right">
                                                    <button type="button"
                                                            class="delete-ip text-munti-red-400 hover:text-munti-red-300 transition p-1.5 rounded-lg hover:bg-munti-red-700/20"
                                                            data-cidr="{{ $ip->cidr }}"
                                                            title="Delete IP">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24">
                                                            <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="flex items-center justify-center h-32 text-sm text-text-500">
                                    No allowed IPs yet
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    // ============ API KEY JAVASCRIPT ============
    const ownerLabelInput = document.getElementById('ownerLabel');
    const generateBtn = document.getElementById('generateBtn');
    const saveBtn = document.getElementById('saveBtn');
    const apiStatus = document.getElementById('apiStatus');
    const apiForm = document.getElementById('apiKeyForm');
    const generatedKeyInput = document.getElementById('generatedKey');

    let currentPlainKey = '';

    function setApiStatus(message, type = 'info') {
        apiStatus.className = 'mt-3 text-sm font-medium text-center min-h-[1.25rem]';
        const classes = {
            'success': 'text-munti-green-400',
            'error': 'text-munti-red-400',
            'info': 'text-radar-400',
            'warning': 'text-munti-yellow-400'
        };
        apiStatus.classList.add(classes[type] || 'text-text-400');
        apiStatus.textContent = message;
    }

    function validateApiForm() {
        const label = ownerLabelInput.value.trim();
        const hasKey = generatedKeyInput.value.trim().length > 0;
        saveBtn.disabled = !(label && hasKey);
    }
    ownerLabelInput.addEventListener('input', validateApiForm);
    generatedKeyInput.addEventListener('input', validateApiForm);

    // Generate key function
    async function generateKey() {
        setApiStatus('Generating key...', 'info');
        try {
            const response = await fetch('{{ route('api.keys.generate') }}');
            const data = await response.json();
            if (data.key) {
                currentPlainKey = data.key;
                generatedKeyInput.value = currentPlainKey;
                setApiStatus('Key generated. Click Save to store it.', 'success');
                validateApiForm();
            } else {
                setApiStatus('Failed to generate key.', 'error');
            }
        } catch (err) {
            setApiStatus('Server error', 'error');
            console.error(err);
        }
    }

    // Auto-generate on page load
    generateKey();

    // Manual regenerate button
    generateBtn.addEventListener('click', generateKey);

    // Form submit → show popup first, then save
    apiForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const owner_label = ownerLabelInput.value.trim();
        const token_hash = generatedKeyInput.value.trim();
        if (!owner_label || !token_hash) return;

        // ===== SweetAlert Popup =====
        const result = await Swal.fire({
            title: 'Copy Your API Key',
            html: `
                <div class="text-left">
                    <p class="text-sm text-yellow-400 mb-4 font-medium">
                        ⚠️ <strong>Copy the generated key first before saving.</strong><br>
                        It will <strong>not be shown again</strong> after you save.
                    </p>
                    <div class="flex gap-2 items-center mb-2">
                        <input id="swal-api-key" type="text" readonly
                            value="${token_hash}"
                            class="flex-1 px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-600 
                                    text-green-400 font-mono text-sm focus:outline-none cursor-text">
                        <button type="button" id="swal-copy-btn"
                                class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white 
                                    rounded-lg text-sm font-semibold transition whitespace-nowrap">
                            Copy Key
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">This is the only time you will see the plain key.</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'I have copied it — Save Key',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#6b7280',
            background: '#1f2937',
            color: '#f3f4f6',
            customClass: {
                popup: 'rounded-2xl border border-gray-700',
                title: 'text-lg font-semibold',
                htmlContainer: 'text-sm'
            },
            didOpen: () => {
                const copyBtn = document.getElementById('swal-copy-btn');
                const keyInput = document.getElementById('swal-api-key');

                copyBtn.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(token_hash);
                        copyBtn.textContent = 'Copied!';
                        copyBtn.classList.remove('bg-emerald-600', 'hover:bg-emerald-500');
                        copyBtn.classList.add('bg-green-700');
                    } catch (err) {
                        keyInput.select();
                        document.execCommand('copy');
                        copyBtn.textContent = 'Copied!';
                    }
                });
            }
        });

        // User cancelled
        if (!result.isConfirmed) {
            setApiStatus('Save cancelled.', 'warning');
            return;
        }

        // ===== Proceed to save =====
        setApiStatus('Saving key...', 'info');

        try {
            const response = await fetch('{{ route('api.keys.save') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ owner_label, token_hash })
            });

            const data = await response.json();

            if (data.success) {
                ownerLabelInput.value = '';
                generatedKeyInput.value = '';
                currentPlainKey = '';
                validateApiForm();
                setApiStatus('Key saved successfully!', 'success');

                Swal.fire({
                    icon: 'success',
                    title: 'Key Saved!',
                    text: 'The API key has been saved. Make sure you already copied it.',
                    background: '#1f2937',
                    color: '#f3f4f6',
                    confirmButtonColor: '#059669',
                    timer: 2000,
                    showConfirmButton: false
                });

                setTimeout(() => location.reload(), 1800);
            } else {
                setApiStatus(data.error || 'Operation failed', 'error');
            }
        } catch (err) {
            setApiStatus('Server error', 'error');
            console.error(err);
        }
    });


    document.querySelectorAll('.delete-key').forEach(btn => {
        btn.addEventListener('click', async function() {
            const token = this.dataset.token;
            const row = this.closest('tr');
            const owner = row.querySelector('td:first-child')?.textContent.trim() || token;
            
            // SweetAlert confirmation dialog
            const result = await Swal.fire({
                title: 'Delete API Key?',
                html: `Are you sure you want to delete the API key for <strong>"${owner}"</strong>?<br><span style="color: #ef4444;">This action cannot be undone!</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                background: '#1f2937',
                color: '#f3f4f6',
                iconColor: '#f59e0b'
            });

            if (!result.isConfirmed) return;

            try {
                const response = await fetch(`/api-keys/${encodeURIComponent(token)}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });
                
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new Error('Response is not JSON');
                }
                
                const data = await response.json();
                
                if (data.success) {
                    row.remove();
                    setApiStatus('Key deleted successfully!', 'success');
                    
                    // Success SweetAlert
                    await Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: `API key for "${owner}" has been deleted.`,
                        background: '#1f2937',
                        color: '#f3f4f6',
                        confirmButtonColor: '#059669',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    // Error SweetAlert
                    await Swal.fire({
                        icon: 'error',
                        title: 'Failed!',
                        text: data.error || 'Failed to delete the API key.',
                        background: '#1f2937',
                        color: '#f3f4f6',
                        confirmButtonColor: '#059669',
                        confirmButtonText: 'OK'
                    });
                    setApiStatus('Failed to delete key.', 'error');
                }
            } catch (err) {
                // Server error SweetAlert
                await Swal.fire({
                    icon: 'error',
                    title: 'Server Error',
                    text: 'An unexpected error occurred. Please try again later.',
                    background: '#1f2937',
                    color: '#f3f4f6',
                    confirmButtonColor: '#059669',
                    confirmButtonText: 'OK'
                });
                setApiStatus('Server error', 'error');
                console.error(err);
            }
        });
    });

    // ============ ALLOWED IP JAVASCRIPT ============
    const cidrInput = document.getElementById('cidr');
    const labelInput = document.getElementById('label');
    const enabledCheck = document.getElementById('enabled');
    const saveIpBtn = document.getElementById('saveIpBtn');
    const networkStatus = document.getElementById('networkStatus');
    const ipForm = document.getElementById('allowedIpForm');

    function setNetworkStatus(message, type = 'info') {
        networkStatus.className = 'mt-3 text-sm font-medium text-center min-h-[1.25rem]';
        switch (type) {
            case 'success': networkStatus.classList.add('text-munti-green-400'); break;
            case 'error':   networkStatus.classList.add('text-munti-red-400'); break;
            case 'info':    networkStatus.classList.add('text-radar-400'); break;
            case 'warning': networkStatus.classList.add('text-munti-yellow-400'); break;
            default:        networkStatus.classList.add('text-text-400');
        }
        networkStatus.textContent = message;
    }

    function validateIpForm() {
        saveIpBtn.disabled = !(cidrInput.value.trim() && labelInput.value.trim());
    }
    cidrInput.addEventListener('input', validateIpForm);
    labelInput.addEventListener('input', validateIpForm);
    validateIpForm();

    ipForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const cidr = cidrInput.value.trim();
        const label = labelInput.value.trim();
        const enabled = enabledCheck.checked;

        setNetworkStatus('Adding...', 'info');
        try {
            const response = await fetch('{{ route('allowed-networks.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ cidr, label, enabled })
            });
            const data = await response.json();
            if (data.success) {
                setNetworkStatus('Allowed IP added successfully!', 'success');
                cidrInput.value = '';
                labelInput.value = '';
                enabledCheck.checked = true;
                validateIpForm();
                setTimeout(() => location.reload(), 800);
            } else {
                setNetworkStatus(data.error || 'Operation failed', 'error');
            }
        } catch (err) {
            setNetworkStatus('Server error', 'error');
            console.error(err);
        }
    });

    document.querySelectorAll('.delete-ip').forEach(btn => {
        btn.addEventListener('click', async function() {
            const cidr = this.dataset.cidr;
            const row = this.closest('tr');
            const label = row.querySelector('td:nth-child(2)')?.textContent.trim() || cidr;
            
            // SweetAlert confirmation dialog
            const result = await Swal.fire({
                title: 'Delete Allowed IP?',
                html: `Are you sure you want to delete <strong>"${label}"</strong> (<code style="background: #374151; padding: 2px 6px; border-radius: 4px; color: #60a5fa;">${cidr}</code>)?<br><span style="color: #ef4444;">This action cannot be undone!</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                background: '#1f2937',
                color: '#f3f4f6',
                iconColor: '#f59e0b'
            });

            if (!result.isConfirmed) return;

            try {
                const response = await fetch('/allowed-networks', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        _method: 'DELETE',
                        cidr: cidr
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    row.remove();
                    setNetworkStatus('IP deleted successfully!', 'success');
                    
                    // Success SweetAlert
                    await Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: `Allowed IP "${label}" (${cidr}) has been removed.`,
                        background: '#1f2937',
                        color: '#f3f4f6',
                        confirmButtonColor: '#059669',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    // Error SweetAlert
                    await Swal.fire({
                        icon: 'error',
                        title: 'Failed!',
                        text: data.error || 'Failed to delete the IP address.',
                        background: '#1f2937',
                        color: '#f3f4f6',
                        confirmButtonColor: '#059669',
                        confirmButtonText: 'OK'
                    });
                    setNetworkStatus('Failed to delete IP.', 'error');
                }
            } catch (err) {
                // Server error SweetAlert
                await Swal.fire({
                    icon: 'error',
                    title: 'Server Error',
                    text: 'An unexpected error occurred. Please try again later.',
                    background: '#1f2937',
                    color: '#f3f4f6',
                    confirmButtonColor: '#059669',
                    confirmButtonText: 'OK'
                });
                setNetworkStatus('Server error', 'error');
                console.error(err);
            }
        });
    });

        // ============ MARK AS SEEN FUNCTIONALITY ============
    
    // Mark individual or selected logs as seen
    document.addEventListener('DOMContentLoaded', function() {
        // Select all checkbox
        const selectAll = document.getElementById('selectAllLogs');
        const checkboxes = document.querySelectorAll('.log-checkbox');
        
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        }
        
        // Mark selected as seen
        document.getElementById('markAllSeenBtn')?.addEventListener('click', async function() {
            const selectedIds = [];
            const checkboxes = document.querySelectorAll('.log-checkbox:checked');
            
            checkboxes.forEach(cb => {
                const id = cb.dataset.id;
                if (id) selectedIds.push(parseInt(id));
            });
            
            if (selectedIds.length === 0) {
                // If no checkboxes selected, mark all unseen logs as seen
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
                    }
                } catch (err) {
                    console.error(err);
                    await Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to mark logs as seen.',
                        background: '#1f2937',
                        color: '#f3f4f6',
                        confirmButtonColor: '#3b82f6'
                    });
                }
                return;
            }
            
            // Mark selected logs as seen
            try {
                const response = await fetch('{{ route("api-logs.mark-as-seen") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ ids: selectedIds })
                });
                
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
                }
            } catch (err) {
                console.error(err);
                await Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to mark logs as seen.',
                    background: '#1f2937',
                    color: '#f3f4f6',
                    confirmButtonColor: '#3b82f6'
                });
            }
        });
        
        // Individual checkbox - auto mark as seen when clicking on row?
        document.querySelectorAll('.log-row-unseen').forEach(row => {
            row.addEventListener('click', async function(e) {
                // Don't trigger if clicking checkbox or button
                if (e.target.closest('input[type="checkbox"]') || e.target.closest('button')) {
                    return;
                }
                
                const checkbox = this.querySelector('.log-checkbox');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    // Optionally auto-mark as seen when checked
                    // You can uncomment this if you want auto-mark when clicked
                    // checkbox.dispatchEvent(new Event('change'));
                }
            });
        });
        
        // Optional: Auto-mark as seen when checkbox is checked
        document.querySelectorAll('.log-checkbox').forEach(cb => {
            cb.addEventListener('change', async function() {
                if (this.checked && this.dataset.unseen === 'true') {
                    const id = parseInt(this.dataset.id);
                    try {
                        const response = await fetch('{{ route("api-logs.mark-as-seen") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({ ids: [id] })
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            // Update the row visually
                            const row = this.closest('tr');
                            if (row) {
                                row.classList.remove('log-row-unseen');
                                const seenCell = row.querySelector('td:last-child');
                                if (seenCell) {
                                    seenCell.innerHTML = `<span class="text-text-500">Just now</span>`;
                                }
                            }
                        }
                    } catch (err) {
                        console.error(err);
                    }
                }
            });
        });
    });
    
</script>

@include('layouts.footer')