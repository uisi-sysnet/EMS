<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (empty($roles)) {
            return $next($request);
        }

        if (!in_array(auth()->user()->role, $roles, true)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}