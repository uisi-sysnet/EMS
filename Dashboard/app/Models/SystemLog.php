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

    /**
     * Logs the user is notified about (bell, menu badge, "new" on the Logs
     * page): errors from any service, and device problems such as a station
     * or camera going offline. Everything else is kept for reference only.
     */
    public function scopeNeedsAttention($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('level', ['ERROR', 'CRITICAL'])
              ->orWhere(fn ($d) => $d->where('category', 'device')->where('level', 'WARNING'));
        });
    }

    public function isAttentionWorthy(): bool
    {
        return in_array($this->level, ['ERROR', 'CRITICAL'], true)
            || ($this->category === 'device' && $this->level === 'WARNING');
    }

    // Helper method to mark as seen
    public function markAsSeen(): void
    {
        $this->update(['seen_at' => now()]);
    }
}