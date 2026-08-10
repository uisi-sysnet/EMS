<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class RecentLogsController extends Controller
{
    public function index(Request $request)
    {
        // Always fetch the 20 most recent API logs (regardless of seen status)
        $apiLogs = ApiLog::latest()->limit(20)->get();

        $seenIds = $this->parseSeenIds($request->input('seen', ''));
        $logs = $this->buildLogEntries($apiLogs, collect());

        // Filter out logs that are in the seen list (client-side tracking)
        // but keep them in the response, just mark them as seen
        $logs = $logs->map(function ($log) use ($seenIds) {
            // Check if this log is in the seen list
            $isSeen = in_array($log['type'] . '-' . $log['id'], $seenIds);
            // Also check if seen_at is null in the database
            $log['is_seen'] = $isSeen || $log['is_seen'];
            return $log;
        })->sortByDesc('timestamp')->values();

        return response()->json($logs);
    }

    public function count(Request $request)
    {
        // Get the 20 most recent API logs
        $apiLogs = ApiLog::latest()->limit(20)->get();
        
        // Count how many of these have seen_at null
        $unseenCount = $apiLogs->filter(function ($log) {
            return $log->seen_at === null;
        })->count();

        return response()->json(['count' => $unseenCount]);
    }

    private function parseSeenIds($input)
    {
        if (empty($input)) return [];
        return array_filter(explode(',', $input));
    }

    private function buildLogEntries($apiLogs, $systemLogs)
    {
        $entries = collect();

        foreach ($apiLogs as $log) {
            // Determine status color based on status code
            $statusColor = $log->status_code >= 400 ? 'text-red-400' : 'text-green-400';
            
            $entries->push([
                'type'         => 'api',
                'id'           => $log->id,
                'summary'      => $log->method . ' ' . $log->path,
                'detail'       => 'Status: ' . $log->status_code . ' | IP: ' . $log->client_ip . ' | ' . round($log->duration_ms, 2) . 'ms',
                'time'         => $log->created_at->diffForHumans(),
                'timestamp'    => $log->created_at->toISOString(),
                'url'          => route('api-logs.index'),
                'status_code'  => $log->status_code,
                'status_color' => $statusColor,
                'is_seen'      => $log->seen_at !== null,
            ]);
        }

        return $entries;
    }
}