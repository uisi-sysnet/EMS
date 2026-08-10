<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class RecentLogsController extends Controller
{
    public function index(Request $request)
    {
        // Fetch 20 most recent API logs
        $apiLogs = ApiLog::latest()->limit(20)->get();
        
        // Fetch 20 most recent System logs
        $systemLogs = SystemLog::latest()->limit(20)->get();

        $seenIds = $this->parseSeenIds($request->input('seen', ''));
        
        // Build log entries from both sources
        $logs = $this->buildLogEntries($apiLogs, $systemLogs);

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
        $systemLogs = SystemLog::latest()->limit(20)->get();
        
        // Count how many of these have seen_at null
        $unseenCount = 0;
        
        foreach ($apiLogs as $log) {
            if ($log->seen_at === null) {
                $unseenCount++;
            }
        }
        
        foreach ($systemLogs as $log) {
            if ($log->seen_at === null) {
                $unseenCount++;
            }
        }

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

        // Build API log entries
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
                'icon'         => '📡', // API icon
                'badge_color'  => 'text-blue-400',
                'badge_text'   => 'API',
            ]);
        }

        // Build System log entries
        foreach ($systemLogs as $log) {
            // Determine level color
            $levelColor = $this->getLevelColor($log->level);
            $levelIcon = $this->getLevelIcon($log->level);
            
            $entries->push([
                'type'         => 'system',
                'id'           => $log->id,
                'summary'      => $log->message,
                'detail'       => 'Service: ' . ($log->service ?? 'N/A') . ' | Logger: ' . ($log->logger_name ?? 'N/A'),
                'time'         => $log->created_at->diffForHumans(),
                'timestamp'    => $log->created_at->toISOString(),
                'url'          => route('logs.index'),
                'status_code'  => $log->level,
                'status_color' => $levelColor,
                'is_seen'      => $log->seen_at !== null,
                'icon'         => $levelIcon,
                'badge_color'  => $levelColor,
                'badge_text'   => strtoupper($log->level ?? 'INFO'),
                'level'        => $log->level,
                'service'      => $log->service,
                'logger_name'  => $log->logger_name,
                'thread_name'  => $log->thread_name,
            ]);
        }

        return $entries;
    }

    private function getLevelColor($level)
    {
        switch (strtolower($level)) {
            case 'emergency':
            case 'alert':
            case 'critical':
                return 'text-red-600';
            case 'error':
                return 'text-red-400';
            case 'warning':
                return 'text-yellow-400';
            case 'notice':
                return 'text-blue-400';
            case 'info':
                return 'text-green-400';
            case 'debug':
                return 'text-gray-400';
            default:
                return 'text-gray-300';
        }
    }

    private function getLevelIcon($level)
    {
        switch (strtolower($level)) {
            case 'emergency':
            case 'alert':
            case 'critical':
                return '🚨';
            case 'error':
                return '❌';
            case 'warning':
                return '⚠️';
            case 'notice':
                return '📢';
            case 'info':
                return 'ℹ️';
            case 'debug':
                return '🐛';
            default:
                return '📝';
        }
    }

    public function markLogAsSeen(Request $request)
    {
        try {
            $type = $request->input('type');
            $id = $request->input('id');
            
            if ($type === 'api') {
                $log = ApiLog::find($id);
            } elseif ($type === 'system') {
                $log = SystemLog::find($id);
            } else {
                return response()->json(['success' => false, 'message' => 'Invalid log type'], 400);
            }
            
            if (!$log) {
                return response()->json(['success' => false, 'message' => 'Log not found'], 404);
            }
            
            $log->update(['seen_at' => now()]);
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Error marking log as seen: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}