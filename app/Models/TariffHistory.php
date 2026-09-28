<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TariffHistory extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'water_price', 'admin_fee', 'old_water_price', 'old_admin_fee',
        'changed_by', 'created_at',
    ];
    protected $casts = [
        'water_price' => 'float',
        'admin_fee' => 'float',
        'old_water_price' => 'float',
        'old_admin_fee' => 'float',
        'created_at' => 'datetime',
    ];
}
