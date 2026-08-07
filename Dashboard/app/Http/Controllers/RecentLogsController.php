<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class RecentLogsController extends Controller
{
    public function index(Request $request)
    {
        $apiLogs = ApiLog::latest()->limit(20)->get();
        $systemLogs = SystemLog::latest()->limit(20)->get();

        $seenIds = $this->parseSeenIds($request->input('seen', ''));
        $logs = $this->buildLogEntries($apiLogs, $systemLogs);

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
        $apiLogs = ApiLog::latest()->limit(20)->get();
        $systemLogs = SystemLog::latest()->limit(20)->get();

        $seenIds = $this->parseSeenIds($request->input('seen', ''));
        $logs = $this->buildLogEntries($apiLogs, $systemLogs);

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

        foreach ($systemLogs as $log) {
            // Extract the actual level from the message if the level field is missing or default
            $level = $this->extractLogLevel($log);
            
            $entries->push([
                'type'      => 'system',
                'id'        => $log->id,
                'level'     => $level, // Add the actual level to the log entry
                'summary'   => '[' . strtoupper($level) . '] ' . $log->service,
                'detail'    => substr($log->message, 0, 60) . (strlen($log->message) > 60 ? '…' : ''),
                'time'      => $log->created_at->diffForHumans(),
                'timestamp' => $log->created_at->toISOString(),
                'url'       => route('logs.index'), // clean URL (no highlight)
            ]);
        }

        return $entries;
    }

    /**
     * Extract the actual log level from the message or use the level field
     */
    private function extractLogLevel($log)
    {
        // First, check if the level field exists and is not 'info' (which might be default)
        if (isset($log->level) && $log->level && strtolower($log->level) !== 'info') {
            return strtolower($log->level);
        }
        
        // If level is missing or default 'info', try to extract from message
        $message = $log->message ?? '';
        
        // Check for ERROR in message (case insensitive)
        if (preg_match('/\[ERROR\]/i', $message)) {
            return 'error';
        }
        
        // Check for WARNING in message (case insensitive)
        if (preg_match('/\[WARNING\]/i', $message) || preg_match('/\[WARN\]/i', $message)) {
            return 'warning';
        }
        
        // Check for INFO in message (case insensitive)
        if (preg_match('/\[INFO\]/i', $message)) {
            return 'info';
        }
        
        // If no level found in message, return whatever is in the level field or default to 'info'
        return isset($log->level) && $log->level ? strtolower($log->level) : 'info';
    }
}