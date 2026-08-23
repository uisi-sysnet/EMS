@include('layouts.header')
@include('layouts.topbar')

<div id="main-content" class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2.5">
                <span class="leading-tight uppercase tracking-wide">Telegram Notifications</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">Connect a Telegram bot to receive daily digests and alerts</span>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-6 px-5 sm:px-8">

            @if (session('status'))
                <div class="mb-6 rounded-lg border border-border-700 bg-munti-green-500/10 text-munti-green-400 text-sm px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-red-500/40 bg-red-500/10 text-red-400 text-sm px-4 py-3">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 h-full">

                <!-- ==================== LEFT: INSTRUCTIONS ==================== -->
                <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm h-full">

                    <!-- Card Header -->
                    <div class="px-5 py-3.5 border-b border-border-700 bg-surface-900/70 flex items-center gap-2">
                        <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider">Setup Instructions</h3>
                    </div>

                    <!-- Instructions -->
                    <div class="p-5 flex-1 flex flex-col">
                        <p class="text-sm font-medium text-text-100 mb-3">First time setting this up?</p>
                        <ol class="list-decimal list-inside space-y-2.5 text-sm text-text-400 flex-1">
                            <li>
                                In Telegram, message
                                <span class="text-text-100 font-medium">@BotFather</span>
                                and send
                                <span class="text-text-100 font-medium">/newbot</span>,
                                then paste the token it gives you on the right.
                            </li>
                            <li>
                                Add the bot to the chat/group you want alerts in, then send it any message.
                            </li>
                            <li>
                                Get the chat ID by visiting
                                <span class="text-text-100 font-mono text-xs break-all">
                                    https://api.telegram.org/bot&lt;YOUR_TOKEN&gt;/getUpdates
                                </span>
                                and reading
                                <span class="text-text-100 font-mono text-xs">"chat":{"id": ...}</span>
                                from the response.
                            </li>
                        </ol>

                        <!-- Test button -->
                        <form method="POST" action="{{ route('settings.telegram.test') }}" class="mt-6">
                            @csrf
                            <button type="submit"
                                    class="w-full px-4 py-2.5 bg-surface-700 hover:bg-surface-600 text-text-100 font-semibold rounded-lg transition border border-border-600 flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                Send Test Message
                            </button>
                        </form>
                    </div>
                </div>

                <!-- ==================== RIGHT: SETTINGS FORM ==================== -->
                <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm h-full">

                    <!-- Card Header -->
                    <div class="px-5 py-3.5 border-b border-border-700 bg-surface-900/70 flex items-center gap-2">
                        <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider">Bot Settings</h3>
                    </div>

                    <!-- Form -->
                    <form method="POST" action="{{ route('settings.telegram.update') }}" class="p-5 flex-1 flex flex-col">
                        @csrf
                        @method('PUT')

                        <div class="space-y-5 flex-1 overflow-auto">
                            <!-- Bot Token + Chat ID in one row -->
                            <div class="flex flex-col sm:flex-row gap-4">
                                <!-- Bot Token -->
                                <div class="flex-1 min-w-0">
                                    <label for="bot_token" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                        Bot Token
                                    </label>
                                    <input type="password" id="bot_token" name="bot_token" autocomplete="off"
                                           placeholder="{{ $settings->isConfigured() ? 'Saved — leave blank to keep it' : 'e.g. 123456789:AAExampleTokenGoesHere' }}"
                                           class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg
                                                  bg-surface-900 text-text-100 placeholder-text-500
                                                  focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                                </div>

                                <!-- Chat ID -->
                                <div class="flex-1 min-w-0">
                                    <label for="chat_id" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                        Chat ID
                                    </label>
                                    <input type="text" id="chat_id" name="chat_id"
                                           value="{{ old('chat_id', $settings->chat_id) }}"
                                           placeholder="e.g. -1001234567890"
                                           class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg
                                                  bg-surface-900 text-text-100 placeholder-text-500
                                                  focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition font-mono">
                                </div>

                            </div>
                            <p class="text-xs text-text-500 mt-1.5">
                                Stored encrypted. Never shown again once saved — leave blank to keep the current token.
                            </p>

                            <!-- Morning Digest -->
                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <label for="morning_digest_enabled" class="text-sm font-medium text-text-100">Morning digest</label>
                                    <p class="text-xs text-text-500">Station status + system health, sent once a day at this time.</p>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <input type="time" name="morning_digest_time"
                                           value="{{ old('morning_digest_time', $settings->morning_digest_time) }}"
                                           class="rounded-lg border border-border-600 bg-surface-900 px-2.5 py-1.5 text-sm text-text-100">
                                    <input type="checkbox" id="morning_digest_enabled" name="morning_digest_enabled" value="1"
                                           @checked(old('morning_digest_enabled', $settings->morning_digest_enabled))
                                           class="h-4 w-4 rounded border-border-600 bg-surface-900 text-munti-green-600 focus:ring-munti-green-500 focus:ring-offset-0">
                                </div>
                            </div>

                            <!-- Afternoon Digest -->
                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <label for="afternoon_digest_enabled" class="text-sm font-medium text-text-100">Afternoon digest</label>
                                    <p class="text-xs text-text-500">A second digest later in the day, at this time.</p>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <input type="time" name="afternoon_digest_time"
                                           value="{{ old('afternoon_digest_time', $settings->afternoon_digest_time) }}"
                                           class="rounded-lg border border-border-600 bg-surface-900 px-2.5 py-1.5 text-sm text-text-100">
                                    <input type="checkbox" id="afternoon_digest_enabled" name="afternoon_digest_enabled" value="1"
                                           @checked(old('afternoon_digest_enabled', $settings->afternoon_digest_enabled))
                                           class="h-4 w-4 rounded border-border-600 bg-surface-900 text-munti-green-600 focus:ring-munti-green-500 focus:ring-offset-0">
                                </div>
                            </div>

                            <!-- Station Offline Alerts -->
                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <label for="offline_alert_enabled" class="text-sm font-medium text-text-100">Station offline alerts</label>
                                    <p class="text-xs text-text-500">Sent immediately when a station goes offline, and again when it comes back.</p>
                                </div>
                                <input type="checkbox" id="offline_alert_enabled" name="offline_alert_enabled" value="1"
                                       @checked(old('offline_alert_enabled', $settings->offline_alert_enabled))
                                       class="h-4 w-4 rounded border-border-600 bg-surface-900 text-munti-green-600 focus:ring-munti-green-500 focus:ring-offset-0 shrink-0">
                            </div>

                            <!-- System Health Alerts -->
                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <label for="health_alert_enabled" class="text-sm font-medium text-text-100">System health alerts</label>
                                    <p class="text-xs text-text-500">Sent when CPU, memory, or storage crosses into critical — and again when it recovers.</p>
                                </div>
                                <input type="checkbox" id="health_alert_enabled" name="health_alert_enabled" value="1"
                                       @checked(old('health_alert_enabled', $settings->health_alert_enabled))
                                       class="h-4 w-4 rounded border-border-600 bg-surface-900 text-munti-green-600 focus:ring-munti-green-500 focus:ring-offset-0 shrink-0">
                            </div>

                            <div class="flex items-center justify-between gap-4">
                                <button type="submit"
                                        class="w-full px-4 py-2.5 bg-munti-green-600 hover:bg-munti-green-500 text-text-100 font-semibold rounded-lg transition border border-munti-green-500/30 flex items-center justify-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Save Settings
                                </button>
                            </div>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

@include('layouts.footer')