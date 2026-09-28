<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Device;
use App\Models\IndicatorSetting;
use App\Models\MeterReading;
use App\Models\PumpLog;
use App\Models\SensorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardApiController extends Controller
{
    /**
     * GET /api/dashboard/data — data live untuk polling dashboard web.
     */
    public function data()
    {
        $devices = Device::with(['tank', 'pump', 'sensor'])->get();
        $online = 0;

        $devicesData = $devices->map(function (Device $d) use (&$online) {
            $isOnline = $d->isOnline();
            if ($isOnline) $online++;

            $lastPct = $d->sensorLogs()->orderByDesc('record_time')->value('water_percentage');

            // Interlock satu bak: ACTUATOR tanpa data sendiri mengambil level air
            // dari MONITOR pasangan pada tank_id yang sama (resolusi dari log).
            if (($lastPct === null || (float) $lastPct == 0.0)
                && $d->device_type !== 'MONITOR' && $d->tank_id) {
                $monId = Device::where('tank_id', $d->tank_id)
                    ->where('device_type', 'MONITOR')->value('id');
                if ($monId) {
                    $monPct = SensorLog::where('device_id', $monId)
                        ->orderByDesc('record_time')->value('water_percentage');
                    if ($monPct !== null) $lastPct = $monPct;
                }
            }

            // Waktu transisi status pompa terakhir (badge timer nyala/mati; log hanya ditulis saat status berubah)
            $pumpStatusSince = null;
            $lastPumpLog = PumpLog::where('device_id', $d->id)->orderByDesc('timestamp')->first();
            if ($lastPumpLog && $lastPumpLog->timestamp
                && strtoupper((string) $lastPumpLog->pump_status) === strtoupper((string) $d->status)) {
                $pumpStatusSince = $lastPumpLog->timestamp->timestamp;
            }

            return [
                'id' => $d->id,
                'mac_address' => $d->mac_address,
                'device_type' => $d->device_type,
                'tank_name' => $d->tank?->tank_name,
                'tank_height' => (float) ($d->tank?->height ?? 0),
                'pump_name' => $d->pump?->pump_name,
                'status' => $d->status,
                'pump_status_since' => $pumpStatusSince,
                'control_mode' => $d->control_mode,
                'rssi' => $d->rssi,
                'uptime' => (int) $d->uptime,
                'free_heap' => $d->free_heap,
                'firmware_version' => $d->firmware_version,
                'water_percentage' => (float) ($lastPct ?? 0),
                'trigger_percentage' => $d->trigger_percentage,
                'is_online' => $isOnline,
                'last_update' => $d->last_update?->toDateTimeString(),
                'last_update_ts' => $d->last_update?->timestamp ?? 0,
            ];
        });

        return response()->json([
            'server_time' => now()->format('d M Y, H:i:s'),
            'server_timestamp' => now()->timestamp,
            'stats' => [
                'total_devices' => $devices->count(),
                'online_devices' => $online,
            ],
            'devices' => $devicesData,
            'indicator_settings' => IndicatorSetting::getSettings(),
        ]);
    }

    /**
     * GET /api/device/history?device_id=1&range=1h — riwayat untuk grafik detail perangkat.
     * Format respons mengikuti sistem lama agar kompatibel dengan chart-control:
     * {sensors:[{record_time,water_level,water_percentage}], pumps:[{record_time,status}],
     *  initial_pump_status, trigger, window_start, window_end}.
     * Menerima juga param legacy: id / range menit (60, 360, 1440) / 'live'.
     */
    public function history(Request $request)
    {
        $deviceId = (int) ($request->query('device_id', $request->query('id', 0)));
        if ($deviceId <= 0) {
            return response()->json(['status' => 'error', 'message' => 'device_id wajib diisi'], 400);
        }
        $device = Device::find($deviceId);
        if (!$device) {
            return response()->json(['status' => 'error', 'message' => 'Perangkat tidak ditemukan'], 404);
        }

        $range = (string) $request->query('range', '1h');
        if ($range === '60') $range = '1h';
        elseif ($range === '360') $range = '6h';
        elseif ($range === '1440') $range = '24h';
        $minutes = match ($range) {
            'live' => 15, '1h' => 60, '6h' => 360, '24h' => 1440,
            '7d' => 10080, '30d' => 43200, default => 60,
        };
        $since = now()->subMinutes($minutes);
        $now = now();

        // Interlock satu bak: ACTUATOR meminjam data sensor MONITOR pasangan
        $sensorId = $deviceId;
        if ($device->device_type !== 'MONITOR' && $device->tank_id) {
            $monId = Device::where('tank_id', $device->tank_id)
                ->where('device_type', 'MONITOR')->value('id');
            if ($monId) $sensorId = (int) $monId;
        }

        if ($range === 'live') {
            // Mentah, 15 menit terakhir (maks 400 titik)
            $raw = SensorLog::where('device_id', $sensorId)
                ->where('record_time', '>=', $since)
                ->orderBy('record_time')->limit(400)->get();
            $sensors = $raw->map(fn ($l) => [
                'record_time' => ($l->record_time instanceof \DateTimeInterface ? $l->record_time : \Carbon\Carbon::parse($l->record_time))->format('Y-m-d H:i:s'),
                'water_level' => (float) $l->water_level,
                'water_percentage' => (float) $l->water_percentage,
            ])->values();
        } else {
            // Agregasi per menit (kompatibel SQLite & MySQL), maks 1440 titik
            $driver = DB::connection()->getDriverName();
            $groupExpr = $driver === 'sqlite'
                ? "strftime('%Y-%m-%d %H:%M', record_time)"
                : "DATE_FORMAT(record_time, '%Y-%m-%d %H:%i')";
            $points = SensorLog::query()->where('device_id', $sensorId)
                ->where('record_time', '>=', $since)
                ->orderBy('record_time')
                ->selectRaw("{$groupExpr} as t, AVG(water_percentage) as pct, AVG(water_level) as cm")
                ->groupBy('t')->limit(1440)->get();
            $sensors = $points->map(fn ($p) => [
                'record_time' => $p->t . ':00',
                'water_level' => (float) $p->cm,
                'water_percentage' => (float) $p->pct,
            ])->values();
        }

        // Status pompa sebelum jendela (agar grafik shading tidak mulai dari asumsi OFF)
        $prev = PumpLog::where('device_id', $deviceId)
            ->where('timestamp', '<', $since)
            ->orderByDesc('timestamp')->value('pump_status');
        $pumps = PumpLog::where('device_id', $deviceId)
            ->where('timestamp', '>=', $since)
            ->orderBy('timestamp')->limit(1000)->get()
            ->map(fn ($l) => [
                'record_time' => ($l->timestamp instanceof \DateTimeInterface ? $l->timestamp : \Carbon\Carbon::parse($l->timestamp))->format('Y-m-d H:i:s'),
                'status' => $l->pump_status,
            ])->values();

        // Device offline: status terakhir TIDAK berlaku — jangan lukis pita ON melewati putusnya
        // koneksi. Tutup pita di waktu kontak terakhir; bila offline sejak sebelum jendela, anggap OFF.
        if (! $device->isOnline() && $device->last_update) {
            if ($device->last_update->lt($since)) {
                $prev = 'OFF';
            } else {
                $pumps->push([
                    'record_time' => $device->last_update->format('Y-m-d H:i:s'),
                    'status' => 'OFF',
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'range' => $range,
            'sensors' => $sensors,
            'pumps' => $pumps,
            'initial_pump_status' => $prev ?: 'OFF',
            'trigger' => (int) ($device->trigger_percentage ?? 70),
            'window_start' => $since->toDateTimeString(),
            'window_end' => $now->toDateTimeString(),
        ]);
    }

    /**
     * GET /api/meter/last/{customerId} — pembacaan meter terakhir.
     */
    public function lastReading(string $customerId)
    {
        $reading = MeterReading::where('customer_id', $customerId)->orderByDesc('period')->first();
        return response()->json([
            'status' => 'success',
            'data' => $reading ? [
                'period' => $reading->period,
                'current_meter' => (float) $reading->current_meter,
            ] : null,
        ]);
    }
}
