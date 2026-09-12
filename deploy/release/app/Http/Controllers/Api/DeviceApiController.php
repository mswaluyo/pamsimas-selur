<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\EventLog;
use App\Models\PumpLog;
use App\Models\SensorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint untuk perangkat ESP8266 (dilindungi X-API-KEY).
 * Port dari DeviceApiController sistem asli.
 */
class DeviceApiController extends Controller
{
    /**
     * POST /api/log — perangkat kirim data level air.
     */
    public function log(Request $request)
    {
        $data = $request->validate([
            'mac_address' => 'required|string|size:17',
            'water_level' => 'required|numeric',
            'water_percentage' => 'required|numeric|min:0|max:100',
            'rssi' => 'nullable|integer',
            'uptime' => 'nullable|numeric',
            'free_heap' => 'nullable|integer',
        ]);

        $device = Device::where('mac_address', strtoupper($data['mac_address']))->first();
        if (!$device) {
            return response()->json(['status' => 'error', 'message' => 'Perangkat belum terdaftar'], 404);
        }

        SensorLog::create([
            'device_id' => $device->id,
            'water_level' => $data['water_level'],
            'water_percentage' => $data['water_percentage'],
            'rssi' => (int) ($data['rssi'] ?? 0),
            'record_time' => now(),
        ]);

        $device->last_update = now();
        $device->rssi = (int) ($data['rssi'] ?? 0);
        if (isset($data['uptime'])) $device->uptime = (int) $data['uptime'];
        if (isset($data['free_heap'])) $device->free_heap = (int) $data['free_heap'];

        // Kontrol pompa otomatis: ON di bawah trigger%, OFF saat penuh
        $newStatus = $device->status;
        if ($device->control_mode === 'AUTO') {
            if ($data['water_percentage'] < $device->trigger_percentage) $newStatus = 'ON';
            elseif ($data['water_percentage'] >= 99) $newStatus = 'OFF';
        }
        $statusChanged = $newStatus !== $device->status;
        $device->status = $newStatus;
        $device->save();

        if ($statusChanged) {
            $this->writePumpLog($device, $newStatus, "Pompa {$newStatus} ({$device->control_mode}) @ {$data['water_percentage']}%");
        }

        $this->aggregate($device->id, (float) $data['water_percentage']);

        return response()->json([
            'status' => 'success',
            'pump_command' => $newStatus,
            'control_mode' => $device->control_mode,
        ]);
    }

    /**
     * GET /api/status?mac_address=XX — firmware menarik konfigurasi & perintah.
     */
    public function status(Request $request)
    {
        $mac = strtoupper((string) $request->query('mac_address', ''));
        $device = Device::where('mac_address', $mac)->first();
        if (!$device) {
            return response()->json(['status' => 'error', 'message' => 'Perangkat belum terdaftar'], 404);
        }

        $device->last_update = now();
        $device->save();

        $response = [
            'status' => 'success',
            'control_mode' => $device->control_mode,
            'pump_command' => $device->control_mode === 'MANUAL' ? $device->status : null,
            'full_tank_distance' => $device->full_tank_distance,
            'empty_tank_distance' => $device->empty_tank_distance,
            'trigger_percentage' => $device->trigger_percentage,
            'min_run_time' => $device->min_run_time,
            'sensor_debounce' => $device->sensor_debounce,
            'report_interval' => $device->report_interval,
            'on_duration' => $device->on_duration,
            'off_duration' => $device->off_duration,
            'restart' => false,
            'config_update' => false,
        ];

        // Perintah one-shot: diberikan sekali lalu di-reset
        if ($device->restart_command) {
            $response['restart'] = true;
            $device->restart_command = false;
        }
        if ($device->config_update_command || $device->mode_update_command) {
            $response['config_update'] = true;
            $device->config_update_command = false;
            $device->mode_update_command = false;
        }
        $device->save();

        return response()->json($response);
    }

    /**
     * POST /api/update — perangkat laporkan status pompa (ON/OFF) + mode.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'mac_address' => 'required|string|size:17',
            'pump_status' => 'required|in:ON,OFF',
            'control_mode' => 'nullable|in:AUTO,MANUAL,TIMED',
            'duration_seconds' => 'nullable|integer|min:0',
        ]);

        $device = Device::where('mac_address', strtoupper($data['mac_address']))->first();
        if (!$device) {
            return response()->json(['status' => 'error', 'message' => 'Perangkat belum terdaftar'], 404);
        }

        $statusChanged = $device->status !== $data['pump_status'];
        $device->status = $data['pump_status'];
        if (isset($data['control_mode'])) $device->control_mode = $data['control_mode'];
        $device->last_update = now();
        $device->save();

        if ($statusChanged) {
            $this->writePumpLog($device, $data['pump_status'], "Pompa {$data['pump_status']} ({$device->control_mode})");
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * POST /api/health — telemetry kesehatan perangkat.
     */
    public function health(Request $request)
    {
        $data = $request->validate([
            'mac_address' => 'required|string|size:17',
            'uptime' => 'nullable|numeric',
            'free_heap' => 'nullable|integer',
            'reset_reason' => 'nullable|string|max:255',
            'firmware_version' => 'nullable|string|max:20',
            'firmware_build_date' => 'nullable|string|max:50',
            'rssi' => 'nullable|integer',
        ]);

        $device = Device::where('mac_address', strtoupper($data['mac_address']))->first();
        if (!$device) {
            return response()->json(['status' => 'error', 'message' => 'Perangkat belum terdaftar'], 404);
        }

        $device->last_update = now();
        $device->uptime = (int) ($data['uptime'] ?? $device->uptime);
        $device->free_heap = (int) ($data['free_heap'] ?? $device->free_heap);
        $device->reset_reason = $data['reset_reason'] ?? $device->reset_reason;
        $device->firmware_version = $data['firmware_version'] ?? $device->firmware_version;
        $device->firmware_build_date = $data['firmware_build_date'] ?? $device->firmware_build_date;
        if (isset($data['rssi'])) $device->rssi = (int) $data['rssi'];
        $device->save();

        return response()->json(['status' => 'success']);
    }

    /**
     * POST /api/log-offline — kirim log buffer saat perangkat offline.
     */
    public function logOffline(Request $request)
    {
        $data = $request->validate([
            'mac_address' => 'required|string|size:17',
            'logs' => 'required|array|max:500',
            'logs.*.water_level' => 'required|numeric',
            'logs.*.water_percentage' => 'required|numeric',
            'logs.*.record_time' => 'nullable|date',
        ]);

        $device = Device::where('mac_address', strtoupper($data['mac_address']))->first();
        if (!$device) {
            return response()->json(['status' => 'error', 'message' => 'Perangkat belum terdaftar'], 404);
        }

        foreach ($data['logs'] as $log) {
            SensorLog::create([
                'device_id' => $device->id,
                'water_level' => $log['water_level'],
                'water_percentage' => $log['water_percentage'],
                'rssi' => $device->rssi,
                'record_time' => $log['record_time'] ?? now()->toDateTimeString(),
            ]);
        }

        $device->last_offline_sync = now();
        $device->last_update = now();
        $device->save();

        EventLog::create([
            'device_id' => $device->id,
            'event_type' => 'Sync',
            'message' => 'Sinkronisasi offline: ' . count($data['logs']) . ' log',
            'event_time' => now(),
        ]);

        return response()->json(['status' => 'success', 'received' => count($data['logs'])]);
    }

    private function writePumpLog(Device $device, string $status, string $message): void
    {
        PumpLog::create([
            'device_id' => $device->id,
            'pump_status' => $status,
            'control_mode' => $device->control_mode,
            'duration_seconds' => 0,
            'timestamp' => now(),
        ]);
        EventLog::create([
            'device_id' => $device->id,
            'event_type' => 'Pump',
            'message' => $message,
            'event_time' => now(),
        ]);
    }

    /**
     * Agregasi log per-menit & per-jam (upsert per kunci waktu).
     */
    private function aggregate(int $deviceId, float $pct): void
    {
        $minuteTs = now()->startOfMinute();
        DB::table('minute_sensor_logs')->updateOrInsert(
            ['device_id' => $deviceId, 'minute_timestamp' => $minuteTs->toDateTimeString()],
            ['avg_water_level' => $pct]
        );

        $hourTs = now()->startOfHour();
        $hourAvg = (float) (DB::table('minute_sensor_logs')
            ->where('device_id', $deviceId)
            ->where('minute_timestamp', '>=', $hourTs->toDateTimeString())
            ->avg('avg_water_level') ?? $pct);

        DB::table('hourly_sensor_logs')->updateOrInsert(
            ['device_id' => $deviceId, 'hour_timestamp' => $hourTs->toDateTimeString()],
            ['avg_water_level' => $hourAvg]
        );
    }
}
