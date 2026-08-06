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
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                <span class="leading-tight uppercase">API & Network Settings</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">Manage API keys and allowed IP/CIDR whitelist</span>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-5 px-5 sm:px-8 space-y-8">

            <!-- ========== SECTION 1: API KEYS ========== -->
            <div class="bg-surface-800 rounded-xl border border-border-700 p-5 sm:p-6">
                <h3 class="text-sm font-semibold text-text-200 mb-4 uppercase tracking-wide">Generate New API Key</h3>
                <form id="apiKeyForm" class="space-y-4">
                    <div>
                        <label for="ownerLabel" class="block text-sm font-medium text-text-300 mb-1.5">Owner Label</label>
                        <input type="text" id="ownerLabel" placeholder="Enter owner label" required
                               class="w-full px-4 py-2.5 border border-border-600 rounded-lg
                                      bg-surface-900 text-text-100 placeholder-text-500
                                      focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm transition">
                    </div>
                    <button type="submit" id="saveBtn"
                            class="w-full px-4 py-2 bg-munti-green-600 hover:bg-munti-green-500 text-text-100 font-semibold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed border border-munti-green-500/30">
                        Generate & Save API Key
                    </button>
                </form>
                <div id="apiStatus" class="mt-3 text-sm font-medium text-center"></div>
            </div>

            @if($keys->isNotEmpty())
                <div>
                    <h3 class="text-sm font-semibold text-text-200 mb-3 uppercase tracking-wide">Active API Keys</h3>
                    <div class="bg-surface-800 rounded-xl border border-border-700 overflow-x-auto thin-scrollbar">
                        <table class="min-w-full divide-y divide-border-700">
                            <thead class="bg-surface-900/80 text-xs uppercase tracking-wider text-text-400">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Owner Label</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Token Hash</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Status</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Created</th>
                                    <th scope="col" class="px-4 py-3 text-right font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-800">
                                @foreach($keys as $key)
                                    <tr class="hover:bg-surface-700/60 transition">
                                        <td class="px-4 py-1 whitespace-nowrap font-mono text-sm text-munti-green-400">
                                            {{ $key->owner_label }}
                                        </td>
                                        <td class="px-4 py-1 font-mono text-sm text-text-400 truncate max-w-xs">
                                            {{ $key->token_hash }}
                                        </td>
                                        <td class="px-4 py-1 whitespace-nowrap">
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium border
                                                {{ $key->enabled
                                                    ? 'bg-munti-green-700/20 text-munti-green-400 border-munti-green-600/30'
                                                    : 'bg-munti-red-700/20 text-munti-red-400 border-munti-red-600/30' }}">
                                                {{ $key->enabled ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-1 whitespace-nowrap text-xs text-text-500">
                                            {{ $key->created_at->format('Y-m-d h:i A') }}
                                        </td>
                                        <td class="px-4 py-1 whitespace-nowrap text-right">
                                            <button type="button"
                                                    class="delete-key text-munti-red-400 hover:text-munti-red-300 transition p-1 rounded hover:bg-munti-red-700/20"
                                                    data-token="{{ $key->token_hash }}"
                                                    title="Delete key">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="1.35em" height="1.35em" viewBox="0 0 24 24">
                                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- ========== SECTION 2: ALLOWED NETWORKS ========== -->
            <div class="bg-surface-800 rounded-xl border border-border-700 p-5 sm:p-6">
                <h3 class="text-sm font-semibold text-text-200 mb-4 uppercase tracking-wide">Add Allowed IP/CIDR</h3>
                <form id="allowedIpForm" class="space-y-4">
                    <div>
                        <label for="cidr" class="block text-sm font-medium text-text-300 mb-1.5">CIDR</label>
                        <input type="text" id="cidr" placeholder="e.g. 192.168.1.0/24" required
                               class="w-full px-4 py-2.5 border border-border-600 rounded-lg
                                      bg-surface-900 text-text-100 placeholder-text-500
                                      focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm transition">
                    </div>
                    <div>
                        <label for="label" class="block text-sm font-medium text-text-300 mb-1.5">Label</label>
                        <input type="text" id="label" placeholder="e.g. Office Network" required
                               class="w-full px-4 py-2.5 border border-border-600 rounded-lg
                                      bg-surface-900 text-text-100 placeholder-text-500
                                      focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm transition">
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="enabled" checked
                               class="h-4 w-4 text-munti-green-600 focus:ring-munti-green-500 border-border-600 rounded bg-surface-900">
                        <label for="enabled" class="ml-2 text-sm text-text-300">Enabled</label>
                    </div>

                    <button type="submit" id="saveIpBtn"
                            class="w-full px-4 py-2 bg-munti-green-600 hover:bg-munti-green-500 text-text-100 font-semibold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed border border-munti-green-500/30">
                        Add Allowed IP
                    </button>
                </form>
                <div id="networkStatus" class="mt-3 text-sm font-medium text-center"></div>
            </div>

            @if($ips->isNotEmpty())
                <div>
                    <h3 class="text-sm font-semibold text-text-200 mb-3 uppercase tracking-wide">Current Allowed IPs</h3>
                    <div class="bg-surface-800 rounded-xl border border-border-700 overflow-x-auto thin-scrollbar">
                        <table class="min-w-full divide-y divide-border-700">
                            <thead class="bg-surface-900/80 text-xs uppercase tracking-wider text-text-400">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">CIDR</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Label</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Status</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Created</th>
                                    <th scope="col" class="px-4 py-3 text-right font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-800">
                                @foreach($ips as $ip)
                                    <tr class="hover:bg-surface-700/60 transition">
                                        <td class="px-4 py-1 whitespace-nowrap font-mono text-sm text-munti-green-400">
                                            {{ $ip->cidr }}
                                        </td>
                                        <td class="px-4 py-1 text-sm text-text-200">
                                            {{ $ip->label }}
                                        </td>
                                        <td class="px-4 py-1 whitespace-nowrap">
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium border
                                                {{ $ip->enabled
                                                    ? 'bg-munti-green-700/20 text-munti-green-400 border-munti-green-600/30'
                                                    : 'bg-munti-red-700/20 text-munti-red-400 border-munti-red-600/30' }}">
                                                {{ $ip->enabled ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-1 whitespace-nowrap text-xs text-text-500">
                                            {{ \Carbon\Carbon::parse($ip->created_at)->format('Y-m-d h:i A') }}
                                        </td>
                                        <td class="px-4 py-1 whitespace-nowrap text-right">
                                            <button type="button"
                                                    class="delete-ip text-munti-red-400 hover:text-munti-red-300 transition p-1 rounded hover:bg-munti-red-700/20"
                                                    data-cidr="{{ $ip->cidr }}"
                                                    title="Delete IP">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="1.35em" height="1.35em" viewBox="0 0 24 24">
                                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>

<script>
    // ============ API KEY JAVASCRIPT ============
    const ownerLabelInput = document.getElementById('ownerLabel');
    const saveBtn = document.getElementById('saveBtn');
    const apiStatus = document.getElementById('apiStatus');
    const apiForm = document.getElementById('apiKeyForm');

    function setApiStatus(message, type = 'info') {
        apiStatus.className = 'mt-3 text-sm font-medium text-center';
        switch (type) {
            case 'success': apiStatus.classList.add('text-munti-green-400'); break;
            case 'error':   apiStatus.classList.add('text-munti-red-400'); break;
            case 'info':    apiStatus.classList.add('text-radar-400'); break;
            case 'warning': apiStatus.classList.add('text-munti-yellow-400'); break;
            default:        apiStatus.classList.add('text-text-400');
        }
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
                body: JSON.stringify({ owner_label })
            });
            const data = await response.json();
            if (data.success) {
                setApiStatus('API key generated and saved successfully!', 'success');
                ownerLabelInput.value = '';
                validateApiForm();
                setTimeout(() => location.reload(), 800);
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
            const ownerLabel = row.querySelector('td:first-child')?.textContent.trim() || 'this key';
            if (!confirm(`Delete API key "${ownerLabel}"? This cannot be undone.`)) return;
            try {
                const response = await fetch(`/api-keys/${token}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    row.remove();
                    const tbody = document.querySelector('tbody');
                    if (tbody && tbody.querySelectorAll('tr').length === 0) {
                        const container = tbody.closest('.mb-8') || tbody.closest('div');
                        if (container) container.remove();
                    }
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
        networkStatus.className = 'mt-3 text-sm font-medium text-center';
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
                    const tbody = document.querySelector('tbody');
                    if (tbody && tbody.querySelectorAll('tr').length === 0) {
                        const container = tbody.closest('.mb-8') || tbody.closest('div');
                        if (container) container.remove();
                    }
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