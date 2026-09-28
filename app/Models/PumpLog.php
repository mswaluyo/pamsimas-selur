<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PumpLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['device_id', 'pump_status', 'control_mode', 'duration_seconds', 'timestamp'];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    /** Relasi ke perangkat (dipakai oleh PumpLogController::with('device')). */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
