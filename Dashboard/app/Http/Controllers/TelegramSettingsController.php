<?php

namespace App\Http\Controllers;

use App\Models\TelegramSetting;
use App\Services\TelegramNotifier;
use Illuminate\Http\Request;

class TelegramSettingsController extends Controller
{
    public function edit()
    {
        $settings = TelegramSetting::current();

        return view('settings.telegram', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'bot_token'         => ['nullable', 'string'],
            'chat_id'           => ['nullable', 'string', 'max:64'],
            'daily_digest_time' => ['required', 'date_format:H:i'],
        ]);

        $settings = TelegramSetting::current();

        // A blank bot-token field means "leave the saved token as-is".
        // The token is never echoed back into the form (see the view),
        // so on every normal save this field arrives empty — it should
        // never overwrite a working token with nothing.
        if (! filled($validated['bot_token'] ?? null)) {
            unset($validated['bot_token']);
        }

        // Checkboxes are absent from the request entirely when unchecked,
        // so read them explicitly rather than relying on $validated.
        $validated['daily_digest_enabled']  = $request->boolean('daily_digest_enabled');
        $validated['offline_alert_enabled'] = $request->boolean('offline_alert_enabled');
        $validated['health_alert_enabled']  = $request->boolean('health_alert_enabled');

        $settings->update($validated);

        return back()->with('status', 'Telegram settings saved.');
    }

    public function test(TelegramNotifier $telegram)
    {
        $ok = $telegram->send(
            "✅ <b>Test message</b>\nYour EMS Gateway Telegram bot is connected and working."
        );

        return back()->with('status', $ok
            ? 'Test message sent — check your Telegram chat.'
            : 'Could not send test message. Double-check the bot token and chat ID, and see the application log for details.'
        );
    }
}