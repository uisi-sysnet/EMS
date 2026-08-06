<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllowedIp extends Model
{
    protected $primaryKey = 'cidr';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['cidr', 'label', 'enabled'];

    public $timestamps = false; // created_at is managed by DB default
}