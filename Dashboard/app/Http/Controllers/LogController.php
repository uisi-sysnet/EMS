<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use App\Models\AuditLog;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Logs page with two tabs (?tab=logs | ?tab=audit):
 *
 * - Logs: service_logs — messages from the EMS services and device status
 *   changes, filterable by category (system / device / security).
 * - Audit Log: activity_log — who did what in the dashboard
 *   (App\Http\Middleware\AuditTrail).
 */
class LogController extends Controller
{
    public const LOG_CATEGORIES = [
        'system'   => 'System',
        'device'   => 'Device',
        'security' => 'Security',
    ];

    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'audit' ? 'audit' : 'logs';

        // Shown on both tabs (header badge).
        $unseenCount = SystemLog::needsAttention()->unseen()->count();

        if ($tab === 'audit') {
            $auditLogs = $this->auditQuery($request)
                ->orderByDesc('created_at')
                ->paginate(100)
                ->withQueryString();

            $failedToday = AuditLog::where('result', 'failed')
                ->where('created_at', '>=', now()->startOfDay())
                ->count();

            return view('logs.system', [
                'tab'             => $tab,
                'auditLogs'       => $auditLogs,
                'failedToday'     => $failedToday,
                'auditCategories' => AuditLog::CATEGORIES,
                'unseenCount'     => $unseenCount,
            ]);
        }

        $hasCategory = Schema::connection('logs')->hasColumn('service_logs', 'category');

        $defaultFrom = SystemLog::min('created_at');
        $defaultTo   = SystemLog::max('created_at');
        $defaultFrom = $defaultFrom ? \Carbon\Carbon::parse($defaultFrom)->toDateString() : null;
        $defaultTo   = $defaultTo   ? \Carbon\Carbon::parse($defaultTo)->toDateString()   : null;

        $logs = $this->systemQuery($request, $hasCategory)
            ->orderBy('created_at', 'desc')
            ->paginate(1000)
            ->withQueryString();

        // A full-table count, so cached briefly rather than run on every load.
        $categoryCounts = $hasCategory
            ? \Illuminate\Support\Facades\Cache::remember('logs.category_counts', 60, fn () =>
                SystemLog::selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category')->all())
            : [];

        return view('logs.system', [
            'tab'            => $tab,
            'logs'           => $logs,
            'defaultFrom'    => $defaultFrom,
            'defaultTo'      => $defaultTo,
            'unseenCount'    => $unseenCount,
            'hasCategory'    => $hasCategory,
            'logCategories'  => self::LOG_CATEGORIES,
            'categoryCounts' => $categoryCounts,
        ]);
    }

    private function systemQuery(Request $request, bool $hasCategory)
    {
        $query = SystemLog::query();

        if ($hasCategory && array_key_exists((string) $request->category, self::LOG_CATEGORIES)) {
            $query->where('category', $request->category);
        }
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
        if ($request->filled('search')) {
            $query->where('message', 'ilike', '%' . $request->search . '%');
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return $query;
    }

    private function auditQuery(Request $request)
    {
        $query = AuditLog::query();

        if (array_key_exists((string) $request->category, AuditLog::CATEGORIES)) {
            $query->where('log_name', $request->category);
        }
        if (in_array($request->result, ['success', 'failed'], true)) {
            $query->where('result', $request->result);
        }
        if ($request->filled('user')) {
            $query->where('username', 'ilike', '%' . $request->user . '%');
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', $search)->orWhere('ip_address', 'ilike', $search);
            });
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return $query;
    }

    // Mark logs as seen
    public function markAsSeen(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            $type = $request->input('type', 'system'); // 'api' or 'system'

            $model = $type === 'api' ? ApiLog::class : SystemLog::class;

            if (empty($ids)) {
                $count = $model::unseen()->update(['seen_at' => now()]);
                $message = "All {$count} {$type} logs marked as seen.";
            } else {
                $count = $model::whereIn('id', $ids)->update(['seen_at' => now()]);
                $message = "{$count} selected {$type} logs marked as seen.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'count'   => $count ?? 0,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error marking logs as seen: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error marking logs as seen: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function exportCsv(Request $request)
    {
        if ($request->query('tab') === 'audit') {
            return $this->exportAuditCsv($request);
        }

        $hasCategory = Schema::connection('logs')->hasColumn('service_logs', 'category');
        $logs = $this->systemQuery($request, $hasCategory)->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="system-logs-' . date('Y-m-d-His') . '.csv"',
        ];

        $callback = function () use ($logs, $hasCategory) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));   // UTF-8 BOM for Excel

            fputcsv($file, ['Date', 'Category', 'Service', 'Level', 'Logger', 'Thread', 'Message', 'Seen At']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->created_at->setTimezone('Asia/Manila')->format('Y-m-d H:i:s'),
                    $hasCategory ? (self::LOG_CATEGORIES[$log->category] ?? $log->category) : 'System',
                    $log->service,
                    $log->level,
                    $log->logger_name,
                    $log->thread_name,
                    $log->message,
                    $log->seen_at ? \Carbon\Carbon::parse($log->seen_at)->setTimezone('Asia/Manila')->format('Y-m-d H:i:s') : 'Unseen',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportAuditCsv(Request $request)
    {
        $entries = $this->auditQuery($request)->orderByDesc('created_at')->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-log-' . date('Y-m-d-His') . '.csv"',
        ];

        $callback = function () use ($entries) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['Date', 'User', 'Role', 'Category', 'Action', 'Result', 'Details', 'IP Address']);

            foreach ($entries as $entry) {
                $p = $entry->properties ?? [];
                $details = array_filter([
                    !empty($p['fields']) ? 'Fields: ' . implode(', ', $p['fields']) : null,
                    !empty($p['message']) ? 'Error: ' . $p['message'] : null,
                ]);
                fputcsv($file, [
                    $entry->created_at->setTimezone('Asia/Manila')->format('Y-m-d H:i:s'),
                    $entry->username ?? '—',
                    $entry->role,
                    AuditLog::CATEGORIES[$entry->log_name] ?? $entry->log_name,
                    $entry->description,
                    ucfirst($entry->result),
                    implode(' | ', $details),
                    $entry->ip_address,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
