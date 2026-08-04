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
            $entries->push([
                'type'      => 'system',
                'id'        => $log->id,
                'summary'   => '[' . strtoupper($log->level) . '] ' . $log->service,
                'detail'    => substr($log->message, 0, 60) . (strlen($log->message) > 60 ? '…' : ''),
                'time'      => $log->created_at->diffForHumans(),
                'timestamp' => $log->created_at->toISOString(),
                'url'       => route('logs.index'), // clean URL (no highlight)
            ]);
        }

        return $entries;
    }
}