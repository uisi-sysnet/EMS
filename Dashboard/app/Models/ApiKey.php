<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $connection = 'api';
    /**
     * Disable the updated_at column.
     */
    public const UPDATED_AT = null;

    /**
     * Set token_hash as the primary key.
     */
    protected $primaryKey = 'token_hash';

    /**
     * The primary key is not auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The primary key is a string.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'owner_label',
        'token_hash',
        'enabled',
        // 'created_at' is handled automatically
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'enabled' => 'boolean',
        'created_at' => 'datetime',
    ];
}