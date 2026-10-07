<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One user action in the dashboard (Logs page › Audit Log tab).
 *
 * Stored in the activity_log table on the default connection (IOT_api),
 * written by App\Http\Middleware\AuditTrail. log_name holds the audit
 * category (authentication, users, stations, ...).
 */
class AuditLog extends Model
{
    protected $table = 'activity_log';

    protected $fillable = [
        'log_name', 'description', 'event', 'result',
        'subject_type', 'subject_id',
        'causer_id', 'username', 'role', 'ip_address', 'user_agent',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public const CATEGORIES = [
        'authentication' => 'Authentication',
        'security'       => 'Security',
        'users'          => 'Users',
        'stations'       => 'Stations',
        'cameras'        => 'Cameras',
        'settings'       => 'Settings',
        'network'        => 'Network',
        'services'       => 'Services',
        'maintenance'    => 'Maintenance',
        'other'          => 'Other',
    ];
}
