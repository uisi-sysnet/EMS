<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Last known up/down status per device, used by logs:track-device-status
 * to log only changes.
 */
class DeviceStatusState extends Model
{
    protected $fillable = ['key', 'status', 'changed_at'];

    protected $casts = [
        'changed_at' => 'datetime',
    ];
}
