{{-- resources/views/server/services.blade.php --}}
@include('layouts.header')
@include('layouts.topbar')

<div id="main-content"
     class="pt-20 pb-6 px-4 sm:px-6 max-w-7xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">
        {{-- Header --}}
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                <span class="leading-tight uppercase">Services</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400 sm:text-right">
                Status, control &amp; server terminal
            </span>
        </div>

        {{-- Scrollable body --}}
        <div class="flex-1 min-h-0 overflow-y-auto thin-scrollbar px-4 sm:px-6 py-4 sm:py-6 space-y-6">

    {{-- ========== SERVICE STATUS CARDS ========== --}}
    <div id="service-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($services as $svc)
            <div class="service-card bg-surface-800 border border-border-700 rounded-xl p-4" data-unit="{{ $svc['unit'] }}">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-text-100 truncate">{{ $svc['label'] }}</p>
                        <p class="text-xs text-text-500 truncate">{{ $svc['unit'] }}</p>
                    </div>
                    <span class="status-pill shrink-0 inline-flex items-center gap-1.5 text-xs font-medium px-2 py-1 rounded-full
                        {{ $svc['running']
                            ? 'bg-munti-green-700/20 text-munti-green-400 border border-munti-green-600/30'
                            : ($svc['active'] === 'failed'
                                ? 'bg-munti-red-700/20 text-munti-red-400 border border-munti-red-600/30'
                                : 'bg-surface-700 text-text-400 border border-border-600') }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $svc['running'] ? 'bg-munti-green-400' : ($svc['active'] === 'failed' ? 'bg-munti-red-400' : 'bg-text-500') }}"></span>
                        <span class="status-label">{{ ucfirst($svc['active']) }}</span>
                    </span>
                </div>

                <p class="text-xs text-text-500 mt-2">Boot: <span class="enabled-label">{{ ucfirst($svc['enabled']) }}</span></p>

                <div class="flex gap-2 mt-4">
                    <button type="button"
                            class="btn-action flex-1 text-xs font-semibold py-2 rounded-lg border border-munti-green-600/40 text-munti-green-400 hover:bg-munti-green-700/20 transition disabled:opacity-40 disabled:cursor-not-allowed"
                            data-action="start">Start</button>
                    <button type="button"
                            class="btn-action flex-1 text-xs font-semibold py-2 rounded-lg border border-munti-red-600/40 text-munti-red-400 hover:bg-munti-red-700/20 transition disabled:opacity-40 disabled:cursor-not-allowed"
                            data-action="stop">Stop</button>
                    <button type="button"
                            class="btn-action flex-1 text-xs font-semibold py-2 rounded-lg border border-border-600 text-text-300 hover:bg-surface-700 transition disabled:opacity-40 disabled:cursor-not-allowed"
                            data-action="restart">Restart</button>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ========== TERMINAL ========== --}}
    <div class="bg-surface-800 border border-border-700 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-border-700">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold text-text-100">Server Terminal</h2>
                <span id="term-status" class="text-xs px-2 py-0.5 rounded-full bg-surface-700 text-text-400 border border-border-600">Connecting…</span>
            </div>
            <button id="term-reconnect" type="button" class="text-xs text-text-400 hover:text-text-100 px-2 py-1 rounded hover:bg-surface-700 transition">Reconnect</button>
        </div>
        <div id="terminal" class="p-2 bg-black" style="height: 420px;"></div>
    </div>

        </div>{{-- /scrollable body --}}
    </div>{{-- /card --}}
</div>{{-- /main-content --}}

{{-- xterm.js --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/xterm/5.3.0/css/xterm.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xterm/5.3.0/lib/xterm.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xterm-addon-fit/0.8.0/lib/xterm-addon-fit.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        ?? '{{ csrf_token() }}';

    /* ---------------- Service status cards ---------------- */
    const cardsRoot = document.getElementById('service-cards');

    function pillClasses(svc) {
        if (svc.running) return { pill: 'bg-munti-green-700/20 text-munti-green-400 border border-munti-green-600/30', dot: 'bg-munti-green-400' };
        if (svc.active === 'failed') return { pill: 'bg-munti-red-700/20 text-munti-red-400 border border-munti-red-600/30', dot: 'bg-munti-red-400' };
        return { pill: 'bg-surface-700 text-text-400 border border-border-600', dot: 'bg-text-500' };
    }

    function applyStatus(card, svc) {
        const pill = card.querySelector('.status-pill');
        const dot = card.querySelector('.status-pill span');
        const label = card.querySelector('.status-label');
        const enabled = card.querySelector('.enabled-label');
        const cls = pillClasses(svc);

        pill.className = 'status-pill shrink-0 inline-flex items-center gap-1.5 text-xs font-medium px-2 py-1 rounded-full ' + cls.pill;
        dot.className = 'w-1.5 h-1.5 rounded-full ' + cls.dot;
        label.textContent = svc.active.charAt(0).toUpperCase() + svc.active.slice(1);
        enabled.textContent = svc.enabled.charAt(0).toUpperCase() + svc.enabled.slice(1);
    }

    async function refreshStatuses() {
        try {
            const res = await fetch('{{ route('services.status') }}', { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const list = await res.json();
            list.forEach(svc => {
                const card = cardsRoot.querySelector(`.service-card[data-unit="${svc.unit}"]`);
                if (card) applyStatus(card, svc);
            });
        } catch (e) { /* silent — next poll will retry */ }
    }

    cardsRoot.addEventListener('click', async function (e) {
        const btn = e.target.closest('.btn-action');
        if (!btn) return;
        const card = btn.closest('.service-card');
        const unit = card.dataset.unit;
        const action = btn.dataset.action;

        if (action === 'stop' || action === 'restart') {
            if (!confirm(`${action[0].toUpperCase() + action.slice(1)} ${unit}?`)) return;
        }

        const buttons = card.querySelectorAll('.btn-action');
        buttons.forEach(b => b.disabled = true);

        try {
            const res = await fetch(`/maintenance/services/${encodeURIComponent(unit)}/action`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ action }),
            });
            const data = await res.json();
            if (!res.ok) {
                alert(data.message || 'Action failed.');
            } else if (data.service) {
                applyStatus(card, data.service);
            }
        } catch (e) {
            alert('Network error while sending action.');
        } finally {
            buttons.forEach(b => b.disabled = false);
        }
    });

    refreshStatuses();
    setInterval(refreshStatuses, 5000);

    /* ---------------- Terminal ---------------- */
    const termStatus = document.getElementById('term-status');
    const term = new Terminal({
        convertEol: true,
        cursorBlink: true,
        fontSize: 13,
        theme: { background: '#000000' },
    });
    const fitAddon = new FitAddon.FitAddon();
    term.loadAddon(fitAddon);
    term.open(document.getElementById('terminal'));
    fitAddon.fit();
    window.addEventListener('resize', () => fitAddon.fit());

    let ws = null;

    function setTermStatus(text, tone) {
        const tones = {
            ok: 'bg-munti-green-700/20 text-munti-green-400 border border-munti-green-600/30',
            warn: 'bg-munti-yellow-700/20 text-munti-yellow-400 border border-munti-yellow-600/30',
            err: 'bg-munti-red-700/20 text-munti-red-400 border border-munti-red-600/30',
        };
        termStatus.className = 'text-xs px-2 py-0.5 rounded-full ' + (tones[tone] || 'bg-surface-700 text-text-400 border border-border-600');
        termStatus.textContent = text;
    }

    async function connectTerminal() {
        setTermStatus('Connecting…', 'warn');

        let token;
        try {
            const res = await fetch('{{ route('terminal.token') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            ({ token } = await res.json());
        } catch (e) {
            setTermStatus('Auth failed', 'err');
            return;
        }

        const wsUrl = @json(config('services.terminal_ws_url'));
        if (!wsUrl) {
            setTermStatus('Terminal not configured', 'err');
            term.writeln('\r\n\x1b[31mEMS_TERMINAL_WS_URL is not set.\x1b[0m');
            return;
        }

        ws = new WebSocket(wsUrl);

        ws.onopen = () => {
            ws.send(JSON.stringify({ type: 'auth', token, cols: term.cols, rows: term.rows }));
        };

        ws.onmessage = (evt) => {
            const msg = JSON.parse(evt.data);
            if (msg.type === 'ready') {
                setTermStatus('Connected', 'ok');
                term.focus();
            } else if (msg.type === 'data') {
                term.write(msg.data);
            } else if (msg.type === 'error') {
                setTermStatus('Error', 'err');
                term.writeln(`\r\n\x1b[31m${msg.message}\x1b[0m`);
            }
        };

        ws.onclose = () => setTermStatus('Disconnected', 'err');
        ws.onerror = () => setTermStatus('Connection error', 'err');

        term.onData((data) => {
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: 'input', data }));
            }
        });

        term.onResize(({ cols, rows }) => {
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: 'resize', cols, rows }));
            }
        });
    }

    document.getElementById('term-reconnect').addEventListener('click', () => {
        if (ws) ws.close();
        term.reset();
        connectTerminal();
    });

    connectTerminal();
});
</script>

@include('layouts.footer')