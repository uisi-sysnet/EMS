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
</style>

<div id="main-content" class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                <span class="leading-tight uppercase">Allowed Networks</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">Manage IP/CIDR whitelist</span>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-5 px-5 sm:px-8 space-y-8">

            <!-- Add new IP form -->
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

                    <button type="submit" id="saveBtn"
                            class="w-full px-4 py-2 bg-munti-green-600 hover:bg-munti-green-500 text-text-100 font-semibold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed border border-munti-green-500/30">
                        Add Allowed IP
                    </button>
                </form>
                <div id="status" class="mt-3 text-sm font-medium text-center"></div>
            </div>

            <!-- Existing IPs list -->
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
                                            {{ $ip->created_at->format('Y-m-d H:i') }}
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
    const cidrInput = document.getElementById('cidr');
    const labelInput = document.getElementById('label');
    const enabledCheck = document.getElementById('enabled');
    const saveBtn = document.getElementById('saveBtn');
    const status = document.getElementById('status');
    const form = document.getElementById('allowedIpForm');

    function setStatus(message, type = 'info') {
        status.className = 'mt-3 text-sm font-medium text-center';
        switch (type) {
            case 'success':
                status.classList.add('text-munti-green-400');
                break;
            case 'error':
                status.classList.add('text-munti-red-400');
                break;
            case 'info':
                status.classList.add('text-radar-400');
                break;
            case 'warning':
                status.classList.add('text-munti-yellow-400');
                break;
            default:
                status.classList.add('text-text-400');
        }
        status.textContent = message;
    }

    function validateForm() {
        const cidr = cidrInput.value.trim();
        const label = labelInput.value.trim();
        saveBtn.disabled = !(cidr && label);
    }

    cidrInput.addEventListener('input', validateForm);
    labelInput.addEventListener('input', validateForm);
    validateForm();

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const cidr = cidrInput.value.trim();
        const label = labelInput.value.trim();
        const enabled = enabledCheck.checked;

        setStatus('Adding...', 'info');
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
                setStatus('Allowed IP added successfully!', 'success');
                cidrInput.value = '';
                labelInput.value = '';
                enabledCheck.checked = true;
                validateForm();
                setTimeout(() => location.reload(), 800);
            } else {
                setStatus(data.error || 'Operation failed', 'error');
            }
        } catch (err) {
            setStatus('Server error', 'error');
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
                    setStatus('IP deleted successfully!', 'success');
                } else {
                    setStatus('Failed to delete IP.', 'error');
                }
            } catch (err) {
                setStatus('Server error', 'error');
                console.error(err);
            }
        });
    });
</script>

@include('layouts.footer')