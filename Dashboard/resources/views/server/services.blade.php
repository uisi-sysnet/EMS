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

                    @if($svc['isSms'] ?? false)
                        <p class="text-xs text-text-500 mt-2">SMS Ingestion: <span class="font-semibold {{ $svc['running'] ? 'text-munti-green-400' : 'text-munti-red-400' }}">{{ $svc['running'] ? 'True' : 'False' }}</span></p>
                    @else
                        <p class="text-xs text-text-500 mt-2">Boot: <span class="enabled-label">{{ ucfirst($svc['enabled']) }}</span></p>
                    @endif

                    @if($svc['isSms'] ?? false)
                        
                        {{-- SMS-specific enable/disable buttons --}}
                        <div class="flex gap-2 mt-4">
                            <button type="button" 
                                    class="btn-sms-toggle flex-1 text-xs font-semibold py-2 rounded-lg border border-munti-green-600/40 text-munti-green-400 hover:bg-munti-green-700/20 transition disabled:opacity-40 disabled:cursor-not-allowed 
                                        {{ $svc['running'] ? 'bg-munti-green-700/10' : '' }}"
                                    data-action="enable"
                                    data-unit="{{ $svc['unit'] }}"
                                    data-label="{{ $svc['label'] }}"
                                    {{ $svc['running'] ? 'disabled' : '' }}>
                                Enable
                            </button>
                            <button type="button" 
                                    class="btn-sms-toggle flex-1 text-xs font-semibold py-2 rounded-lg border border-munti-red-600/40 text-munti-red-400 hover:bg-munti-red-700/20 transition disabled:opacity-40 disabled:cursor-not-allowed
                                        {{ !$svc['running'] ? 'bg-munti-red-700/10' : '' }}"
                                    data-action="disable"
                                    data-unit="{{ $svc['unit'] }}"
                                    data-label="{{ $svc['label'] }}"
                                    {{ !$svc['running'] ? 'disabled' : '' }}>
                                Disable
                            </button>
                        </div>
                    @else
                        {{-- Regular start/stop/restart buttons --}}
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
                    @endif

                    @if($svc['hasConfig'] ?? false)
                        <button type="button"
                                class="btn-edit-config w-full mt-2 text-xs font-semibold py-2 rounded-lg border border-border-600 text-text-300 hover:bg-surface-700 transition inline-flex items-center justify-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit Config
                        </button>
                    @endif
                </div>
            @endforeach
        </div>

    {{-- ========== CONFIG EDIT MODAL ========== --}}
    <div id="configModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-surface-800 rounded-2xl border border-border-700 shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col">
            <div class="px-6 py-4 border-b border-border-700 flex items-center justify-between gap-3">
                <h3 class="text-lg font-semibold text-text-100 flex items-center gap-2 min-w-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-radar-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span>Edit Config</span>
                    <span id="configPathLabel" class="text-xs font-normal text-text-500 font-mono truncate"></span>
                </h3>
                <button type="button" onclick="closeConfigModal()" class="p-2 rounded-lg hover:bg-surface-700 text-text-400 hover:text-text-100 transition shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="px-6 py-4 flex-1 min-h-0 overflow-y-auto thin-scrollbar flex flex-col gap-3">
                <div id="configError" class="hidden px-4 py-3 rounded-lg border border-munti-red-600/30 bg-munti-red-700/15 text-munti-red-400 text-xs font-mono whitespace-pre-wrap max-h-56 overflow-y-auto"></div>
                <textarea id="configTextarea" spellcheck="false" autocomplete="off"
                    class="w-full flex-1 min-h-[420px] px-4 py-3 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-xs font-mono leading-relaxed resize-none"></textarea>
            </div>

            <div class="px-6 py-4 border-t border-border-700 flex items-center justify-between gap-3">
                <span id="configHint" class="text-xs text-text-500"></span>
                <div class="flex gap-3">
                    <button type="button" onclick="closeConfigModal()"
                            class="px-4 py-2.5 text-sm font-medium text-text-300 hover:text-text-100 bg-surface-700 hover:bg-surface-600 rounded-lg transition border border-border-600">
                        Cancel
                    </button>
                    <button type="button" id="configSaveBtn" onclick="saveConfig()"
                            class="px-6 py-2.5 bg-radar-500 hover:bg-radar-400 text-text-100 font-semibold rounded-lg transition border border-radar-400/30 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save, Test &amp; Restart
                    </button>
                </div>
            </div>
        </div>
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
        
        // Update SMS status display if it exists
        const smsStatusSpan = card.querySelector('.sms-status-text');
        if (smsStatusSpan) {
            smsStatusSpan.textContent = svc.running ? 'ACTIVE' : 'INACTIVE';
            smsStatusSpan.className = 'text-xs font-semibold ' + (svc.running ? 'text-munti-green-400' : 'text-munti-red-400');
        }
        
        // Update SMS button states
        const enableBtn = card.querySelector('.btn-sms-toggle[data-action="enable"]');
        const disableBtn = card.querySelector('.btn-sms-toggle[data-action="disable"]');
        if (enableBtn && disableBtn) {
            enableBtn.disabled = svc.running;
            disableBtn.disabled = !svc.running;
            // Update visual states
            enableBtn.className = enableBtn.className.replace(/bg-\w+-\d+\/\d+/g, '').trim() + (svc.running ? ' bg-munti-green-700/10' : '');
            disableBtn.className = disableBtn.className.replace(/bg-\w+-\d+\/\d+/g, '').trim() + (!svc.running ? ' bg-munti-red-700/10' : '');
        }
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

    cardsRoot.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.btn-edit-config');
        if (!editBtn) return;
        const card = editBtn.closest('.service-card');
        openConfigModal(card.dataset.unit);
    });

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

    // ---------- SMS Enable/Disable ----------
    const SMS_ACTION_META = {
        enable: { 
            verb: 'Enable', 
            desc: 'This will enable the SMS service to start automatically on boot.', 
            confirmColor: '#16a34a',
            icon: 'question'
        },
        disable: { 
            verb: 'Disable', 
            desc: 'This will prevent the SMS service from starting automatically on boot.', 
            confirmColor: '#dc2626',
            icon: 'warning'
        },
    };

    cardsRoot.addEventListener('click', async function (e) {
        const btn = e.target.closest('.btn-sms-toggle');
        if (!btn) return;
        
        const unit = btn.dataset.unit || btn.closest('.service-card')?.dataset.unit;
        const label = btn.dataset.label || 'SMS Service';
        const action = btn.dataset.action; // 'enable' or 'disable'
        const meta = SMS_ACTION_META[action];
        
        // Confirmation dialog
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
        
        if (!result.isConfirmed) return;
        
        // Disable both SMS toggle buttons during request
        const card = btn.closest('.service-card');
        const smsButtons = card.querySelectorAll('.btn-sms-toggle');
        smsButtons.forEach(b => b.disabled = true);
        
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
                    title: data.message || `${meta.verb} action completed.`,
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
            smsButtons.forEach(b => b.disabled = false);
        }
    });

    // ---------- Config edit modal ----------
    let currentConfigService = null;
    const configModal = document.getElementById('configModal');
    const configTextarea = document.getElementById('configTextarea');
    const configError = document.getElementById('configError');
    const configPathLabel = document.getElementById('configPathLabel');
    const configHint = document.getElementById('configHint');
    const configSaveBtn = document.getElementById('configSaveBtn');

    function showConfigError(message) {
        configError.textContent = message;
        configError.classList.remove('hidden');
    }

    function clearConfigError() {
        configError.textContent = '';
        configError.classList.add('hidden');
    }

    function openConfigModal(unit) {
        currentConfigService = unit;
        clearConfigError();
        configPathLabel.textContent = '';
        configHint.textContent = 'Loading current config…';
        configTextarea.value = '';
        configTextarea.disabled = true;

        configModal.classList.remove('hidden');
        configModal.classList.add('flex');

        fetch(`/maintenance/services/${encodeURIComponent(unit)}/config`, {
            headers: { 'Accept': 'application/json' },
        })
            .then(async res => ({ ok: res.ok, data: await res.json() }))
            .then(({ ok, data }) => {
                if (!ok) {
                    showConfigError(data.message || 'Failed to load config.');
                    configHint.textContent = '';
                    return;
                }
                configTextarea.value = data.content;
                configPathLabel.textContent = data.path;
                configHint.textContent = '';
            })
            .catch(() => {
                showConfigError('Network error while loading the config file.');
                configHint.textContent = '';
            })
            .finally(() => { configTextarea.disabled = false; });
    }

    function closeConfigModal() {
        configModal.classList.add('hidden');
        configModal.classList.remove('flex');
        currentConfigService = null;
    }
    window.closeConfigModal = closeConfigModal;

    async function saveConfig() {
        if (!currentConfigService) return;

        clearConfigError();
        configSaveBtn.disabled = true;
        const originalLabel = configSaveBtn.innerHTML;
        configSaveBtn.textContent = 'Testing & saving…';
        configHint.textContent = 'Writing file, running nginx -t, restarting…';

        try {
            const res = await fetch(`/maintenance/services/${encodeURIComponent(currentConfigService)}/config`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ content: configTextarea.value }),
            });
            const data = await res.json();

            if (!res.ok) {
                // Test (or write/backup) failed — keep the modal open so
                // the user can fix the text and retry, per the error shown.
                showConfigError((data.message || 'Save failed.') + (data.detail ? '\n\n' + data.detail : ''));
                configHint.textContent = '';
                return;
            }

            const card = cardsRoot.querySelector(`.service-card[data-unit="${currentConfigService}"]`);
            if (card && data.service) applyStatus(card, data.service);

            closeConfigModal();
            Swal.fire({
                title: data.message || 'Config saved and nginx restarted.',
                icon: 'success',
                background: '#1f2937',
                color: '#f3f4f6',
                iconColor: '#22c55e',
                timer: 2800,
                timerProgressBar: true,
                showConfirmButton: false,
            });
        } catch (e) {
            showConfigError('Network error while saving the config file.');
            configHint.textContent = '';
        } finally {
            configSaveBtn.disabled = false;
            configSaveBtn.innerHTML = originalLabel;
        }
    }
    window.saveConfig = saveConfig;

    configModal.addEventListener('click', function (e) {
        if (e.target === this) closeConfigModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !configModal.classList.contains('hidden')) closeConfigModal();
    });

    refreshStatuses();
    setInterval(refreshStatuses, 5000);
});
</script>

@include('layouts.footer')