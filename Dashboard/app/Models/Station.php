<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory;

    // Define the table name (optional, Laravel assumes 'stations')
    protected $table = 'stations';

    // Set the primary key column
    protected $primaryKey = 'station_mn';

    // The primary key is not an incrementing integer
    public $incrementing = false;

    // The primary key is a string
    protected $keyType = 'string';

    // Disable timestamps if you don't have created_at/updated_at columns
    // You have 'updated_at' only; you can keep it or disable timestamps.
    // If you want to use updated_at, keep $timestamps = true (default) 
    // and add 'updated_at' to fillable.
    public $timestamps = true;

    // Allow mass assignment for these columns
    protected $fillable = [
        'station_mn',
        'station_name',
        'enabled',
        'latitude',
        'longitude',
        'lead_ip',
        'lead_port',
        'lead_slave',
        'updated_at',
    ];

    // Cast attributes to appropriate types
    protected $casts = [
        'enabled' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'lead_port' => 'integer',
        'lead_slave' => 'integer',
        'updated_at' => 'datetime',
    ];
}