{{-- resources/views/server/terminal.blade.php --}}
@include('layouts.header')
@include('layouts.topbar')

<div id="main-content"
     class="pt-20 pb-6 px-4 sm:px-6 max-w-7xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">
        {{-- Header --}}
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                <span class="leading-tight uppercase">Server Terminal</span>
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('services.index') }}"
                   class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-border-600 text-text-300 hover:bg-surface-700 transition inline-flex items-center gap-1.5">
                    ← Back to Services
                </a>
            </div>
        </div>

        {{-- Body --}}
        <div class="flex-1 min-h-0 overflow-y-auto thin-scrollbar px-4 sm:px-6 py-4 sm:py-6">
            <div class="bg-surface-800 border border-border-700 rounded-xl overflow-hidden h-full flex flex-col">
                <div class="flex items-center justify-between px-4 py-3 border-b border-border-700">
                    <div class="flex items-center gap-2">
                        <span id="term-status" class="text-xs px-2 py-0.5 rounded-full bg-surface-700 text-text-400 border border-border-600">Connecting…</span>
                    </div>
                    <button id="term-reconnect" type="button" class="text-xs text-text-400 hover:text-text-100 px-2 py-1 rounded hover:bg-surface-700 transition">Reconnect</button>
                </div>
                <div id="terminal" class="p-2 bg-black flex-1 min-h-0"></div>
            </div>
        </div>
    </div>{{-- /card --}}
</div>{{-- /main-content --}}

{{-- xterm.js --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/xterm/5.3.0/css/xterm.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xterm/5.3.0/lib/xterm.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        ?? '{{ csrf_token() }}';

    const termStatus = document.getElementById('term-status');
    const term = new Terminal({
        convertEol: true,
        cursorBlink: true,
        fontSize: 13,
        theme: { background: '#000000' },
    });

    let fitAddon = null;
    try {
        if (window.FitAddon) {
            fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);
        }
    } catch (e) {
        console.error('FitAddon failed to load; terminal will not auto-resize.', e);
    }

    term.open(document.getElementById('terminal'));
    if (fitAddon) fitAddon.fit();
    window.addEventListener('resize', () => { if (fitAddon) fitAddon.fit(); });

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

    // Close the websocket cleanly if the tab is closed/navigated away.
    window.addEventListener('beforeunload', () => {
        if (ws) ws.close();
    });
});
</script>

@include('layouts.footer')