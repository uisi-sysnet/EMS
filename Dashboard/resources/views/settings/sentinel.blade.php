@include('layouts.header')
@include('layouts.topbar')

@php
    $inputCls = 'w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition';
    $labelCls = 'block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide';
    $test = session('sentinel_test');
@endphp

<div id="main-content" class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2.5">
                <span class="leading-tight uppercase tracking-wide">Uplink Sentinel</span>
                @if ($settings->isConfigured())
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $settings->enabled ? 'bg-munti-green-700/20 text-munti-green-400 border-munti-green-600/30' : 'bg-surface-700 text-text-400 border-border-600' }}">
                        {{ $settings->enabled ? 'Reporting on' : 'Reporting off' }}
                    </span>
                @endif
            </h2>
            <span class="text-xs sm:text-sm text-text-400">Send every sensor's status to the Uplink Sentinel monitoring system</span>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-4 sm:py-6 px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 sm:mb-6 rounded-lg border border-border-700 bg-munti-green-500/10 text-munti-green-400 text-sm px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 sm:mb-6 rounded-lg border border-red-500/40 bg-red-500/10 text-red-400 text-sm px-4 py-3">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($test)
                <div class="mb-4 sm:mb-6 rounded-lg border text-sm px-4 py-3 {{ $test['ok'] ? 'border-munti-green-600/40 bg-munti-green-500/10' : 'border-red-500/40 bg-red-500/10' }}">
                    <p class="font-semibold {{ $test['ok'] ? 'text-munti-green-400' : 'text-red-400' }}">
                        Test connection: {{ $test['code'] ? 'HTTP ' . $test['code'] : 'no response' }}
                        <span class="font-normal text-text-400">· {{ $test['units'] }} sensor(s) sent</span>
                    </p>
                    <p class="mt-1 text-text-300 break-words">{{ $test['summary'] }}</p>
                    @if (filled($test['body']))
                        <details class="mt-2">
                            <summary class="cursor-pointer text-xs text-text-400 hover:text-text-100">Sentinel's response</summary>
                            <pre class="mt-2 text-xs text-text-300 bg-surface-900 border border-border-700 rounded-lg p-3 overflow-x-auto thin-scrollbar">{{ $test['body'] }}</pre>
                        </details>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 sm:gap-6">

                <!-- ==================== LEFT: LINK SETTINGS ==================== -->
                <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm">
                    <div class="px-4 sm:px-5 py-3 sm:py-3.5 border-b border-border-700 bg-surface-900/70">
                        <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider">Link Settings</h3>
                    </div>

                    <form method="POST" action="{{ route('settings.sentinel.update') }}" class="p-4 sm:p-5 flex-1 flex flex-col">
                        @csrf
                        @method('PUT')

                        <div class="space-y-5 flex-1">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="sm:col-span-2 min-w-0">
                                    <label for="host" class="{{ $labelCls }}">Sentinel IP address</label>
                                    <input type="text" id="host" name="host" required
                                           value="{{ old('host', $settings->host) }}"
                                           placeholder="e.g. 192.168.1.50"
                                           class="{{ $inputCls }} font-mono">
                                </div>
                                <div class="min-w-0">
                                    <label for="port" class="{{ $labelCls }}">Port</label>
                                    <input type="number" id="port" name="port" min="1" max="65535" required
                                           value="{{ old('port', $settings->port ?? 8090) }}"
                                           class="{{ $inputCls }} font-mono">
                                </div>
                            </div>

                            <div class="min-w-0">
                                <label for="token" class="{{ $labelCls }}">Key from Sentinel</label>
                                <input type="password" id="token" name="token" autocomplete="off"
                                       placeholder="{{ filled($settings->token) ? 'Saved — leave blank to keep it' : 'Paste the EMS link key from Sentinel' }}"
                                       class="{{ $inputCls }} font-mono">
                                <p class="text-xs text-text-500 mt-1.5">Stored encrypted and never shown again. Leave blank to keep the saved key.</p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="min-w-0">
                                    <label for="interval_minutes" class="{{ $labelCls }}">Send every (minutes)</label>
                                    <input type="number" id="interval_minutes" name="interval_minutes" min="1" max="1440" required
                                           value="{{ old('interval_minutes', $settings->interval_minutes ?? 30) }}"
                                           class="{{ $inputCls }}">
                                </div>
                                <div class="min-w-0">
                                    <label for="system_name" class="{{ $labelCls }}">System name</label>
                                    <input type="text" id="system_name" name="system_name" maxlength="100" required
                                           value="{{ old('system_name', $settings->system_name ?? 'EMS-AQ') }}"
                                           class="{{ $inputCls }}">
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <label for="use_https" class="text-sm font-medium text-text-100">Use HTTPS</label>
                                    <p class="text-xs text-text-500">Only if Sentinel is served over HTTPS.</p>
                                </div>
                                <input type="checkbox" id="use_https" name="use_https" value="1"
                                       @checked(old('use_https', $settings->use_https))
                                       class="h-4 w-4 rounded border-border-600 bg-surface-900 text-munti-green-600 focus:ring-munti-green-500 focus:ring-offset-0 shrink-0">
                            </div>

                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <label for="enabled" class="text-sm font-medium text-text-100">Enable reporting</label>
                                    <p class="text-xs text-text-500">Send on the interval above and within about 2 minutes of any status change.</p>
                                </div>
                                <input type="checkbox" id="enabled" name="enabled" value="1"
                                       @checked(old('enabled', $settings->enabled))
                                       class="h-4 w-4 rounded border-border-600 bg-surface-900 text-munti-green-600 focus:ring-munti-green-500 focus:ring-offset-0 shrink-0">
                            </div>

                            @if ($settings->url())
                                <p class="text-xs text-text-500">Reports go to <span class="font-mono text-text-300 break-all">{{ $settings->url() }}</span></p>
                            @endif
                        </div>

                        <div class="mt-6 pt-2">
                            <button type="submit"
                                    class="w-full px-4 py-2.5 bg-munti-green-600 hover:bg-munti-green-500 text-text-100 font-semibold rounded-lg transition border border-munti-green-500/30 flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ==================== RIGHT: STATUS + TEST ==================== -->
                <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm">
                    <div class="px-4 sm:px-5 py-3 sm:py-3.5 border-b border-border-700 bg-surface-900/70">
                        <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider">Connection</h3>
                    </div>

                    <div class="p-4 sm:p-5 flex-1 flex flex-col gap-5">
                        <dl class="grid grid-cols-[auto,1fr] gap-x-4 gap-y-2 text-sm">
                            <dt class="text-text-500">Last report</dt>
                            <dd class="text-text-100">
                                @if ($settings->last_attempt_at)
                                    {{ $settings->last_attempt_at->timezone('Asia/Manila')->format('M j, Y H:i:s') }}
                                    <span class="ml-1 px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $settings->last_status_code === 202 ? 'bg-munti-green-700/20 text-munti-green-400 border-munti-green-600/30' : 'bg-munti-red-700/20 text-munti-red-400 border-munti-red-600/30' }}">
                                        {{ $settings->last_status_code ? 'HTTP ' . $settings->last_status_code : 'no response' }}
                                    </span>
                                @else
                                    <span class="text-text-500">Nothing sent yet</span>
                                @endif
                            </dd>
                            <dt class="text-text-500">Last accepted</dt>
                            <dd class="text-text-100">{{ $settings->last_success_at ? $settings->last_success_at->timezone('Asia/Manila')->format('M j, Y H:i:s') : '—' }}</dd>
                            @if ($settings->last_result)
                                <dt class="text-text-500">Result</dt>
                                <dd class="text-text-300 break-words">{{ $settings->last_result }}</dd>
                            @endif
                            @if ($effective['source'] !== 'settings page' && filled($effective['url']))
                                <dt class="text-text-500">In use</dt>
                                <dd class="text-amber-400">Settings from scripts/.env ({{ $effective['url'] }}). Saving this page replaces them.</dd>
                            @endif
                        </dl>

                        <div class="text-sm text-text-400 space-y-2">
                            <p>Each sensor is sent with its status: <span class="text-text-100">online</span>, <span class="text-text-100">stale</span>, <span class="text-text-100">offline</span> or <span class="text-text-100">no_data</span>, grouped by station. Cameras are sent as online, unreachable or fault.</p>
                            <p>Every send is logged on the <a href="{{ route('logs.index') }}" class="text-radar-400 hover:underline">Logs</a> page. A wrong key or a disabled link also shows in the notification bell.</p>
                        </div>

                        <form method="POST" action="{{ route('settings.sentinel.test') }}" class="mt-auto">
                            @csrf
                            <button type="submit" @disabled(!filled($effective['url']) || !filled($effective['token']))
                                    class="w-full px-4 py-2.5 bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed text-text-100 font-semibold rounded-lg transition border border-border-600 flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Test Connection
                            </button>
                            <p class="text-xs text-text-500 mt-2 text-center">Sends one report now with the saved settings and shows Sentinel's reply. Works while reporting is off.</p>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

@include('layouts.footer')
