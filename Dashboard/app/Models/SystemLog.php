<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $connection = 'logs';   
    protected $table = 'service_logs'; 
    public $timestamps = false;

    protected $fillable = [
        'created_at', 'service', 'level', 'logger_name', 'thread_name', 'message'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}