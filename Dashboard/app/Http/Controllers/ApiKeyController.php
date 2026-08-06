<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\AllowedIp;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    // ========== API KEY METHODS ==========

    public function index()
    {
        $keys = ApiKey::orderBy('owner_label')->get();
        $ips  = AllowedIp::orderBy('cidr')->get();

        return view('env.api-editor', compact('keys', 'ips'));
    }

    public function save(Request $request)
    {
        $request->validate([
            'owner_label' => 'required|string|max:255',
            'token_hash'  => 'nullable|string',
        ]);

        $ownerLabel = trim($request->input('owner_label'));
        $plainToken = trim($request->input('token_hash'));

        $sanitizedLabel = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $ownerLabel));

        if (empty($plainToken)) {
            $plainToken = bin2hex(random_bytes(20));
        }

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

    public function destroy($token)
    {
        $key = ApiKey::where('token_hash', $token)->firstOrFail();
        $key->delete();
        return response()->json(['success' => true]);
    }

    public function generate()
    {
        return response()->json(['key' => bin2hex(random_bytes(20))]);
    }

    // ========== ALLOWED IP METHODS ==========

    public function store(Request $request)
    {
        $request->validate([
            'cidr'    => 'required|string|max:43|unique:allowed_ips,cidr',
            'label'   => 'required|string|max:100',
            'enabled' => 'sometimes|boolean',
        ]);

        $ip = AllowedIp::create([
            'cidr'    => $request->cidr,
            'label'   => $request->label,
            'enabled' => $request->boolean('enabled', true),
        ]);

        return response()->json(['success' => true, 'ip' => $ip]);
    }

    public function destroyIp($cidr)
    {
        $ip = AllowedIp::where('cidr', $cidr)->firstOrFail();
        $ip->delete();
        return response()->json(['success' => true]);
    }
}