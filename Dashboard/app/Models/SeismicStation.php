<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeismicStation extends Model
{
    protected $table = 'seismic_stations';

    protected $primaryKey = 'station_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'station_id',
        'station_name',
        'enabled',
        'latitude',
        'longitude',
        'elevation_m',
    ];

    protected $casts = [
        'enabled'     => 'boolean',
        'latitude'    => 'float',
        'longitude'   => 'float',
        'elevation_m' => 'float',
    ];
}