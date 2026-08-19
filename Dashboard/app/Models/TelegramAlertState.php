<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramAlertState extends Model
{
    protected $fillable = [
        'key',
        'last_status',
        'last_notified_at',
    ];

    protected $casts = [
        'last_notified_at' => 'datetime',
    ];
}