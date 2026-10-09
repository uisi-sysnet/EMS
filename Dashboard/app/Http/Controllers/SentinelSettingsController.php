<?php

namespace App\Http\Controllers;

use App\Models\SentinelSetting;
use App\Services\Sentinel\SentinelReporter;
use Illuminate\Http\Request;

/**
 * Settings > Sentinel: the link to Uplink Sentinel (address, key, interval)
 * and a Test Connection button that sends one report and shows the reply.
 */
class SentinelSettingsController extends Controller
{
    public function edit(SentinelReporter $reporter)
    {
        $settings = SentinelSetting::current();

        return view('settings.sentinel', [
            'settings'  => $settings,
            'effective' => $reporter->settings(),
        ]);
    }

    public function update(Request $request, SentinelReporter $reporter)
    {
        $validated = $request->validate([
            // IP address (v4/v6) or hostname, no scheme or path.
            'host'             => ['required', 'string', 'max:255', function ($attr, $value, $fail) {
                if (!filter_var($value, FILTER_VALIDATE_IP)
                    && !preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i', $value)) {
                    $fail('Enter the Sentinel IP address or hostname only, e.g. 192.168.1.50 (no http:// or path).');
                }
            }],
            'port'             => ['required', 'integer', 'between:1,65535'],
            'token'            => ['nullable', 'string', 'max:500'],
            'interval_minutes' => ['required', 'integer', 'between:1,1440'],
            'system_name'      => ['required', 'string', 'max:100'],
        ], [], ['token' => 'key', 'host' => 'Sentinel IP address']);

        $settings = SentinelSetting::current();

        // A blank key field keeps the saved key (it is never shown again).
        if (!filled($validated['token'] ?? null)) {
            unset($validated['token']);
            if ($settings->key() === null) {
                return back()->withInput()->withErrors(['token' => 'Enter the key from Sentinel.']);
            }
        }

        $validated['enabled']   = $request->boolean('enabled');
        $validated['use_https'] = $request->boolean('use_https');
        $settings->update($validated);

        // New link settings: send on the next run instead of waiting out a backoff.
        $reporter->resetSchedule();

        return back()->with('status', $settings->enabled
            ? 'Sentinel settings saved. Reports will start within a minute.'
            : 'Sentinel settings saved. Reporting is off; use Test Connection to check the link.');
    }

    public function test(SentinelReporter $reporter)
    {
        if (!$reporter->configured()) {
            return back()->withErrors(['host' => 'Save the Sentinel IP address and key first.']);
        }

        $result = $reporter->sendNow();

        return back()->with('sentinel_test', [
            'ok'      => $result['code'] === 202,
            'code'    => $result['code'],
            'summary' => $result['summary'],
            'units'   => count($result['payload']['units']),
            'body'    => is_array($result['body'])
                ? json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (string) ($result['body'] ?? ''),
        ]);
    }
}
