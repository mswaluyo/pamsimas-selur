<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Device;
use App\Models\IndicatorSetting;
use App\Models\MeterReading;
use App\Models\SensorLog;
use Illuminate\Http\Request;

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

            return [
                'id' => $d->id,
                'mac_address' => $d->mac_address,
                'device_type' => $d->device_type,
                'tank_name' => $d->tank?->tank_name,
                'tank_height' => (float) ($d->tank?->height ?? 0),
                'pump_name' => $d->pump?->pump_name,
                'status' => $d->status,
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
     * GET /api/device/history?device_id=1&range=24h — data grafik.
     */
    public function history(Request $request)
    {
        $deviceId = (int) $request->query('device_id', 0);
        $range = $request->query('range', '24h');
        $since = match ($range) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDay(),
        };

        $query = SensorLog::query()->where('record_time', '>=', $since);
        if ($deviceId > 0) $query->where('device_id', $deviceId);

        $points = $query->orderBy('record_time')
            ->selectRaw("DATE_FORMAT(record_time, '%Y-%m-%d %H:%i') as t, AVG(water_percentage) as pct")
            ->groupBy('t')->limit(720)->get();

        return response()->json([
            'status' => 'success',
            'range' => $range,
            'data' => $points->map(fn ($p) => ['t' => $p->t, 'pct' => round((float) $p->pct, 1)]),
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
