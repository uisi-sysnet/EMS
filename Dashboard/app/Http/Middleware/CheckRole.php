<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Not logged in
        if (!session('authenticated')) {
            return redirect()->route('login');
        }

        $userRole = session('role');

        // If no specific roles are required, just check if authenticated
        if (empty($roles)) {
            return $next($request);
        }

        // Check if user's role is allowed
        if (!in_array($userRole, $roles)) {
            // User tried to access a restricted page
            abort(403, 'Unauthorized access.');
            // Or redirect back to home:
            // return redirect()->route('home')->with('error', 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}