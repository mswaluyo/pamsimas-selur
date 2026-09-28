<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['device_id', 'water_level', 'water_percentage', 'rssi', 'record_time'];

    protected $casts = [
        'record_time' => 'datetime',
    ];

    /** Relasi ke perangkat (dipakai oleh SensorLogController::with('device')). */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
