<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory;

    protected $connection = 'aq';
    
    protected $table = 'stations';
    protected $primaryKey = 'station_mn';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; 

    protected $fillable = [
        'station_mn', 'station_name', 'enabled', 'deleted', 'latitude', 'longitude',
        'lead_ip', 'lead_port', 'lead_slave', 'updated_at'
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'deleted' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'lead_port' => 'integer',
        'lead_slave' => 'integer',
        'updated_at' => 'datetime',
    ];

    // Add relationship to SensorData
    public function sensorData()
    {
        return $this->hasMany(SensorData::class, 'station_mn', 'station_mn');
    }

    // Add method to check if station has data
    public function hasData()
    {
        return $this->sensorData()->exists();
    }

    // Optional: Add a scope for stations with data
    public function scopeWithData($query)
    {
        return $query->whereHas('sensorData');
    }

    // Optional: Add a scope for stations without data
    public function scopeWithoutData($query)
    {
        return $query->whereDoesntHave('sensorData');
    }

    public function scopeActive($query)
    {
        return $query->where('deleted', false);
    }

    public function scopeDeleted($query)
    {
        return $query->where('deleted', true);
    }

    public function delete()
    {
        $this->deleted = true;
        return $this->save();
    }

    public function restore()
    {
        $this->deleted = false;
        return $this->save();
    }
}