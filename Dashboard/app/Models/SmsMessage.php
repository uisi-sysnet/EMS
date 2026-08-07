<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    protected $connection = 'sms';
    protected $table = 'sms_messages';   
    public $timestamps = false;          

    protected $fillable = [
        'received_at', 'sender', 'modem_timestamp', 'raw_body',
        'parsed_ok', 'parse_error', 'station_id'
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'parsed_ok' => 'boolean',
    ];

    // For display, we might want to format the date
    public function getFormattedReceivedAtAttribute()
    {
        return $this->received_at ? $this->received_at->format('H:i') : '';
    }
}