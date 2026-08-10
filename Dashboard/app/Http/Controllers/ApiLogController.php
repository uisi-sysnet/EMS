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

    public function exportCsv(Request $request)
    {
        $query = ApiLog::query();

        // Apply ALL the same filters as index()
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

        $logs = $query->orderBy('created_at', 'desc')->get();
        
        // Generate CSV
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="api-logs-' . date('Y-m-d-His') . '.csv"',
        ];
        
        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8 (Excel compatibility)
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Headers
            fputcsv($file, [
                'Date', 'Client IP', 'Method', 'Path', 'Status Code', 
                'Duration (ms)', 'API Key Owner', 'API Key Used'
            ]);
            
            // Data rows
            foreach ($logs as $log) {
                fputcsv($file, [
                    \Carbon\Carbon::parse($log->created_at)->setTimezone('Asia/Manila')->format('Y-m-d H:i:s'),
                    $log->client_ip,
                    $log->method,
                    $log->path,
                    $log->status_code,
                    number_format($log->duration_ms, 2),
                    $log->api_key_owner,
                    $log->api_key_used
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}