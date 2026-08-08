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

<div id="main-content"
     class="pt-20 pb-6 px-4 sm:px-6 max-w-7xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">

    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                <span class="leading-tight uppercase">Maintenance</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400 sm:text-right">
                Network diagnostics
            </span>
        </div>

        <!-- Body -->
        <div class="flex-1 p-5 sm:p-8 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 flex flex-col gap-6">

            <!-- Controls -->
            <div class="bg-surface-800 rounded-xl border border-border-700 p-4 sm:p-5">
                <h3 class="text-sm font-bold text-text-100 uppercase tracking-wide mb-4">Ping / Traceroute</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="diag_device" class="block text-sm font-medium text-text-300 mb-1">Ethernet Interface</label>
                        <select id="diag_device"
                                class="w-full px-3 py-2 border border-border-600 rounded-lg focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm bg-surface-900 text-text-100 transition">
                            <option value="">Loading...</option>
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label for="diag_host" class="block text-sm font-medium text-text-300 mb-1">Host / IP Address</label>
                        <input type="text" id="diag_host" placeholder="e.g. 8.8.8.8 or google.com"
                               class="w-full px-3 py-2 border border-border-600 rounded-lg focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm bg-surface-900 text-text-100 transition">
                    </div>

                    <div>
                        <label for="diag_count" class="block text-sm font-medium text-text-300 mb-1">Ping Count</label>
                        <input type="number" id="diag_count" value="4" min="1" max="20"
                               class="w-full px-3 py-2 border border-border-600 rounded-lg focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm bg-surface-900 text-text-100 transition">
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 mt-4">
                    <button id="pingBtn"
                            class="px-6 py-3 bg-radar-600 hover:bg-radar-500 text-text-100 font-semibold rounded-xl flex items-center justify-center gap-2 transition disabled:opacity-50 disabled:cursor-not-allowed border border-radar-500/40">
                        Ping
                    </button>
                    <button id="tracerouteBtn"
                            class="px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl flex items-center justify-center gap-2 transition disabled:opacity-50 disabled:cursor-not-allowed border border-blue-500/40">
                        Traceroute
                    </button>
                    <button id="clearBtn"
                            class="px-6 py-3 bg-surface-700 hover:bg-surface-600 text-text-200 font-medium rounded-xl transition border border-border-600">
                        Clear Output
                    </button>
                </div>
            </div>

            <!-- Output -->
            <div class="bg-surface-800 rounded-xl border border-border-700 flex-1 flex flex-col min-h-0 overflow-hidden">
                <div class="px-4 py-3 border-b border-border-700 bg-surface-900/80 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-text-100 uppercase tracking-wide">Output</h3>
                    <span id="diag_status" class="text-xs font-medium text-text-400"></span>
                </div>
                <pre id="diag_output" class="flex-1 overflow-auto thin-scrollbar p-4 text-xs sm:text-sm text-green-400 font-mono whitespace-pre-wrap min-h-[200px]">Select an interface and enter a host to begin.</pre>
            </div>

        </div>
    </div>
</div>

<script>
    const deviceSelect = document.getElementById('diag_device');
    const hostInput = document.getElementById('diag_host');
    const countInput = document.getElementById('diag_count');
    const pingBtn = document.getElementById('pingBtn');
    const tracerouteBtn = document.getElementById('tracerouteBtn');
    const clearBtn = document.getElementById('clearBtn');
    const output = document.getElementById('diag_output');
    const diagStatus = document.getElementById('diag_status');

    function setDiagStatus(message, type = 'info') {
        const colors = {
            success: 'text-green-400',
            error: 'text-red-400',
            info: 'text-blue-400',
            warning: 'text-yellow-400',
        };
        diagStatus.className = 'text-xs font-medium ' + (colors[type] || 'text-text-400');
        diagStatus.textContent = message;
    }

    function setRunning(running) {
        pingBtn.disabled = running;
        tracerouteBtn.disabled = running;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    }

    // ----- Load interfaces -----
    fetch('{{ route('maintenance.interfaces') }}')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.devices && data.devices.length > 0) {
                deviceSelect.innerHTML = data.devices.map(d => `<option value="${d}">${d}</option>`).join('');
            } else {
                deviceSelect.innerHTML = `<option value="">No Ethernet devices found</option>`;
                pingBtn.disabled = true;
                tracerouteBtn.disabled = true;
            }
        })
        .catch(err => {
            deviceSelect.innerHTML = `<option value="">Failed to load interfaces</option>`;
            pingBtn.disabled = true;
            tracerouteBtn.disabled = true;
            console.error(err);
        });

    // ----- Run a diagnostic -----
    async function runDiagnostic(type) {
        const device = deviceSelect.value;
        const host = hostInput.value.trim();

        if (!device) {
            setDiagStatus('Select an interface first', 'warning');
            return;
        }
        if (!host) {
            setDiagStatus('Enter a host or IP address', 'warning');
            return;
        }

        const isPing = type === 'ping';
        const routeUrl = isPing ? '{{ route('maintenance.ping') }}' : '{{ route('maintenance.traceroute') }}';
        const payload = { device, host };
        if (isPing) {
            payload.count = parseInt(countInput.value, 10) || 4;
        }

        setRunning(true);
        setDiagStatus(isPing ? 'Pinging...' : 'Tracing route...', 'info');
        output.textContent = `$ ${isPing ? 'ping' : 'traceroute'} -I ${device} ${host}\n\nRunning...`;

        try {
            const response = await fetch(routeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken()
                },
                body: JSON.stringify(payload)
            });
            const data = await response.json();

            if (data.success) {
                output.textContent = `$ ${data.command}\n\n${data.output || '(no output)'}`;
                setDiagStatus('Done', 'success');
            } else {
                output.textContent = `$ ${data.command || (isPing ? 'ping' : 'traceroute')}\n\nError: ${data.error || 'Unknown error'}`;
                setDiagStatus(data.error || 'Failed', 'error');
            }
        } catch (err) {
            output.textContent += `\n\nServer error: could not reach the diagnostic endpoint.`;
            setDiagStatus('Server error', 'error');
            console.error(err);
        } finally {
            setRunning(false);
        }
    }

    pingBtn.addEventListener('click', () => runDiagnostic('ping'));
    tracerouteBtn.addEventListener('click', () => runDiagnostic('traceroute'));
    clearBtn.addEventListener('click', () => {
        output.textContent = 'Select an interface and enter a host to begin.';
        setDiagStatus('');
    });
</script>

@include('layouts.footer')