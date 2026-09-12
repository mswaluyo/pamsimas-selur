<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'alert_type', 'title', 'message', 'device_id', 'severity', 'status', 'created_at', 'resolved_at',
    ];
    public $timestamps = false;
}
