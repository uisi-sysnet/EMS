<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log; 

class ApiKeyController extends Controller
{
    /**
     * Show the API key manager view with all keys.
     */
    public function index()
    {
        $keys = ApiKey::orderBy('owner_label')->get();
        return view('env.api-editor', compact('keys'));
    }

    /**
     * Save a new API key.
     * If token_hash is not provided, generate a random key and hash it.
     */
    public function save(Request $request)
    {
        $request->validate([
            'owner_label' => 'required|string|max:255',
            // token_hash is now optional – if omitted, we generate a new key
            'token_hash'  => 'nullable|string',
        ]);

        $ownerLabel = trim($request->input('owner_label'));
        $plainToken = trim($request->input('token_hash'));

        // Sanitize label: uppercase, allow only letters, numbers, underscore
        $sanitizedLabel = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $ownerLabel));

        // If no token provided, generate one
        if (empty($plainToken)) {
            $plainToken = bin2hex(random_bytes(20)); // 40-char hex
        }

        // Hash the plain token – never store raw
        $hashedToken = hash('sha256', $plainToken);

        // Save (avoid duplicate hashes, though extremely unlikely)
        ApiKey::updateOrCreate(
            ['token_hash' => $hashedToken],
            [
                'owner_label' => $sanitizedLabel,
                'enabled'     => true,
            ]
        );

        // For security, we do NOT return the plain token.
        return response()->json(['success' => true]);
    }

    /**
     * Delete an API key.
     * $token is the already-hashed value stored in the DB (shown in the table).
     */
    public function destroy($token)
    {
        $key = ApiKey::where('token_hash', $token)->firstOrFail();
        $key->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Generate a random plain API key (utility endpoint – kept for any other use).
     */
    public function generate()
    {
        $key = bin2hex(random_bytes(20));
        return response()->json(['key' => $key]);
    }

    /**
     * Toggle the enabled status of an API key.
     */
    public function toggle(Request $request, $token)
    {
        try {
            Log::info('API Key toggle attempt', ['token' => $token, 'enabled' => $request->input('enabled')]);
            $key = ApiKey::where('token_hash', $token)->firstOrFail();
            $key->enabled = $request->input('enabled', !$key->enabled);
            $key->save();
            Log::info('API Key toggled', ['token' => $token, 'new_status' => $key->enabled]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('API Key toggle failed', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
}