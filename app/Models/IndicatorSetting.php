<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicatorSetting extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'threshold_low', 'color_low', 'threshold_medium', 'color_medium',
        'color_high', 'active_template_id', 'water_price', 'admin_fee',
    ];

    public static function getSettings(): array
    {
        $s = static::query()->first();
        return $s ? $s->toArray() : [
            'threshold_low' => 30, 'color_low' => '#e74c3c',
            'threshold_medium' => 70, 'color_medium' => '#f39c12',
            'color_high' => '#27ae60', 'active_template_id' => 'tank_gauge',
            'water_price' => 1500, 'admin_fee' => 5000,
        ];
    }
}
