<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Station extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'aq';
    
    protected $table = 'stations';
    protected $primaryKey = 'station_mn';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; 

    protected $fillable = [
        'station_mn', 'station_name', 'enabled', 'latitude', 'longitude',
        'lead_ip', 'lead_port', 'lead_slave', 'updated_at'
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'lead_port' => 'integer',
        'lead_slave' => 'integer',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime', // Add this
    ];

    // Optional: Add a scope to exclude soft-deleted by default (Laravel does this automatically)
    // But if you want to include them in specific queries, use withTrashed()
}