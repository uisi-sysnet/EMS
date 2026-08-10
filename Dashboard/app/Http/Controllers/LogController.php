<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $query = SystemLog::query();

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('service')) {
            $query->where('service', 'like', '%' . $request->service . '%');
        }

        if ($request->filled('thread')) {
            $query->where('thread_name', 'like', '%' . $request->thread . '%');
        }

        // NEW: filter by logger_name
        if ($request->filled('logger')) {
            $query->where('logger_name', 'like', '%' . $request->logger . '%');
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $defaultFrom = SystemLog::min('created_at');
        $defaultTo   = SystemLog::max('created_at');
        $defaultFrom = $defaultFrom ? \Carbon\Carbon::parse($defaultFrom)->toDateString() : null;
        $defaultTo   = $defaultTo   ? \Carbon\Carbon::parse($defaultTo)->toDateString()   : null;

        $logs = $query
            ->orderBy('created_at', 'desc')
            ->paginate(1000)
            ->withQueryString();

        // Get unseen count for notification badge
        $unseenCount = SystemLog::unseen()->count();

        return view('logs.system', compact('logs', 'defaultFrom', 'defaultTo', 'unseenCount'));
    }

    // Mark logs as seen
    public function markAsSeen(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            
            if (empty($ids)) {
                // Mark all as seen
                $count = SystemLog::unseen()->update(['seen_at' => now()]);
                $message = "All {$count} logs marked as seen.";
            } else {
                $count = SystemLog::whereIn('id', $ids)->update(['seen_at' => now()]);
                $message = "{$count} selected logs marked as seen.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'count' => $count ?? 0
            ]);
        } catch (\Exception $e) {
            \Log::error('Error marking logs as seen: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error marking logs as seen: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportCsv(Request $request)
    {
        $query = SystemLog::query();

        // Apply ALL the same filters as index()
        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('service')) {
            $query->where('service', 'like', '%' . $request->service . '%');
        }

        if ($request->filled('thread')) {
            $query->where('thread_name', 'like', '%' . $request->thread . '%');
        }

        if ($request->filled('logger')) {
            $query->where('logger_name', 'like', '%' . $request->logger . '%');
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
            'Content-Disposition' => 'attachment; filename="system-logs-' . date('Y-m-d-His') . '.csv"',
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8 (Excel compatibility)
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Headers
            fputcsv($file, [
                'Date', 'Service', 'Level', 'Logger', 'Thread', 'Message', 'Seen At'
            ]);
            
            // Data rows
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->created_at->setTimezone('Asia/Manila')->format('Y-m-d H:i:s'),
                    $log->service,
                    $log->level,
                    $log->logger_name,
                    $log->thread_name,
                    $log->message,
                    $log->seen_at ? \Carbon\Carbon::parse($log->seen_at)->setTimezone('Asia/Manila')->format('Y-m-d H:i:s') : 'Unseen'
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}