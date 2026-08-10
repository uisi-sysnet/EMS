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

    // Mark a single log as seen
    public function markSingleAsSeen(Request $request)
    {
        try {
            // Debug: Log the incoming request
            Log::info('markSingleAsSeen called', [
                'all_input' => $request->all(),
                'json' => $request->json()->all(),
                'content_type' => $request->header('Content-Type')
            ]);

            $id = $request->input('id');
            
            // Try to get ID from JSON if not found in input
            if (!$id && $request->isJson()) {
                $data = $request->json()->all();
                $id = $data['id'] ?? null;
            }

            if (!$id) {
                Log::warning('Log ID not provided in request', [
                    'request_data' => $request->all(),
                    'json_data' => $request->json()->all()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Log ID is required'
                ], 400);
            }

            $log = ApiLog::find($id);
            
            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => 'Log not found'
                ], 404);
            }

            if ($log->seen_at) {
                return response()->json([
                    'success' => true,
                    'message' => 'Log already marked as seen',
                    'already_seen' => true
                ]);
            }

            // Use the model's markAsSeen method
            $log->markAsSeen();

            return response()->json([
                'success' => true,
                'message' => 'Log marked as seen successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking log as seen: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error marking log as seen: ' . $e->getMessage()
            ], 500);
        }
    }

    // Mark logs as seen (bulk)
    public function markAsSeen(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            
            // Try to get IDs from JSON if not found in input
            if (empty($ids) && $request->isJson()) {
                $data = $request->json()->all();
                $ids = $data['ids'] ?? [];
            }

            if (empty($ids)) {
                // Mark all as seen
                $count = ApiLog::unseen()->count();
                ApiLog::unseen()->update(['seen_at' => now()]);
                $message = "All {$count} logs marked as seen.";
            } else {
                $count = ApiLog::whereIn('id', $ids)->update(['seen_at' => now()]);
                $message = "{$count} selected logs marked as seen.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'count' => $count ?? 0
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking logs as seen: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error marking logs as seen: ' . $e->getMessage()
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