<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $connection = 'logs';   
    protected $table = 'service_logs'; 
    public $timestamps = false;

    protected $fillable = [
        'created_at', 'service', 'level', 'logger_name', 'thread_name', 'message', 'seen_at'
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

    // Helper method to mark as seen
    public function markAsSeen(): void
    {
        $this->update(['seen_at' => now()]);
    }
}