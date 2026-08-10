<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalAuthController extends Controller
{
    /**
     * Issue a short-lived, signed token that the browser passes to the
     * Node PTY bridge (terminal-server/server.js) as its first WebSocket
     * message. The Node process shares TERMINAL_SHARED_SECRET via its own
     * .env — it never talks to Laravel, MySQL, or sessions, it just checks
     * the HMAC signature and the expiry.
     *
     * Because the token is only ever generated behind the same
     * 'auth' + 'role:administrator' middleware as the rest of Maintenance,
     * simply holding a valid token is treated as proof of authorization.
     *
     * POST /maintenance/terminal/token
     */
    public function issue(Request $request): JsonResponse
    {
        $secret = config('services.terminal_secret'); // see config/services.php + .env

        if (empty($secret)) {
            abort(500, 'TERMINAL_SHARED_SECRET is not configured.');
        }

        $payload = [
            'u'   => session('username') ?? 'unknown',
            'iat' => time(),
            'exp' => time() + 60, // 60s to establish the WS connection; reconnects just call this again
        ];

        $payloadJson = json_encode($payload);
        $payloadB64  = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
        $signature   = hash_hmac('sha256', $payloadB64, $secret);

        return response()->json([
            'token' => $payloadB64 . '.' . $signature,
        ]);
    }
}