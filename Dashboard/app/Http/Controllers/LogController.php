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

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query
            ->orderBy('created_at', 'desc')
            ->paginate(1000)
            ->withQueryString(); // keeps filters in pagination links

        return view('logs.system', compact('logs'));
    }
}