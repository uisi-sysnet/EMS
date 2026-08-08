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

    input.changed, select.changed {
        border-color: #FFB702 !important;
        background-color: rgba(255, 183, 2, 0.08) !important;
        box-shadow: 0 0 0 1px rgba(255, 183, 2, 0.25);
    }
</style>

<div id="main-content"
     class="pt-20 pb-6 px-4 sm:px-6 max-w-7xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">

    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                <span class="leading-tight uppercase">Network Configuration</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400 sm:text-right">
                Ethernet settings
            </span>
        </div>

        <!-- Form -->
        <div class="flex-1 p-5 sm:p-8 overflow-y-auto thin-scrollbar min-h-0 bg-background-900">
            <div id="form-container" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- populated by JavaScript -->
            </div>
        </div>

        <!-- Footer -->
        <div class="px-5 sm:px-8 py-4 sm:py-6 bg-surface-800 border-t border-border-800 flex flex-col-reverse sm:flex-row justify-between items-stretch sm:items-center gap-3">
            <button id="save"
                    class="w-full sm:w-auto px-6 sm:px-8 py-3 bg-radar-600 hover:bg-radar-500 text-text-100 font-semibold rounded-xl flex items-center justify-center gap-2 transition disabled:opacity-50 disabled:cursor-not-allowed border border-radar-500/40">
                Save Changes
            </button>
            <div id="status" class="text-sm font-medium text-center sm:text-right"></div>
        </div>
    </div>
</div>

<script>
    const container = document.getElementById('form-container');
    const status = document.getElementById('status');
    const saveBtn = document.getElementById('save');

    let originalValues = {}; // keyed by device name, e.g. { eth0: {...}, enp0s3: {...} }
    let deviceList = [];     // device names currently rendered, in display order

    function setStatus(message, type = 'info') {
        status.className = 'text-sm font-medium text-center sm:text-right';
        const types = {
            success: 'text-green-400',
            error: 'text-red-400',
            info: 'text-blue-400',
            warning: 'text-yellow-400',
            default: 'text-text-400'
        };
        status.classList.add(types[type] || types.default);
        status.textContent = message;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ----- Build form -----
    function buildForm(ethList) {
        deviceList = ethList.map(e => e.device);
        originalValues = {};
        ethList.forEach(eth => {
            originalValues[eth.device] = {
                renderer: eth.renderer || 'NetworkManager',
                dhcp4: eth.dhcp4 ?? true,
                address: eth.address || '',
                gateway: eth.gateway || '',
                nameservers: eth.nameservers || '',
            };
        });

        if (ethList.length === 0) {
            container.innerHTML = `<div class="text-text-400 text-sm lg:col-span-2">No Ethernet devices found on this system.</div>`;
            saveBtn.disabled = true;
            saveBtn.classList.add('opacity-50', 'cursor-not-allowed');
            setStatus("No devices to configure", "info");
            return;
        }

        const html = ethList.map(eth => {
            const d = escapeHtml(eth.device);
            return `
            <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden">
                <div class="px-4 py-3 border-b border-border-700 bg-surface-900/80 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-text-100 uppercase tracking-wide">Ethernet (${d})</h3>
                    <button class="restartEthBtn px-3 py-1 text-xs font-medium bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition disabled:opacity-50"
                            data-device="${d}">
                        Restart Connection
                    </button>
                </div>
                <div class="p-4 space-y-4">
                    <div>
                        <label for="eth_${d}_renderer" class="block text-sm font-medium text-text-300 mb-1">Renderer</label>
                        <select id="eth_${d}_renderer" class="w-full px-3 py-2 border border-border-600 rounded-lg focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm bg-surface-900 text-text-100 transition">
                            <option value="networkd" ${eth.renderer === 'networkd' ? 'selected' : ''}>networkd</option>
                            <option value="NetworkManager" ${eth.renderer === 'NetworkManager' ? 'selected' : ''}>NetworkManager</option>
                        </select>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="eth_${d}_dhcp4" ${eth.dhcp4 ? 'checked' : ''}
                               class="w-4 h-4 text-radar-600 bg-surface-900 border-border-600 rounded focus:ring-radar-500 focus:ring-2">
                        <label for="eth_${d}_dhcp4" class="ml-2 text-sm font-medium text-text-300">Enable DHCP</label>
                    </div>

                    <div id="eth_${d}_static_group" style="${eth.dhcp4 ? 'display: none;' : ''}">
                        <label for="eth_${d}_address" class="block text-sm font-medium text-text-300 mb-1">IP Address / Netmask</label>
                        <input type="text" id="eth_${d}_address" value="${escapeHtml(eth.address)}"
                               placeholder="e.g. 192.168.1.100/24"
                               class="w-full px-3 py-2 border border-border-600 rounded-lg focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm bg-surface-900 text-text-100 transition">
                    </div>

                    <div id="eth_${d}_gateway_group" style="${eth.dhcp4 ? 'display: none;' : ''}">
                        <label for="eth_${d}_gateway" class="block text-sm font-medium text-text-300 mb-1">Gateway</label>
                        <input type="text" id="eth_${d}_gateway" value="${escapeHtml(eth.gateway)}"
                               placeholder="e.g. 192.168.1.1"
                               class="w-full px-3 py-2 border border-border-600 rounded-lg focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm bg-surface-900 text-text-100 transition">
                    </div>

                    <div>
                        <label for="eth_${d}_nameservers" class="block text-sm font-medium text-text-300 mb-1">Nameservers (comma separated)</label>
                        <input type="text" id="eth_${d}_nameservers" value="${escapeHtml(eth.nameservers)}"
                               placeholder="e.g. 8.8.8.8, 8.8.4.4"
                               class="w-full px-3 py-2 border border-border-600 rounded-lg focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm bg-surface-900 text-text-100 transition">
                    </div>
                </div>
            </div>
        `;
        }).join('');

        container.innerHTML = html;

        // Restart buttons (one per device)
        document.querySelectorAll('.restartEthBtn').forEach(btn => {
            btn.addEventListener('click', async function () {
                const device = this.dataset.device;
                const originalText = this.textContent;
                this.disabled = true;
                this.textContent = 'Restarting...';
                setStatus(`Restarting ${device}...`, 'info');

                try {
                    const response = await fetch('{{ route('network.restart-eth') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ device })
                    });
                    const data = await response.json();
                    if (data.success) {
                        setStatus(`${device} restarted successfully`, 'success');
                    } else {
                        setStatus(data.error || 'Restart failed', 'error');
                    }
                } catch (err) {
                    setStatus('Server error', 'error');
                    console.error(err);
                } finally {
                    this.disabled = false;
                    this.textContent = originalText;
                }
            });
        });

        // DHCP toggle + change detection, per device
        deviceList.forEach(device => {
            const dhcpEl = document.getElementById(`eth_${device}_dhcp4`);
            dhcpEl.addEventListener('change', function() {
                document.getElementById(`eth_${device}_static_group`).style.display = this.checked ? 'none' : 'block';
                document.getElementById(`eth_${device}_gateway_group`).style.display = this.checked ? 'none' : 'block';
                updateSaveButtonState();
            });

            ['renderer', 'dhcp4', 'address', 'gateway', 'nameservers'].forEach(field => {
                const el = document.getElementById(`eth_${device}_${field}`);
                if (el) {
                    el.addEventListener('input', updateSaveButtonState);
                    el.addEventListener('change', updateSaveButtonState);
                }
            });
        });

        updateSaveButtonState();
    }

    function getCurrentValues() {
        const current = {};
        deviceList.forEach(device => {
            current[device] = {
                renderer: document.getElementById(`eth_${device}_renderer`)?.value || 'NetworkManager',
                dhcp4: document.getElementById(`eth_${device}_dhcp4`)?.checked || false,
                address: document.getElementById(`eth_${device}_address`)?.value || '',
                gateway: document.getElementById(`eth_${device}_gateway`)?.value || '',
                nameservers: document.getElementById(`eth_${device}_nameservers`)?.value || '',
            };
        });
        return current;
    }

    function getChangedDevices() {
        const current = getCurrentValues();
        return deviceList.filter(device => JSON.stringify(current[device]) !== JSON.stringify(originalValues[device]));
    }

    function hasChanges() {
        return getChangedDevices().length > 0;
    }

    function updateSaveButtonState() {
        if (deviceList.length === 0) return;

        const changed = hasChanges();
        saveBtn.disabled = !changed;
        if (changed) {
            saveBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            setStatus("Changes detected", "warning");
        } else {
            saveBtn.classList.add('opacity-50', 'cursor-not-allowed');
            setStatus("All changes saved", "success");
        }

        // Highlight changed fields
        const current = getCurrentValues();
        deviceList.forEach(device => {
            ['renderer', 'dhcp4', 'address', 'gateway', 'nameservers'].forEach(field => {
                const el = document.getElementById(`eth_${device}_${field}`);
                if (!el) return;
                const val = el.type === 'checkbox' ? el.checked : el.value;
                if (val !== originalValues[device][field]) {
                    el.classList.add('changed');
                } else {
                    el.classList.remove('changed');
                }
            });
        });
    }

    // ----- Load -----
    saveBtn.disabled = true;
    saveBtn.classList.add('opacity-50', 'cursor-not-allowed');
    setStatus("Loading configuration...", "info");
    fetch('{{ route('network.load') }}')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const ethList = data.eth || [];
                buildForm(ethList);
                if (ethList.length > 0) {
                    setStatus("Loaded successfully", "success");
                }
            } else {
                container.innerHTML = `<div class="text-red-400 text-sm">Failed to load network configuration: ${escapeHtml(data.error || 'unknown error')}</div>`;
                setStatus(data.error || "Failed to load configuration", "error");
                console.error('Network load failed:', data.error);
            }
        })
        .catch((err) => {
            container.innerHTML = `<div class="text-red-400 text-sm">Could not reach the server to load network configuration.</div>`;
            setStatus("Server error", "error");
            console.error(err);
        });

    // ----- Save -----
    saveBtn.addEventListener('click', async () => {
        const changedDevices = getChangedDevices();

        if (changedDevices.length === 0) {
            setStatus("No changes to save", "warning");
            return;
        }

        const current = getCurrentValues();
        const payload = {
            eth: changedDevices.map(device => ({ device, ...current[device] }))
        };

        setStatus("Saving...", "info");

        try {
            const response = await fetch('{{ route('network.save') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (data.success) {
                setStatus(data.message || "Saved successfully!", "success");
                originalValues = getCurrentValues();
                updateSaveButtonState();
            } else {
                setStatus(data.error || data.message || "Save failed", "error");
                console.error(data);
            }
        } catch (err) {
            setStatus("Server error", "error");
            console.error(err);
        }
    });


</script>

@include('layouts.footer')