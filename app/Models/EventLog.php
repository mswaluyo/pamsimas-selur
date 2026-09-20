<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['device_id', 'event_type', 'message', 'event_time'];

    protected $casts = [
        'event_time' => 'datetime',
    ];
}
