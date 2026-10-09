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

    input.changed {
        border-color: #FFB702 !important;
        background-color: rgba(255, 183, 2, 0.08) !important;
        box-shadow: 0 0 0 1px rgba(255, 183, 2, 0.25);
    }

    .label-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.8rem;
    }
</style>

<div id="main-content"
     class="pt-20 pb-6 px-4 sm:px-6 max-w-6xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">

    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                <span class="leading-tight uppercase">MQTT Settings</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400 sm:text-right">
                Only values are editable
            </span>
        </div>

        <!-- Form -->
        <div class="flex-1 p-5 sm:p-8 overflow-y-auto thin-scrollbar min-h-0 bg-background-900">
            <div id="form-container" class="space-y-6">
                <!-- populated by JavaScript -->
            </div>

            <!-- Live MQTT messages: listen to the broker for a few seconds -->
            <div class="mt-6 bg-surface-800 rounded-xl border border-border-700 overflow-hidden">
                <div class="px-4 py-3 border-b border-border-700 bg-surface-900/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                    <h3 class="text-sm font-bold text-text-100 uppercase tracking-wide">Live MQTT Messages</h3>
                    <span class="text-xs text-text-500">Listens with the saved broker settings. Read-only, nothing is published.</span>
                </div>
                <div class="p-4 space-y-3">
                    <div class="flex flex-col sm:flex-row gap-2">
                        <div class="flex-1 min-w-0">
                            <label for="peek-topic" class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Topic</label>
                            <input type="text" id="peek-topic" value="#" maxlength="200" spellcheck="false"
                                   class="w-full px-3 py-2 border border-border-600 rounded-lg bg-surface-900 text-text-100 text-sm font-mono focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 transition">
                            <p class="text-[11px] text-text-500 mt-1"><span class="font-mono">+</span> matches one level, <span class="font-mono">#</span> matches everything below.</p>
                        </div>
                        <div class="sm:w-32">
                            <label for="peek-seconds" class="block text-[10px] font-medium text-text-400 mb-1 uppercase tracking-wider">Listen for</label>
                            <select id="peek-seconds" class="w-full px-3 py-2 border border-border-600 rounded-lg bg-surface-900 text-text-100 text-sm">
                                <option value="5">5 seconds</option>
                                <option value="10" selected>10 seconds</option>
                                <option value="30">30 seconds</option>
                            </select>
                        </div>
                        <div class="sm:self-start sm:pt-5">
                            <button id="peek-btn" type="button"
                                    class="w-full sm:w-auto px-5 py-2 bg-radar-600 hover:bg-radar-500 text-text-100 text-sm font-semibold rounded-lg transition border border-radar-500/40 disabled:opacity-50 disabled:cursor-wait">
                                Listen
                            </button>
                        </div>
                    </div>
                    <div id="peek-status" class="text-sm text-text-400"></div>
                    <div id="peek-messages" class="space-y-2"></div>
                </div>
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

    let lineOrder = [];
    let originalValues = {};

    function setStatus(message, type = 'info') {
        status.className = 'text-sm font-medium text-center sm:text-right';
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

    function buildForm(blockText) {
        const lines = blockText.split('\n');
        lineOrder = [];

        const sections = [];
        let currentSection = null;

        // Display label mappings for MQTT settings
        const displayLabels = {
            'MQTT_BROKER_HOST': 'Host',
            'MQTT_BROKER_PORT': 'Port',
            'MQTT_TIMEOUT_SEC': 'Timeout (sec)',
            'MQTT_TOPIC': 'Topic',
            'MQTT_USER': 'Username',
            'MQTT_PASSWORD': 'Password'
        };

        lines.forEach(line => {
            const trimmed = line.trim();

            if (trimmed.startsWith('#')) {
                if (currentSection) sections.push(currentSection);

                let displayText = trimmed
                    .replace(/^#\s*----\s*/, '')
                    .replace(/\s*----\s*$/, '')
                    .trim();

                // Clean up the display title
                displayText = displayText.replace(/^#\s*/, '').trim();
                if (displayText.includes('MQTT (SEISMIC)')) {
                    displayText = 'MQTT (Seismic)';
                }

                currentSection = {
                    title: displayText,
                    originalComment: line,
                    rows: []
                };
                lineOrder.push({ type: 'comment', text: line });
            } else if (trimmed.includes('=')) {
                const [key, ...valueParts] = line.split('=');
                const cleanKey = key.trim();
                const cleanValue = valueParts.join('=').trim();

                if (!currentSection) {
                    currentSection = { title: 'MQTT Settings', originalComment: null, rows: [] };
                }

                currentSection.rows.push({ key: cleanKey, value: cleanValue });
                lineOrder.push({ type: 'variable', key: cleanKey });
            }
        });
        if (currentSection) sections.push(currentSection);

        let html = '';

        if (sections.length >= 2) {
            html += `<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">`;
            sections.forEach(sec => {
                html += renderSection(sec, displayLabels);
            });
            html += `</div>`;
        } else {
            sections.forEach(sec => {
                html += renderSection(sec, displayLabels);
            });
        }

        container.innerHTML = html;

        container.querySelectorAll('[data-toggle-secret]').forEach(btn => {
            btn.addEventListener('click', () => {
                const field = document.getElementById(btn.dataset.toggleSecret);
                const show = field.type === 'password';
                field.type = show ? 'text' : 'password';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                btn.title = show ? 'Hide password' : 'Show password';
                btn.classList.toggle('text-radar-400', show);
            });
        });

        originalValues = {};
        lineOrder.forEach(item => {
            if (item.type === 'variable') {
                const input = document.getElementById(item.key);
                if (input) {
                    originalValues[item.key] = input.value;
                    input.addEventListener('input', onInputChange);
                }
            }
        });
        updateSaveButtonState();
    }

    function renderSection(sec, displayLabels = {}) {
        let html = `
            <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col">
                <div class="px-4 py-3 border-b border-border-700 bg-surface-900/80">
                    <h3 class="text-sm font-bold text-text-100 uppercase tracking-wide">${escapeHtml(sec.title)}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <tbody>
        `;

        sec.rows.forEach((row, idx) => {
            const isLast = idx === sec.rows.length - 1;
            const displayLabel = displayLabels[row.key] || row.key;
            // Passwords are masked; the eye button shows/hides them.
            const secret = /PASSWORD|SECRET|TOKEN/i.test(row.key);
            const input = `
                        <input type="${secret ? 'password' : 'text'}"
                            id="${row.key}"
                            value="${escapeHtml(row.value)}"
                            ${secret ? 'autocomplete="new-password" spellcheck="false"' : ''}
                            class="w-full px-3 py-2 ${secret ? 'pr-11' : ''} border border-border-600 rounded-lg
                                focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500
                                text-sm bg-surface-900 text-text-100 placeholder-text-500 transition">`;
            html += `
                <tr class="${isLast ? '' : 'border-b border-border-800'} hover:bg-surface-700/60 transition">
                    <td class="px-4 py-3 font-medium text-text-300 whitespace-nowrap w-40 md:w-48 align-middle">
                        <label for="${row.key}" class="label-mono">${escapeHtml(displayLabel)}</label>
                    </td>
                    <td class="px-4 py-2.5 align-middle">
                        ${secret ? `
                        <div class="relative">
                            ${input}
                            <button type="button" data-toggle-secret="${row.key}" aria-label="Show password" title="Show password"
                                    class="absolute inset-y-0 right-0 px-3 flex items-center text-text-500 hover:text-text-100 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7S2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>` : input}
                    </td>
                </tr>
            `;
        });

        html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        return html;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function getCurrentValues() {
        const values = {};
        lineOrder.forEach(item => {
            if (item.type === 'variable') {
                const input = document.getElementById(item.key);
                values[item.key] = input ? input.value : '';
            }
        });
        return values;
    }

    function hasChanges() {
        const current = getCurrentValues();
        for (let key in originalValues) {
            if (current[key] !== originalValues[key]) return true;
        }
        return false;
    }

    function reconstructBlock() {
        const lines = [];
        lineOrder.forEach(item => {
            if (item.type === 'comment') {
                lines.push(item.text);
            } else if (item.type === 'variable') {
                const input = document.getElementById(item.key);
                lines.push(`${item.key}=${input ? input.value : ''}`);
            }
        });
        return lines.join('\n');
    }

    function updateSaveButtonState() {
        const changed = hasChanges();
        saveBtn.disabled = !changed;

        if (changed) {
            saveBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            setStatus("Changes detected", "warning");
        } else {
            saveBtn.classList.add('opacity-50', 'cursor-not-allowed');
            setStatus("All changes saved", "success");
        }

        lineOrder.forEach(item => {
            if (item.type === 'variable') {
                const input = document.getElementById(item.key);
                if (input) {
                    if (input.value !== originalValues[item.key]) {
                        input.classList.add('changed');
                    } else {
                        input.classList.remove('changed');
                    }
                }
            }
        });
    }

    function onInputChange() {
        updateSaveButtonState();
    }

    // ----- Load MQTT settings -----
    setStatus("Loading settings...", "info");
    fetch('{{ route('env.mqtt.load') }}')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                buildForm(data.content);
                setStatus("Loaded successfully", "success");
                // Start from the topic the seismic service subscribes to.
                const topic = document.getElementById('MQTT_TOPIC');
                if (topic && topic.value.trim()) document.getElementById('peek-topic').value = topic.value.trim();
            } else {
                setStatus(data.error || "Failed to load settings", "error");
            }
        })
        .catch(() => setStatus("Server error", "error"));

    // ----- Save MQTT settings -----
    saveBtn.addEventListener('click', async () => {
        if (!hasChanges()) {
            setStatus("No changes to save", "warning");
            return;
        }

        const block = reconstructBlock();
        setStatus("Saving...", "info");

        try {
            const response = await fetch('{{ route('env.mqtt.save') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ content: block })
            });

            const data = await response.json();
            if (data.success) {
                setStatus("Saved successfully!", "success");
                originalValues = getCurrentValues();
                updateSaveButtonState();
            } else {
                setStatus(data.error || "Save failed", "error");
            }
        } catch (err) {
            setStatus("Server error", "error");
            console.error(err);
        }
    });

    // ----- Live MQTT messages -----
    const peekBtn = document.getElementById('peek-btn');
    const peekStatus = document.getElementById('peek-status');
    const peekList = document.getElementById('peek-messages');

    function setPeekStatus(text, cls) {
        peekStatus.className = 'text-sm ' + (cls || 'text-text-400');
        peekStatus.textContent = text;
    }

    function renderPeekMessage(m, i) {
        let body = m.payload;
        let isJson = false;
        try { body = JSON.stringify(JSON.parse(m.payload), null, 2); isJson = true; } catch (e) {}
        const time = new Date(m.received_at).toLocaleTimeString();
        const badges = [
            m.retained ? '<span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-munti-yellow-600/20 text-munti-yellow-400 border border-munti-yellow-500/30" title="Stored on the broker earlier, not necessarily new">retained</span>' : '',
            isJson ? '<span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-radar-600/20 text-radar-400 border border-radar-500/30">JSON</span>' : '',
            `<span class="text-[11px] text-text-500">${m.bytes} bytes${m.payload_truncated ? ', shortened' : ''}</span>`,
        ].join(' ');
        return `
            <details class="rounded-lg border border-border-700 bg-surface-900" ${i < 3 ? 'open' : ''}>
                <summary class="cursor-pointer px-3 py-2 flex flex-wrap items-center gap-2 text-xs">
                    <span class="font-mono text-text-500">${escapeHtml(time)}</span>
                    <span class="font-mono text-text-100 break-all">${escapeHtml(m.topic)}</span>
                    ${badges}
                </summary>
                <pre class="px-3 pb-3 text-xs text-text-300 whitespace-pre-wrap break-all thin-scrollbar max-h-72 overflow-y-auto">${escapeHtml(body)}</pre>
            </details>`;
    }

    peekBtn.addEventListener('click', async () => {
        const topic = document.getElementById('peek-topic').value.trim();
        const seconds = parseInt(document.getElementById('peek-seconds').value, 10);
        if (!topic) { setPeekStatus('Enter a topic, or # for everything.', 'text-munti-red-400'); return; }

        peekBtn.disabled = true;
        peekList.innerHTML = '';
        let left = seconds;
        setPeekStatus(`Listening on "${topic}"… ${left}s`, 'text-radar-400');
        const timer = setInterval(() => {
            left = Math.max(0, left - 1);
            setPeekStatus(left > 0 ? `Listening on "${topic}"… ${left}s` : 'Collecting results…', 'text-radar-400');
        }, 1000);

        try {
            const res = await fetch('{{ route('env.mqtt.peek') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ topic, seconds })
            });
            const data = await res.json().catch(() => ({ ok: false, error: `Server error (HTTP ${res.status})` }));

            if (res.status === 422) {
                setPeekStatus(Object.values(data.errors || {}).flat().join(' ') || 'Invalid topic.', 'text-munti-red-400');
            } else if (!data.ok) {
                setPeekStatus(data.error || 'Could not listen to the broker.', 'text-munti-red-400');
            } else {
                const n = data.messages.length;
                const retained = data.messages.filter(m => m.retained).length;
                if (n === 0) {
                    setPeekStatus(`Connected to ${data.broker}, but no messages on "${data.topic}" in ${data.seconds} seconds. The devices may not be publishing, or they use a different topic (try #).`, 'text-munti-yellow-400');
                } else {
                    setPeekStatus(`Connected to ${data.broker}: ${n} message(s) on "${data.topic}" in ${data.seconds} seconds`
                        + (retained ? ` (${retained} retained from earlier)` : '')
                        + (data.truncated ? '. Stopped at 200 messages.' : '.'), 'text-munti-green-400');
                    peekList.innerHTML = data.messages.slice().reverse().map((m, i) => renderPeekMessage(m, i)).join('');
                }
            }
        } catch (err) {
            setPeekStatus('Server error while listening.', 'text-munti-red-400');
            console.error(err);
        } finally {
            clearInterval(timer);
            peekBtn.disabled = false;
        }
    });
</script>

@include('layouts.footer')