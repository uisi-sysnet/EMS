<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibrationApi extends Model
{
    protected $connection = 'aq';
    protected $table = 'calibration_apis';

    protected $fillable = [
        'source',
        'api_url',
        'api_token',
        'auth_type',
        'file_path',
        'checklist',
        'total_data',
        'requests_per_min',
    ];

    protected $casts = [
        'checklist' => 'array',
        'total_data' => 'integer',
        'requests_per_min' => 'integer',
    ];
}