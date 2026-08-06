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

    /* Toggle switch container */
    .toggle-container {
        position: relative;
        display: inline-block;
        width: 2.25rem;
        height: 1.25rem;
    }
    .toggle-container input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #4B5563; /* gray-600 */
        transition: .3s;
        border-radius: 9999px;
    }
    .toggle-slider::before {
        content: "";
        position: absolute;
        height: 1rem;
        width: 1rem;
        left: 2px;
        bottom: 2px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
        border: 1px solid #D1D5DB;
    }
    input:checked + .toggle-slider {
        background-color: #22C55E; /* green-500 */
    }
    input:checked + .toggle-slider::before {
        transform: translateX(1rem);
        border-color: white;
    }
</style>

<div id="main-content" class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                <span class="leading-tight uppercase">API Settings</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">Generate and manage API keys</span>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-5 px-5 sm:px-8 space-y-8">

            <!-- Add new key form -->
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
                <div id="status" class="mt-3 text-sm font-medium text-center"></div>
            </div>

            <!-- Existing keys list -->
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
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" class="sr-only peer toggle-key"
                                                    data-token="{{ $key->token_hash }}"
                                                    {{ $key->enabled ? 'checked' : '' }}>
                                                <div class="w-9 h-5 bg-gray-600 peer-focus:ring-2 peer-focus:ring-radar-500/50 rounded-full peer
                                                            peer-checked:bg-munti-green-500 transition-all duration-300
                                                            after:content-[''] after:absolute after:top-0.5 after:left-[2px]
                                                            after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4
                                                            after:transition-all after:duration-300
                                                            peer-checked:after:translate-x-full peer-checked:after:border-white
                                                            peer-disabled:opacity-50 peer-disabled:cursor-not-allowed">
                                                </div>
                                            </label>
                                        </td>
                                        <td class="px-4 py-1 whitespace-nowrap text-xs text-text-500">
                                            {{ $key->created_at->format('Y-m-d H:i') }}
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

        </div>
    </div>
</div>

<script>
    const ownerLabelInput = document.getElementById('ownerLabel');
    const saveBtn = document.getElementById('saveBtn');
    const status = document.getElementById('status');
    const form = document.getElementById('apiKeyForm');

    // ----- Helper: set status with color -----
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

    // ----- Validate that label is filled -----
    function validateForm() {
        const label = ownerLabelInput.value.trim();
        saveBtn.disabled = !label;
    }

    ownerLabelInput.addEventListener('input', validateForm);
    validateForm(); // initial state

    // ----- Save the key (auto‑generated on server) -----
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const owner_label = ownerLabelInput.value.trim();
        if (!owner_label) return;

        setStatus('Generating and saving...', 'info');
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
                setStatus('API key generated and saved successfully!', 'success');
                ownerLabelInput.value = '';
                validateForm();
                setTimeout(() => location.reload(), 800);
            } else {
                setStatus('' + (data.error || 'Operation failed'), 'error');
            }
        } catch (err) {
            setStatus('Server error', 'error');
            console.error(err);
        }
    });

    // ----- Delete key -----
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
                    setStatus('Key deleted successfully!', 'success');
                } else {
                    setStatus('Failed to delete key.', 'error');
                }
            } catch (err) {
                setStatus('Server error', 'error');
                console.error(err);
            }
        });
    });

    // Toggle handler
    document.querySelectorAll('.toggle-key').forEach(toggle => {
        toggle.addEventListener('change', async function() {
            const token = this.dataset.token;
            const isChecked = this.checked;          // new state
            const originalChecked = !isChecked;      // to revert on error

            try {
                const url = `/api-keys/toggle/${token}`;
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ enabled: isChecked })
                });
                const data = await response.json();

                if (!data.success) {
                    this.checked = originalChecked;   // revert the toggle
                    setStatus('Failed to update status.', 'error');
                } else {
                    setStatus('Status updated successfully.', 'success');
                }
            } catch (err) {
                this.checked = originalChecked;       // revert on network error
                setStatus('Server error', 'error');
                console.error(err);
            }
        });
    });
</script>

@include('layouts.footer')