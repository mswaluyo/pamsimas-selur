<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sensor extends Model
{
    protected $fillable = [
        'sensor_name', 'sensor_type', 'full_tank_distance', 'trigger_percentage',
    ];
}
