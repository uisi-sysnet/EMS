<?php

namespace App\Http\Controllers\Logs;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class DatabaseLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::query()
            ->with(['causer', 'subject'])
            ->where('log_name', 'database')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', 'like', '%' . $request->subject_type . '%');
        }

        if ($request->filled('causer')) {
            $query->where(function ($q) use ($request) {
                $q->where('causer_id', $request->causer)
                  ->orWhereHas('causer', function ($sub) use ($request) {
                      $sub->where('name', 'like', '%' . $request->causer . '%')
                          ->orWhere('email', 'like', '%' . $request->causer . '%');
                  });
            });
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $logs = $query->paginate(50);
        $totalCount = ActivityLog::where('log_name', 'database')->count();

        return view('logs.database', compact('logs', 'totalCount'));
    }

    public function export(Request $request)
    {
        // Implement CSV/Excel export if needed
        // For now, return a simple JSON response
        $logs = ActivityLog::where('log_name', 'database')
            ->with(['causer', 'subject'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($logs);
    }
}