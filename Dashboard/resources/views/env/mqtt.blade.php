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

        lines.forEach(line => {
            const trimmed = line.trim();

            if (trimmed.startsWith('#')) {
                if (currentSection) sections.push(currentSection);

                let displayText = trimmed
                    .replace(/^#\s*----\s*/, '')
                    .replace(/\s*----\s*$/, '')
                    .trim();

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
                    currentSection = { title: 'Settings', originalComment: null, rows: [] };
                }

                currentSection.rows.push({ key: cleanKey, value: cleanValue });
                lineOrder.push({ type: 'variable', key: cleanKey });
            }
        });
        if (currentSection) sections.push(currentSection);

        let html = '';

        if (sections.length === 2) {
            html += `<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">`;
            sections.forEach(sec => {
                html += renderSection(sec);
            });
            html += `</div>`;
        } else {
            sections.forEach(sec => {
                html += renderSection(sec);
            });
        }

        container.innerHTML = html;

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

    function renderSection(sec) {
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
            html += `
                <tr class="${isLast ? '' : 'border-b border-border-800'} hover:bg-surface-700/60 transition">
                    <td class="px-4 py-3 font-medium text-text-300 whitespace-nowrap w-40 md:w-48 align-middle">
                        <label for="${row.key}" class="label-mono">${escapeHtml(row.key)}</label>
                    </td>
                    <td class="px-4 py-2.5 align-middle">
                        <input type="text"
                            id="${row.key}"
                            value="${escapeHtml(row.value)}"
                            class="w-full px-3 py-2 border border-border-600 rounded-lg
                                   focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500
                                   text-sm bg-surface-900 text-text-100 placeholder-text-500 transition">
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
</script>

@include('layouts.footer')