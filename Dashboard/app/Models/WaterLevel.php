<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterLevelSensorData extends Model
{
    protected $connection = 'water_level';
    protected $table      = 'sensor_data';

    protected $fillable = [
        'station_mn',
        'water_level',
        'battery_voltage',
        'temperature',
        'recorded_at',
    ];

    protected $casts = [
        'water_level'     => 'float',
        'battery_voltage' => 'float',
        'temperature'     => 'float',
        'recorded_at'     => 'datetime',
    ];

    public function station()
    {
        return $this->belongsTo(WaterLevel::class, 'station_mn', 'station_mn');
    }
}