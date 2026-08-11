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
            <div class="flex items-center gap-3">
                <span class="text-xs sm:text-sm text-text-400 sm:text-right">
                    Status &amp; control
                </span>
                <a href="{{ route('services.terminal') }}"
                   target="_blank" rel="noopener"
                   class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-border-600 text-text-300 hover:bg-surface-700 transition inline-flex items-center gap-1.5">
                    Open Terminal ↗
                </a>
            </div>
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

        </div>{{-- /scrollable body --}}
    </div>{{-- /card --}}
</div>{{-- /main-content --}}

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        ?? '{{ csrf_token() }}';

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

    const ACTION_META = {
        start:   { verb: 'Start',   icon: 'question', confirmColor: '#16a34a', desc: 'This will start the service.' },
        stop:    { verb: 'Stop',    icon: 'warning',  confirmColor: '#dc2626', desc: 'This will stop the service. Anything depending on it will be affected.' },
        restart: { verb: 'Restart', icon: 'warning',  confirmColor: '#dc2626', desc: 'This will briefly stop and start the service again.' },
    };

    async function confirmAction(unit, label, action) {
        const meta = ACTION_META[action];
        const result = await Swal.fire({
            title: `${meta.verb} "${label}"?`,
            html: `<span style="color:#9ca3af;">${meta.desc}</span>`,
            icon: meta.icon,
            showCancelButton: true,
            confirmButtonColor: meta.confirmColor,
            cancelButtonColor: '#6b7280',
            confirmButtonText: `Yes, ${meta.verb.toLowerCase()} it`,
            cancelButtonText: 'Cancel',
            background: '#1f2937',
            color: '#f3f4f6',
            iconColor: meta.icon === 'warning' ? '#f59e0b' : '#38bdf8',
        });
        return result.isConfirmed;
    }

    cardsRoot.addEventListener('click', async function (e) {
        const btn = e.target.closest('.btn-action');
        if (!btn) return;
        const card = btn.closest('.service-card');
        const unit = card.dataset.unit;
        const label = card.querySelector('.text-text-100')?.textContent?.trim() || unit;
        const action = btn.dataset.action;

        const confirmed = await confirmAction(unit, label, action);
        if (!confirmed) return;

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
                await Swal.fire({
                    title: 'Action Failed',
                    html: `<span style="color:#9ca3af;">${data.message || 'The action could not be completed.'}</span>`
                        + (data.detail ? `<pre style="text-align:left; white-space:pre-wrap; background:#111827; border:1px solid #374151; border-radius:8px; padding:10px; margin-top:12px; font-size:12px; color:#f87171; max-height:180px; overflow-y:auto;">${data.detail}</pre>` : ''),
                    icon: 'error',
                    confirmButtonColor: '#dc2626',
                    confirmButtonText: 'OK',
                    background: '#1f2937',
                    color: '#f3f4f6',
                    iconColor: '#ef4444',
                });
            } else {
                if (data.service) applyStatus(card, data.service);
                Swal.fire({
                    title: data.message || `${ACTION_META[action].verb} sent to ${unit}.`,
                    icon: 'success',
                    background: '#1f2937',
                    color: '#f3f4f6',
                    iconColor: '#22c55e',
                    timer: 2200,
                    timerProgressBar: true,
                    showConfirmButton: false,
                });
            }
        } catch (e) {
            Swal.fire({
                title: 'Network Error',
                text: 'Could not reach the server while sending the action.',
                icon: 'error',
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'OK',
                background: '#1f2937',
                color: '#f3f4f6',
                iconColor: '#ef4444',
            });
        } finally {
            buttons.forEach(b => b.disabled = false);
        }
    });

    refreshStatuses();
    setInterval(refreshStatuses, 5000);
});
</script>

@include('layouts.footer')