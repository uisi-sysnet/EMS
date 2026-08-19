@include('layouts.header')
@include('layouts.topbar')

{{-- NOTE: adjust the includes above/below if your app's actual layout
     partial names differ — these match the ones used in index.blade.php. --}}

<div class="max-w-2xl mx-auto px-4 py-8">
    <h1 class="text-xl font-semibold text-text-100 mb-1">Telegram Notifications</h1>
    <p class="text-sm text-text-400 mb-6">
        Connect a Telegram bot to receive the daily system digest, station
        offline alerts, and system health alerts.
    </p>

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

    <div class="rounded-xl border border-border-700/50 bg-surface-900 p-5 mb-6 text-sm text-text-400">
        <p class="font-medium text-text-100 mb-2">First time setting this up?</p>
        <ol class="list-decimal list-inside space-y-1">
            <li>In Telegram, message <span class="text-text-100">@BotFather</span> and send <span class="text-text-100">/newbot</span>, then paste the token it gives you below.</li>
            <li>Add the bot to the chat/group you want alerts in, then send it any message.</li>
            <li>Get the chat ID by visiting <span class="text-text-100">https://api.telegram.org/bot&lt;YOUR_TOKEN&gt;/getUpdates</span> and reading <span class="text-text-100">"chat":{"id": ...}</span> from the response.</li>
        </ol>
    </div>

    <form method="POST" action="{{ route('settings.telegram.update') }}" class="rounded-xl border border-border-700/50 bg-surface-900 p-5 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="bot_token" class="block text-sm font-medium text-text-100 mb-1">Bot Token</label>
            <input type="password" id="bot_token" name="bot_token" autocomplete="off"
                   placeholder="{{ $settings->isConfigured() ? 'Saved — leave blank to keep it' : 'e.g. 123456789:AAExampleTokenGoesHere' }}"
                   class="w-full rounded-lg border border-border-700 bg-transparent px-3 py-2 text-sm text-text-100 focus:outline-none focus:ring-2 focus:ring-munti-green-500">
            <p class="text-xs text-text-400 mt-1">Stored encrypted. Never shown again once saved — leave blank to keep the current token.</p>
        </div>

        <div>
            <label for="chat_id" class="block text-sm font-medium text-text-100 mb-1">Chat ID</label>
            <input type="text" id="chat_id" name="chat_id" value="{{ old('chat_id', $settings->chat_id) }}"
                   placeholder="e.g. -1001234567890"
                   class="w-full rounded-lg border border-border-700 bg-transparent px-3 py-2 text-sm text-text-100 focus:outline-none focus:ring-2 focus:ring-munti-green-500">
        </div>

        <hr class="border-border-700/50">

        <div class="flex items-center justify-between gap-4">
            <div>
                <label for="morning_digest_enabled" class="text-sm font-medium text-text-100">Morning digest</label>
                <p class="text-xs text-text-400">Station status + system health, sent once a day at this time.</p>
            </div>
            <div class="flex items-center gap-3">
                <input type="time" name="morning_digest_time" value="{{ old('morning_digest_time', $settings->morning_digest_time) }}"
                       class="rounded-lg border border-border-700 bg-transparent px-2 py-1 text-sm text-text-100">
                <input type="checkbox" id="morning_digest_enabled" name="morning_digest_enabled" value="1"
                       @checked(old('morning_digest_enabled', $settings->morning_digest_enabled))
                       class="h-5 w-5 rounded border-border-700 text-munti-green-500 focus:ring-munti-green-500">
            </div>
        </div>

        <div class="flex items-center justify-between gap-4">
            <div>
                <label for="afternoon_digest_enabled" class="text-sm font-medium text-text-100">Afternoon digest</label>
                <p class="text-xs text-text-400">A second digest later in the day, at this time.</p>
            </div>
            <div class="flex items-center gap-3">
                <input type="time" name="afternoon_digest_time" value="{{ old('afternoon_digest_time', $settings->afternoon_digest_time) }}"
                       class="rounded-lg border border-border-700 bg-transparent px-2 py-1 text-sm text-text-100">
                <input type="checkbox" id="afternoon_digest_enabled" name="afternoon_digest_enabled" value="1"
                       @checked(old('afternoon_digest_enabled', $settings->afternoon_digest_enabled))
                       class="h-5 w-5 rounded border-border-700 text-munti-green-500 focus:ring-munti-green-500">
            </div>
        </div>

        <div class="flex items-center justify-between gap-4">
            <div>
                <label for="offline_alert_enabled" class="text-sm font-medium text-text-100">Station offline alerts</label>
                <p class="text-xs text-text-400">Sent immediately when a station goes offline, and again when it comes back.</p>
            </div>
            <input type="checkbox" id="offline_alert_enabled" name="offline_alert_enabled" value="1"
                   @checked(old('offline_alert_enabled', $settings->offline_alert_enabled))
                   class="h-5 w-5 rounded border-border-700 text-munti-green-500 focus:ring-munti-green-500">
        </div>

        <div class="flex items-center justify-between gap-4">
            <div>
                <label for="health_alert_enabled" class="text-sm font-medium text-text-100">System health alerts</label>
                <p class="text-xs text-text-400">Sent when CPU, memory, or storage crosses into critical — and again when it recovers.</p>
            </div>
            <input type="checkbox" id="health_alert_enabled" name="health_alert_enabled" value="1"
                   @checked(old('health_alert_enabled', $settings->health_alert_enabled))
                   class="h-5 w-5 rounded border-border-700 text-munti-green-500 focus:ring-munti-green-500">
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="rounded-lg bg-munti-green-500 hover:bg-munti-green-600 text-white text-sm font-medium px-4 py-2">
                Save Settings
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('settings.telegram.test') }}" class="mt-4">
        @csrf
        <button type="submit"
                class="rounded-lg border border-border-700 hover:bg-surface-800 text-text-100 text-sm font-medium px-4 py-2">
            Send Test Message
        </button>
    </form>
</div>

@include('layouts.footer')