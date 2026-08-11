<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SensorData extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sensor_data';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'station_mn',
        'ip_address',
        'data_time',
        'pm25',
        'pm10',
        'tsp',
        'ozone',
        'carbon_monoxide',
        'sulfur_dioxide',
        'nitrogen_dioxide',
        'temperature',
        'humidity',
        'rain',
        'wind_speed',
        'wind_direction',
        'air_pressure',
        'noise',
        'lead',
        'lead_temperature',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data_time' => 'datetime',
        'pm25' => 'float',
        'pm10' => 'float',
        'tsp' => 'float',
        'ozone' => 'float',
        'carbon_monoxide' => 'float',
        'sulfur_dioxide' => 'float',
        'nitrogen_dioxide' => 'float',
        'temperature' => 'float',
        'humidity' => 'float',
        'rain' => 'float',
        'wind_speed' => 'float',
        'wind_direction' => 'float',
        'air_pressure' => 'float',
        'noise' => 'float',
        'lead' => 'float',
        'lead_temperature' => 'float',
    ];
}