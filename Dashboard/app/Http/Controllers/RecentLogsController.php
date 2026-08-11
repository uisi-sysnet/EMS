<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class RecentLogsController extends Controller
{
    public function index(Request $request)
    {
        // Fetch 50 most recent API logs
        $apiLogs = ApiLog::latest('created_at')->limit(50)->get();
        
        // Fetch 50 most recent System logs
        $systemLogs = SystemLog::latest('created_at')->limit(50)->get();

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
        })
        // Sort by timestamp (created_at) in descending order (newest first)
        // This sorts across both API and System logs by date
        ->sortByDesc('timestamp')
        // Take the 50 most recent entries overall (combined from both sources)
        ->take(50)
        ->values();

        return response()->json($logs);
    }

    public function count(Request $request)
    {
        // Get the 50 most recent API logs
        $apiLogs = ApiLog::latest('created_at')->limit(50)->get();
        $systemLogs = SystemLog::latest('created_at')->limit(50)->get();
        
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
            
            // Create a unique identifier using available fields
            $uniqueId = 'api_' . md5($log->created_at . $log->client_ip . $log->method . $log->path);
            
            $entries->push([
                'type'         => 'api',
                'id'           => $uniqueId, // Use hashed identifier instead of DB id
                'summary'      => $log->method . ' ' . $log->path,
                'detail'       => 'Status: ' . $log->status_code . ' | IP: ' . $log->client_ip . ' | ' . round($log->duration_ms, 2) . 'ms',
                'time'         => $log->created_at->diffForHumans(),
                'timestamp'    => $log->created_at->toISOString(),
                'url'          => route('api-logs.index'),
                'status_code'  => $log->status_code,
                'status_color' => $statusColor,
                'is_seen'      => $log->seen_at !== null,
                'badge_color'  => 'text-blue-400',
                'badge_text'   => 'API',
                // Store the actual log data for marking
                '_log_data'    => [
                    'client_ip' => $log->client_ip,
                    'created_at' => $log->created_at,
                    'method' => $log->method,
                    'path' => $log->path
                ]
            ]);
        }

        // Build System log entries
        foreach ($systemLogs as $log) {
            // Determine level color
            $levelColor = $this->getLevelColor($log->level);
            
            // Create a unique identifier using available fields
            $uniqueId = 'system_' . md5($log->created_at . $log->service . $log->message . $log->level);
            
            $entries->push([
                'type'         => 'system',
                'id'           => $uniqueId, // Use hashed identifier instead of DB id
                'summary'      => $log->message,
                'detail'       => 'Service: ' . ($log->service ?? 'N/A') . ' | Logger: ' . ($log->logger_name ?? 'N/A'),
                'time'         => $log->created_at->diffForHumans(),
                'timestamp'    => $log->created_at->toISOString(),
                'url'          => route('logs.index'),
                'status_code'  => $log->level,
                'status_color' => $levelColor,
                'is_seen'      => $log->seen_at !== null,
                'badge_color'  => $levelColor,
                'badge_text'   => strtoupper($log->level ?? 'INFO'),
                'level'        => $log->level,
                'service'      => $log->service,
                'logger_name'  => $log->logger_name,
                'thread_name'  => $log->thread_name,
                // Store the actual log data for marking
                '_log_data'    => [
                    'created_at' => $log->created_at,
                    'service' => $log->service,
                    'level' => $log->level,
                    'message' => $log->message
                ]
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

    public function markLogAsSeen(Request $request)
    {
        try {
            $type = $request->input('type');
            $logData = $request->input('log_data');
            
            if ($type === 'api') {
                // Find the log by its unique combination of fields
                $log = ApiLog::where([
                    'client_ip' => $logData['client_ip'],
                    'created_at' => $logData['created_at'],
                    'method' => $logData['method'],
                    'path' => $logData['path']
                ])->first();
                
                if ($log && $log->seen_at === null) {
                    $log->update(['seen_at' => now()]);
                }
            } elseif ($type === 'system') {
                // Find the log by its unique combination of fields
                $log = SystemLog::where([
                    'created_at' => $logData['created_at'],
                    'service' => $logData['service'],
                    'level' => $logData['level'],
                    'message' => $logData['message']
                ])->first();
                
                if ($log && $log->seen_at === null) {
                    $log->update(['seen_at' => now()]);
                }
            } else {
                return response()->json(['success' => false, 'message' => 'Invalid log type'], 400);
            }
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Error marking log as seen: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function markAllAsSeen(Request $request)
    {
        try {
            $type = $request->input('type');
            
            if ($type === 'api') {
                // Mark all unseen API logs as seen
                ApiLog::unseen()->update(['seen_at' => now()]);
            } elseif ($type === 'system') {
                // Mark all unseen system logs as seen
                SystemLog::unseen()->update(['seen_at' => now()]);
            } elseif ($type === 'all') {
                // Mark all unseen logs from both tables as seen
                ApiLog::unseen()->update(['seen_at' => now()]);
                SystemLog::unseen()->update(['seen_at' => now()]);
            } else {
                return response()->json(['success' => false, 'message' => 'Invalid type'], 400);
            }
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Error marking all logs as seen: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}