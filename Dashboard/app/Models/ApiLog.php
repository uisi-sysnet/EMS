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

    // 👇 DISABLE timestamps (no updated_at column)
    public $timestamps = false;

    protected $fillable = [
        'client_ip',
        'method',
        'path',
        'status_code',
        'duration_ms',
        'api_key_owner',
        'api_key_used',
        'seen_at',
    ];

    protected $casts = [
        'duration_ms' => 'float',
        'status_code' => 'integer',
        'created_at'  => 'datetime',
        'seen_at'     => 'datetime',
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
            'seen_at'        => null, 
        ]);
    }

    // Helper method to mark as seen
    public function markAsSeen(): void
    {
        $this->update(['seen_at' => now()]);
    }

    // Helper method to mark multiple as seen
    public static function markAllAsSeen(array $ids): void
    {
        static::whereIn('id', $ids)->update(['seen_at' => now()]);
    }

    // Scope for unseen logs
    public function scopeUnseen($query)
    {
        return $query->whereNull('seen_at');
    }
}