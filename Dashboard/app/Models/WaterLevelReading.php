<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One water level reading (IOT_water_level.sensor_data), written by
 * scripts/water_level_gsm.py from the sensors' SMS.
 */
class WaterLevelReading extends Model
{
    protected $connection = 'water_level';

    protected $table = 'sensor_data';

    public $timestamps = false;

    protected $casts = [
        'water_level'     => 'float',
        'distance'        => 'float',
        'battery_voltage' => 'float',
        'temperature'     => 'float',
        'seq'             => 'integer',
        'recorded_at'     => 'datetime',
    ];
}
