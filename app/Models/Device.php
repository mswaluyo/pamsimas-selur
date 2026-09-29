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

    /**
     * Sinkronkan parameter operasional dari master data yang ditunjuk:
     * - tangki → empty_tank_distance = tinggi tangki
     * - sensor → full_tank_distance & trigger_percentage
     * - pompa  → on_duration & off_duration (detik master → menit, dibulatkan ke atas)
     *
     * Dipakai saat registrasi/edit perangkat, tombol "Sync Master Data", dan saat
     * master data (Pengaturan) diubah supaya perangkat benar-benar menerima angka baru.
     *
     * @return bool true bila ada nilai yang berubah (perubahan sudah disimpan).
     */
    public function syncFromMasterData(): bool
    {
        $this->loadMissing(['tank', 'pump', 'sensor']);

        $changed = false;

        if ($this->tank) {
            $value = (int) $this->tank->height;
            if ((int) $this->empty_tank_distance !== $value) {
                $this->empty_tank_distance = $value;
                $changed = true;
            }
        }

        if ($this->sensor) {
            $full = (int) $this->sensor->full_tank_distance;
            if ((int) $this->full_tank_distance !== $full) {
                $this->full_tank_distance = $full;
                $changed = true;
            }

            $trigger = (int) $this->sensor->trigger_percentage;
            if ((int) $this->trigger_percentage !== $trigger) {
                $this->trigger_percentage = $trigger;
                $changed = true;
            }
        }

        if ($this->pump) {
            $on = (int) ceil((int) $this->pump->on_duration_seconds / 60);
            if ((int) $this->on_duration !== $on) {
                $this->on_duration = $on;
                $changed = true;
            }

            $off = (int) ceil((int) $this->pump->off_duration_seconds / 60);
            if ((int) $this->off_duration !== $off) {
                $this->off_duration = $off;
                $changed = true;
            }
        }

        if ($changed) {
            $this->save();
        }

        return $changed;
    }
}
