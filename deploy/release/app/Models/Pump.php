<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pump extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'pump_name', 'flow_rate_lps', 'power_hp', 'power_watt',
        'delay_seconds', 'on_duration_seconds', 'off_duration_seconds',
    ];
}
