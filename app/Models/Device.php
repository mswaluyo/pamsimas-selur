<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'mac_address', 'device_type', 'tank_id', 'pump_id', 'sensor_id',
        'status', 'control_mode', 'last_update', 'rssi',
        'full_tank_distance', 'empty_tank_distance', 'trigger_percentage',
        'min_run_time', 'sensor_debounce', 'report_interval',
        'on_duration', 'off_duration',
        'restart_command', 'config_update_command', 'mode_update_command',
        'uptime', 'free_heap', 'reset_reason',
        'firmware_version', 'firmware_build_date', 'last_offline_sync',
    ];

    protected $casts = [
        'restart_command' => 'boolean',
        'config_update_command' => 'boolean',
        'mode_update_command' => 'boolean',
        'last_update' => 'datetime',
        'last_offline_sync' => 'datetime',
    ];

    public function tank()
    {
        return $this->belongsTo(Tank::class, 'tank_id');
    }

    public function pump()
    {
        return $this->belongsTo(Pump::class, 'pump_id');
    }

    public function sensor()
    {
        return $this->belongsTo(Sensor::class, 'sensor_id');
    }

    public function sensorLogs()
    {
        return $this->hasMany(SensorLog::class, 'device_id');
    }

    public function pumpLogs()
    {
        return $this->hasMany(PumpLog::class, 'device_id');
    }

    public function isOnline(): bool
    {
        return $this->last_update !== null
            && abs(now()->diffInSeconds($this->last_update)) < 300;
    }
}
