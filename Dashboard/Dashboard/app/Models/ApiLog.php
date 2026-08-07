<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiLog extends Model
{
    // 👇 Tell Eloquent which table to use
    protected $connection = 'api';
    protected $table = 'api_request_logs';

    protected $fillable = [
        'client_ip',
        'method',
        'path',
        'status_code',
        'duration_ms',
        'api_key_owner',
        'api_key_used',
    ];

    protected $casts = [
        'duration_ms' => 'float',
        'status_code' => 'integer',
        'created_at'  => 'datetime',
    ];

    /**
     * Convenience method to log an API request/response from middleware.
     */
    public static function logRequest(
        Request $request,
        Response $response,
        float $durationMs,
        ?string $apiKeyOwner = null,
        ?string $apiKeyUsed = null
    ): self {
        return self::create([
            'client_ip'      => $request->ip(),
            'method'         => $request->method(),
            'path'           => $request->path(),
            'status_code'    => $response->getStatusCode(),
            'duration_ms'    => $durationMs,
            'api_key_owner'  => $apiKeyOwner,
            'api_key_used'   => $apiKeyUsed ?? $request->header('X-API-Key'),
        ]);
    }
}