@include('layouts.header')
@include('layouts.topbar')

<style>
    .thin-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
    .thin-scrollbar::-webkit-scrollbar-track { background: #1A1A1A; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb { background: #4B5563; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb:hover { background: #6B7280; }
    .thin-scrollbar { scrollbar-width: thin; scrollbar-color: #4B5563 #1A1A1A; }
</style>

<div id="main-content" class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2.5">
                <span class="leading-tight uppercase tracking-wide">API & Allowed IP Settings</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">Manage API keys and allowed IP/Network whitelist</span>
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
                    <div class="flex-1 flex flex-col min-h-0">
                        <div class="px-5 py-3 border-b border-border-700 bg-surface-900/40">
                            <h4 class="text-xs font-semibold text-text-400 uppercase tracking-wider flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-munti-green-400"></span>
                                Active API Keys
                            </h4>
                        </div>

                        <div class="overflow-x-auto thin-scrollbar flex-1">
                            @if($keys->isNotEmpty())
                                <table class="min-w-full divide-y divide-border-700">
                                    <thead class="bg-surface-900/60 text-[11px] uppercase tracking-wider text-text-500">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left font-medium">Owner</th>
                                            {{-- <th scope="col" class="px-4 py-3 text-left font-medium">Token Hash</th> --}}
                                            <th scope="col" class="px-4 py-3 text-left font-medium">Status</th>
                                            {{-- <th scope="col" class="px-4 py-3 text-left font-medium">Created</th> --}}
                                            <th scope="col" class="px-4 py-3 text-right font-medium">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border-800">
                                        @foreach($keys as $key)
                                            <tr class="hover:bg-surface-700/50 transition">
                                                <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-munti-green-400">
                                                    {{ $key->owner_label }}
                                                </td>
                                                {{-- <td class="px-4 py-2.5 font-mono text-xs text-text-400 truncate max-w-[140px]" title="{{ $key->token_hash }}">
                                                    {{ $key->token_hash }}
                                                </td> --}}
                                                <td class="px-4 py-2.5 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border
                                                        {{ $key->enabled
                                                            ? 'bg-munti-green-700/15 text-munti-green-400 border-munti-green-600/30'
                                                            : 'bg-munti-red-700/15 text-munti-red-400 border-munti-red-600/30' }}">
                                                        {{ $key->enabled ? 'Enabled' : 'Disabled' }}
                                                    </span>
                                                </td>
                                                {{-- <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                                    {{ $key->created_at->format('Y-m-d h:i A') }}
                                                </td> --}}
                                                <td class="px-4 py-2.5 whitespace-nowrap text-right">
                                                    <button type="button"
                                                            class="delete-key text-munti-red-400 hover:text-munti-red-300 transition p-1.5 rounded-lg hover:bg-munti-red-700/20"
                                                            data-token="{{ $key->token_hash }}"
                                                            title="Delete key">
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
                                    No API keys yet
                                </div>
                            @endif
                        </div>
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
            if (!confirm(`Delete API key for "${owner}"? This cannot be undone.`)) return;

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
                } else {
                    setApiStatus('Failed to delete key.', 'error');
                }
            } catch (err) {
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
            if (!confirm(`Delete allowed IP "${label}" (${cidr})? This cannot be undone.`)) return;

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
                } else {
                    setNetworkStatus('Failed to delete IP.', 'error');
                }
            } catch (err) {
                setNetworkStatus('Server error', 'error');
                console.error(err);
            }
        });
    });
</script>

@include('layouts.footer')