<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $connection = 'logs';   
    protected $table = 'service_logs'; 
    public $timestamps = false;

    protected $fillable = [
        'created_at', 'service', 'level', 'logger_name', 'thread_name', 'message', 'seen_at', 'category'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'seen_at' => 'datetime',
    ];

    // Scope for unseen logs
    public function scopeUnseen($query)
    {
        return $query->whereNull('seen_at');
    }

    /** Levels that notify the user (bell, menu badge, "new" on the Logs page). Warnings and info stay in the logs only. */
    public const ATTENTION_LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    public function scopeNeedsAttention($query)
    {
        return $query->whereIn('level', self::ATTENTION_LEVELS);
    }

    public function isAttentionWorthy(): bool
    {
        return in_array(strtoupper((string) $this->level), self::ATTENTION_LEVELS, true);
    }

    // Helper method to mark as seen
    public function markAsSeen(): void
    {
        $this->update(['seen_at' => now()]);
    }
}