<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use Illuminate\Http\Request;

class ApiLogController extends Controller
{
    /**
     * Display a paginated list of API logs (for the Blade view).
     */
    public function index(Request $request)
    {
        $query = ApiLog::query();

        if ($request->filled('client_ip')) {
            $query->where('client_ip', 'like', '%' . $request->client_ip . '%');
        }

        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        if ($request->filled('path')) {
            $query->where('path', 'like', '%' . $request->path . '%');
        }

        if ($request->filled('status_code')) {
            $query->where('status_code', $request->status_code);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query
            ->orderBy('created_at', 'desc')
            ->paginate(1000)
            ->withQueryString();

        $defaultFrom = ApiLog::min('created_at');
        $defaultTo   = ApiLog::max('created_at');

        // Convert to 'Y-m-d' format for the date input, or keep null if no logs exist
        $defaultFrom = $defaultFrom ? \Carbon\Carbon::parse($defaultFrom)->toDateString() : null;
        $defaultTo   = $defaultTo   ? \Carbon\Carbon::parse($defaultTo)->toDateString()   : null;

        // Pass them to the view
        return view('logs.api', compact('logs', 'defaultFrom', 'defaultTo'));
    }

    /**
     * (Optional) Manual log insertion, e.g. from a CLI command or external source.
     * POST /api-logs
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_ip'      => 'required|ip',
            'method'         => 'required|string|max:10',
            'path'           => 'required|string',
            'status_code'    => 'required|integer',
            'duration_ms'    => 'required|numeric',
            'api_key_owner'  => 'nullable|string|max:100',
            'api_key_used'   => 'nullable|string|max:100',
        ]);

        $log = ApiLog::create($validated);

        return response()->json($log, 201);
    }
}