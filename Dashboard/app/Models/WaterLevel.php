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
        'deleted_at'          => 'datetime',
    ];

    /**
     * Route-model binding uses station_mn, not id.
     */
    public function getRouteKeyName(): string
    {
        return 'station_mn';
    }
}