<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every change made through the dashboard (POST/PUT/PATCH/DELETE)
 * in the Audit Log: who, from where, what, and whether it worked.
 *
 * Descriptions and categories come from config/audit.php. Submitted
 * values are kept only for the fields listed in audit.keep_values; every
 * other field is recorded by name only, so passwords, keys and secrets
 * never reach the log. Auditing must never break the request it records,
 * so any failure here is reported and swallowed.
 */
class AuditTrail
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $route = $request->route();
        $routeName = $route?->getName();
        if ($routeName && in_array($routeName, config('audit.ignore', []), true)) {
            return $next($request);
        }

        // Who is acting, captured before the request (logout clears it).
        $actorBefore = [
            'user_id'  => session('user_id'),
            'username' => session('username'),
            'role'     => session('role'),
        ];

        $response = $next($request);

        try {
            $this->record($request, $response, $routeName, $actorBefore);
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    private function record(Request $request, Response $response, ?string $routeName, array $actorBefore): void
    {
        $method = $request->method();
        $path = '/' . ltrim($request->path(), '/');
        $routes = config('audit.routes', []);
        [$category, $template] = $routes[$routeName ?? ''] ?? $routes["{$method} " . ltrim($path, '/')] ?? ['other', null];

        $isLogin = $path === '/login';
        $result = $this->result($response, $isLogin);

        // A login has no actor before the request: use who signed in, or
        // the username that was tried.
        if ($isLogin) {
            $actor = [
                'user_id'  => session('user_id'),
                'username' => session('username') ?? Str::limit((string) $request->input('username'), 64, ''),
                'role'     => session('role'),
            ];
            if ($result === 'failed') {
                $category = 'security';
                $template = 'Failed sign-in attempt';
            }
        } else {
            $actor = $actorBefore;
        }

        $parameters = $this->parameters($request);
        $input = $request->except(['_token', '_method']);
        $kept = Arr::only($input, config('audit.keep_values', []));
        $kept = array_filter($kept, fn ($v) => is_scalar($v) && $v !== '');

        $description = $template
            ? $this->fill($template, $parameters, $kept)
            : "{$method} {$path}";

        AuditLog::create([
            'log_name'     => $category,
            'description'  => Str::limit($description, 500, '…'),
            'event'        => $routeName ?? "{$method} {$path}",
            'result'       => $result,
            'subject_type' => $routeName ? Str::beforeLast($routeName, '.') : null,
            'causer_id'    => is_numeric($actor['user_id']) ? (int) $actor['user_id'] : null,
            'username'     => $actor['username'] ? Str::limit((string) $actor['username'], 64, '') : null,
            'role'         => $actor['role'],
            'ip_address'   => $request->ip(),
            'user_agent'   => Str::limit((string) $request->userAgent(), 255, ''),
            'properties'   => array_filter([
                'method'      => $method,
                'path'        => $path,
                'parameters'  => $parameters ?: null,
                'values'      => $kept ?: null,
                'fields'      => array_values(array_diff(array_keys($input), array_keys($kept))) ?: null,
                'status_code' => $response->getStatusCode(),
                'message'     => $this->message($response, $result),
            ], fn ($v) => $v !== null),
        ]);
    }

    /** success | failed, judged from what the user was shown. */
    private function result(Response $response, bool $isLogin): string
    {
        if ($isLogin) {
            return session('authenticated') ? 'success' : 'failed';
        }
        if ($response->getStatusCode() >= 400) {
            return 'failed';
        }
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data) && (($data['success'] ?? true) === false || ($data['ok'] ?? true) === false)) {
                return 'failed';
            }
        }
        if ($response instanceof RedirectResponse) {
            $errors = session('errors');
            if (($errors && $errors->any()) || session()->has('error')) {
                return 'failed';
            }
        }
        return 'success';
    }

    /** The error shown to the user, for failed actions. */
    private function message(Response $response, string $result): ?string
    {
        if ($result !== 'failed') {
            return null;
        }
        $errors = session('errors');
        if ($errors && $errors->any()) {
            return Str::limit($errors->first(), 300, '…');
        }
        if (session()->has('error')) {
            return Str::limit((string) session('error'), 300, '…');
        }
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            $text = $data['message'] ?? $data['error'] ?? null;
            return is_string($text) ? Str::limit($text, 300, '…') : null;
        }
        return null;
    }

    /** Route parameters as strings, with secrets masked to their last 4 characters. */
    private function parameters(Request $request): array
    {
        $out = [];
        foreach ($request->route()?->parameters() ?? [] as $key => $value) {
            if (is_object($value)) {
                $value = method_exists($value, 'getRouteKey') ? $value->getRouteKey() : null;
            }
            if (!is_scalar($value)) {
                continue;
            }
            $value = (string) $value;
            if (in_array($key, config('audit.mask_parameters', []), true)) {
                $value = '…' . substr($value, -4);
            }
            $out[$key] = $value;
        }
        return $out;
    }

    private function fill(string $template, array $parameters, array $kept): string
    {
        $values = $parameters + $kept;
        $values['name'] = $kept['station_name'] ?? $kept['name'] ?? $kept['username'] ?? null;

        $text = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($values) {
            return isset($values[$m[1]]) ? (string) $values[$m[1]] : '';
        }, $template);

        return trim(preg_replace('/\s{2,}/', ' ', $text));
    }
}
