<?php
// app/Http/Controllers/DatabaseLogController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class DatabaseLogController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::with('causer', 'subject')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('log_name')) {
            $query->where('log_name', $request->log_name);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('causer_type')) {
            $query->where('causer_type', $request->causer_type);
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'LIKE', "%{$search}%")
                  ->orWhere('log_name', 'LIKE', "%{$search}%")
                  ->orWhere('event', 'LIKE', "%{$search}%");
            });
        }

        $logs = $query->paginate(50);

        // Get filter options
        $logNames = Activity::distinct('log_name')->pluck('log_name')->filter();
        $events = Activity::distinct('event')->pluck('event')->filter();
        $causerTypes = Activity::distinct('causer_type')->pluck('causer_type')->filter();
        $subjectTypes = Activity::distinct('subject_type')->pluck('subject_type')->filter();

        return view('logs.database', compact(
            'logs',
            'logNames',
            'events',
            'causerTypes',
            'subjectTypes'
        ));
    }

    public function show(Activity $log)
    {
        return response()->json([
            'id' => $log->id,
            'description' => $log->description,
            'log_name' => $log->log_name,
            'event' => $log->event,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'causer_type' => $log->causer_type,
            'causer_id' => $log->causer_id,
            'causer_name' => $log->causer?->name ?? 'System',
            'properties' => $log->properties,
            'changes' => $this->formatChanges($log->properties),
            'created_at' => $log->created_at->format('Y-m-d H:i:s'),
        ]);
    }

    public function exportCsv(Request $request)
    {
        $query = Activity::with('causer')
            ->orderBy('created_at', 'desc');

        // Apply same filters as index
        if ($request->filled('log_name')) {
            $query->where('log_name', $request->log_name);
        }
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->limit(10000)->get();

        $filename = 'database_logs_' . date('Y-m-d_H-i-s') . '.csv';
        $handle = fopen('php://output', 'w');

        // Headers
        fputcsv($handle, [
            'ID',
            'Log Name',
            'Event',
            'Description',
            'Subject Type',
            'Subject ID',
            'Causer',
            'Changes',
            'Created At'
        ]);

        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->id,
                $log->log_name,
                $log->event,
                $log->description,
                $log->subject_type,
                $log->subject_id,
                $log->causer?->name ?? 'System',
                $this->formatChangesForCsv($log->properties),
                $log->created_at->format('Y-m-d H:i:s')
            ]);
        }

        fclose($handle);

        return response('', 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    private function formatChanges($properties)
    {
        if (!$properties) {
            return null;
        }

        $changes = [];
        
        if (isset($properties['attributes'])) {
            $changes['new'] = $properties['attributes'];
        }
        if (isset($properties['old'])) {
            $changes['old'] = $properties['old'];
        }

        return $changes;
    }

    private function formatChangesForCsv($properties)
    {
        if (!$properties) {
            return '';
        }

        $changes = [];
        
        if (isset($properties['attributes'])) {
            $changes[] = 'New: ' . json_encode($properties['attributes']);
        }
        if (isset($properties['old'])) {
            $changes[] = 'Old: ' . json_encode($properties['old']);
        }

        return implode(' | ', $changes);
    }
}