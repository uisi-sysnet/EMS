<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiLogController extends Controller
{
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

        // Get unseen count for notification badge
        $unseenCount = ApiLog::unseen()->count();

        $defaultFrom = ApiLog::min('created_at');
        $defaultTo   = ApiLog::max('created_at');

        $defaultFrom = $defaultFrom ? \Carbon\Carbon::parse($defaultFrom)->toDateString() : null;
        $defaultTo   = $defaultTo   ? \Carbon\Carbon::parse($defaultTo)->toDateString()   : null;

        return view('logs.api', compact('logs', 'defaultFrom', 'defaultTo', 'unseenCount'));
    }

    public function markAsSeen(Request $request)
    {
        try {
            // Mark ALL unseen logs as seen (simplest approach)
            $updated = ApiLog::whereNull('seen_at')->update(['seen_at' => now()]);
            
            return response()->json([
                'success' => true,
                'message' => "Marked {$updated} log(s) as seen"
            ]);
        } catch (\Exception $e) {
            \Log::error('Error marking API logs as seen: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark logs as seen'
            ], 500);
        }
    }

    public function exportCsv(Request $request)
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

        $logs = $query->orderBy('created_at', 'desc')->get();
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="api-logs-' . date('Y-m-d-His') . '.csv"',
        ];
        
        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'Date', 'Client IP', 'Method', 'Path', 'Status Code', 
                'Duration (ms)', 'API Key Owner', 'API Key Used', 'Seen At'
            ]);
            
            foreach ($logs as $log) {
                fputcsv($file, [
                    \Carbon\Carbon::parse($log->created_at)->setTimezone('Asia/Manila')->format('Y-m-d H:i:s'),
                    $log->client_ip,
                    $log->method,
                    $log->path,
                    $log->status_code,
                    number_format($log->duration_ms, 2),
                    $log->api_key_owner,
                    $log->api_key_used,
                    $log->seen_at ? \Carbon\Carbon::parse($log->seen_at)->setTimezone('Asia/Manila')->format('Y-m-d H:i:s') : 'Unseen'
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}