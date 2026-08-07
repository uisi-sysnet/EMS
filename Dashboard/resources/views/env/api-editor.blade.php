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
                            <div>
                                <label for="ownerLabel" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">Owner Label</label>
                                <input type="text" id="ownerLabel" placeholder="e.g. Dashboard Client" required
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg
                                            bg-surface-900 text-text-100 placeholder-text-500
                                            focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                            </div>

                            <button type="submit" id="saveBtn"
                                    class="w-full px-4 py-2.5 bg-munti-green-600 hover:bg-munti-green-500 text-text-100 font-semibold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed border border-munti-green-500/30 flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Generate & Save API Key
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
                                            <th scope="col" class="px-4 py-3 text-left font-medium">Token Hash</th>
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
                                                <td class="px-4 py-2.5 font-mono text-xs text-text-400 truncate max-w-[140px]" title="{{ $key->token_hash }}">
                                                    {{ $key->token_hash }}
                                                </td>
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
    const saveBtn = document.getElementById('saveBtn');
    const apiStatus = document.getElementById('apiStatus');
    const apiForm = document.getElementById('apiKeyForm');

    // Modal elements (reuse existing modal)
    const keyModal = document.getElementById('keyModal');
    const modalKeyDisplay = document.getElementById('modalKeyDisplay');
    const modalCopyBtn = document.getElementById('modalCopyBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const modalCloseBtn = document.getElementById('modalCloseBtn');

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
        saveBtn.disabled = !ownerLabelInput.value.trim();
    }
    ownerLabelInput.addEventListener('input', validateApiForm);
    validateApiForm();

    apiForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const owner_label = ownerLabelInput.value.trim();
        if (!owner_label) return;

        setApiStatus('Generating and saving...', 'info');
        try {
            const response = await fetch('{{ route('api.keys.save') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ owner_label }) // no token_hash – server will generate
            });
            const data = await response.json();
            if (data.success) {
                // Show modal with the plain token
                if (data.plain_token) {
                    modalKeyDisplay.textContent = data.plain_token;
                    keyModal.classList.remove('hidden');
                    // Clear the input and reset status
                    ownerLabelInput.value = '';
                    validateApiForm();
                    setApiStatus('Key generated and saved! Check the popup.', 'success');
                } else {
                    setApiStatus('Key saved (no new token generated).', 'info');
                    // Optionally reload if you want to see updated table
                    setTimeout(() => location.reload(), 800);
                }
            } else {
                setApiStatus(data.error || 'Operation failed', 'error');
            }
        } catch (err) {
            setApiStatus('Server error', 'error');
            console.error(err);
        }
    });

    // Modal copy and close (same as before)
    modalCopyBtn.addEventListener('click', () => {
        const key = modalKeyDisplay.textContent;
        if (key) {
            navigator.clipboard.writeText(key).then(() => {
                setApiStatus('Key copied from modal!', 'success');
            }).catch(() => {
                // Fallback
                const range = document.createRange();
                range.selectNode(modalKeyDisplay);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
                document.execCommand('copy');
                setApiStatus('Key copied!', 'success');
            });
        }
    });

    function closeModal() {
        keyModal.classList.add('hidden');
        // Reload page to reflect new key in table
        location.reload();
    }
    closeModalBtn.addEventListener('click', closeModal);
    modalCloseBtn.addEventListener('click', closeModal);
    keyModal.addEventListener('click', (e) => {
        if (e.target === keyModal) closeModal();
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
                const response = await fetch(`/allowed-networks/${encodeURIComponent(cidr)}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
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


<!-- Modal Overlay -->
<div id="keyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm hidden transition-opacity">
    <div class="bg-surface-800 rounded-2xl border border-border-700 shadow-2xl max-w-lg w-full mx-4 p-6 transform transition-all scale-95">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-text-100">New API Key Generated</h3>
            <button type="button" id="closeModalBtn" class="text-text-400 hover:text-text-200 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <p class="text-text-300 text-sm mb-3">Copy this key now. It will not be shown again.</p>
        <div class="flex items-center gap-2 bg-surface-900 p-3 rounded-lg border border-border-600">
            <code id="modalKeyDisplay" class="flex-1 text-munti-green-400 font-mono text-sm break-all select-all"></code>
            <button type="button" id="modalCopyBtn" class="px-3 py-1.5 bg-radar-600 hover:bg-radar-500 text-text-100 rounded-lg text-sm font-semibold transition">
                Copy
            </button>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="button" id="modalCloseBtn" class="px-4 py-2 bg-surface-700 hover:bg-surface-600 text-text-100 rounded-lg transition text-sm">
                Close
            </button>
        </div>
    </div>
</div>

@include('layouts.footer')