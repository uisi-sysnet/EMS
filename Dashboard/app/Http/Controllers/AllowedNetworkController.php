<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AllowedIp; // you need to create this model

class AllowedNetworkController extends Controller
{
    /**
     * Display a listing of allowed IPs.
     */
    public function index()
    {
        $ips = AllowedIp::orderBy('cidr')->get();
        return view('env.allowed-networks', compact('ips'));
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