<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiCalibration extends Model
{
    use HasFactory;

    /**
     * The database connection used by the model.
     */
    protected $connection = 'aq';

    /**
     * The table associated with the model.
     */
    protected $table = 'api_calibration';

    /**
     * Indicates if the model should be timestamped.
     * We only have 'created_at' (no 'updated_at'), so we disable auto-timestamps
     * and manually handle created_at if needed.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
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
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'data_time' => 'datetime',
        'created_at' => 'datetime',
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

    /**
     * Get the station that owns the calibration record.
     */
    public function station()
    {
        return $this->belongsTo(Station::class, 'station_mn', 'station_mn');
    }

    /**
     * Scope a query to filter by station.
     */
    public function scopeForStation($query, $stationMn)
    {
        return $query->where('station_mn', $stationMn);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateBetween($query, $from, $to)
    {
        return $query->whereBetween('data_time', [$from, $to]);
    }

    /**
     * Scope a query to get the latest record per station.
     */
    public function scopeLatestPerStation($query)
    {
        return $query->orderBy('data_time', 'desc');
    }
}