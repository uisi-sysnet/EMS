<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaterLevel extends Model
{
    use SoftDeletes;

    /**
     * The EMS database connection (registered at runtime in AppServiceProvider).
     */
    protected $connection = 'water_level';

    /**
     * The table associated with the model.
     */
    protected $table = 'stations';

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'station_mn',
        'station_name',
        'enabled',
        'location',
        'latitude',
        'longitude',
        'installation_height',
        'elevation_height',
        'lead_ip',
        'lead_port',
        'lead_slave',
        'sim_number',
        'report_interval_minutes',
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'enabled'             => 'boolean',
        'latitude'            => 'float',
        'longitude'           => 'float',
        'installation_height' => 'float',
        'elevation_height'    => 'float',
        'lead_port'           => 'integer',
        'lead_slave'          => 'integer',
        'report_interval_minutes'  => 'integer',
        'applied_interval_minutes' => 'integer',
        'interval_sent_at'         => 'datetime',
        'interval_applied_at'      => 'datetime',
        'deleted_at'          => 'datetime',
    ];

    /**
     * Readings in IOT_water_level.sensor_data. Lets the inventory page use
     * withCount('sensorData') for its Data Status column.
     */
    public function sensorData()
    {
        return $this->hasMany(WaterLevelReading::class, 'station_mn', 'station_mn');
    }

    /**
     * True while the dashboard's interval hasn't been confirmed by the sensor.
     */
    public function intervalPending(): bool
    {
        return filled($this->sim_number)
            && $this->applied_interval_minutes !== $this->report_interval_minutes;
    }

    /**
     * Route-model binding uses station_mn, not id.
     */
    public function getRouteKeyName(): string
    {
        return 'station_mn';
    }
}