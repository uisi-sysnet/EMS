<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AllowedIp; 

class AllowedNetworkController extends Controller
{
    /**
     * Display a listing of allowed IPs.
     */
    public function index()
    {
        $keys = ApiKey::orderBy('owner_label')->get();
        $ips  = AllowedIp::orderBy('cidr')->get(); // <-- Fetch allowed IPs
        return view('env.api-editor', compact('keys', 'ips'));
    }

    /**
     * Store a newly created allowed IP.
     */
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

    /**
     * Remove the specified allowed IP.
     */
    public function destroy($cidr)
    {
        $ip = AllowedIp::where('cidr', $cidr)->firstOrFail();
        $ip->delete();

        return response()->json(['success' => true]);
    }
}