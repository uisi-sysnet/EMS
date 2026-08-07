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

        return view('logs.system', compact('logs', 'defaultFrom', 'defaultTo'));
    }
}