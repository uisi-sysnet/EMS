<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllowedIp extends Model
{
    protected $primaryKey = 'cidr';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['cidr', 'label', 'enabled'];

    public $timestamps = false;   // Keep this – we only have a created_at column

    // Add this to cast created_at to a Carbon instance
    protected $casts = [
        'created_at' => 'datetime',
    ];
}