<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     * Save a new or update an existing key.
     * The incoming token is hashed before storage — only the hash is persisted.
     */
    public function save(Request $request)
    {
        $request->validate([
            'owner_label' => 'required|string|max:255',
            'token_hash'  => 'required|string',
        ]);

        $ownerLabel = trim($request->input('owner_label'));
        $plainToken = trim($request->input('token_hash')); // plain token from the form

        // Sanitize label: uppercase, allow only letters, numbers, underscore
        $sanitizedLabel = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $ownerLabel));

        // Hash the plain token — never store the raw value
        $hashedToken = hash('sha256', $plainToken);

        ApiKey::updateOrCreate(
            ['token_hash' => $hashedToken],
            [
                'owner_label' => $sanitizedLabel,
                'enabled'     => true,
            ]
        );

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
     * Generate a random plain API key for the user to copy.
     * This plain value is shown once in the UI; only its hash is saved later.
     */
    public function generate()
    {
        // 40-char hex string (20 random bytes)
        $key = bin2hex(random_bytes(20));
        return response()->json(['key' => $key]);
    }
}