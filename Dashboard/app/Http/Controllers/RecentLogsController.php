<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class RecentLogsController extends Controller
{
    public function index(Request $request)
    {
        // Only fetch API logs, 20 most recent
        $apiLogs = ApiLog::latest()->limit(20)->get();

        $seenIds = $this->parseSeenIds($request->input('seen', ''));
        $logs = $this->buildLogEntries($apiLogs, collect()); // Empty collection for system logs

        $unseen = $logs->filter(function ($log) use ($seenIds) {
            return !in_array($log['type'] . '-' . $log['id'], $seenIds);
        })->sortByDesc('timestamp');

        // If 'all' is present, return all unseen; otherwise limit to 20
        if (!$request->has('all')) {
            $unseen = $unseen->take(20);
        }

        return response()->json($unseen->values());
    }

    public function count(Request $request)
    {
        // Only count API logs
        $apiLogs = ApiLog::latest()->limit(20)->get();

        $seenIds = $this->parseSeenIds($request->input('seen', ''));
        $logs = $this->buildLogEntries($apiLogs, collect()); // Empty collection for system logs

        $unseenCount = $logs->filter(function ($log) use ($seenIds) {
            return !in_array($log['type'] . '-' . $log['id'], $seenIds);
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
            $entries->push([
                'type'      => 'api',
                'id'        => $log->id,
                'summary'   => $log->method . ' ' . $log->path . ' → ' . $log->status_code,
                'detail'    => 'IP: ' . $log->client_ip . ' | ' . round($log->duration_ms, 2) . 'ms',
                'time'      => $log->created_at->diffForHumans(),
                'timestamp' => $log->created_at->toISOString(),
                'url'       => route('api-logs.index'), // clean URL (no highlight)
            ]);
        }

        // Remove system logs processing entirely
        // foreach ($systemLogs as $log) { ... }

        return $entries;
    }

    /**
     * Extract the actual log level from the message or use the level field
     */
    private function extractLogLevel($log)
    {
        // This method is no longer needed for API-only logs
        return 'info';
    }
}