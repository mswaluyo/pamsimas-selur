<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\GaugeTemplate;
use App\Models\IndicatorSetting;
use App\Models\PumpLog;
use App\Models\Tank;
use App\Models\Pump;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userRole = session('user.role', 'Viewer');
        if ($userRole === 'Kasir') {
            return view('dashboard.kasir');
        }

        $devices = Device::with(['tank', 'pump', 'sensor'])->get();
        $online = $devices->filter(fn ($d) => $d->isOnline())->count();

        $period = now()->format('Y-m');
        $invoicesReadyToPay = \App\Models\Invoice::where('status_bayar', 'BELUM')->where('period', $period)->count();
        $meterPendingValidation = \App\Models\CustomerValidation::whereIn('status', ['MENUNGGU', 'KONFIRMASI', 'DIVALIDASI_WARGA'])
            ->whereYear('updated_at', now()->year)->whereMonth('updated_at', now()->month)->count();

        return view('dashboard.index', [
            'stats' => [
                'total_devices' => $devices->count(),
                'online_devices' => $online,
                'total_tanks' => Tank::count(),
                'total_pumps' => Pump::count(),
                'total_users' => User::count(),
                'invoices_ready_to_pay' => $invoicesReadyToPay,
                'meter_pending_validation' => $meterPendingValidation,
            ],
            'devices' => $devices,
            'indicator_settings' => IndicatorSetting::getSettings(),
            'gaugeTemplate' => GaugeTemplate::where('name', IndicatorSetting::getSettings()['active_template_id'] ?? '')->first(),
        ]);
    }

    /**
     * Live update endpoint untuk polling dashboard (JSON).
     */
    public function data(Request $request)
    {
        $devices = Device::with(['tank', 'pump', 'sensor'])->get();
        $online = 0;

        $devicesData = $devices->map(function (Device $d) use (&$online) {
            $isOnline = $d->isOnline();
            if ($isOnline) $online++;
            $lastPumpLog = PumpLog::where('device_id', $d->id)->orderByDesc('timestamp')->first();
            $lastReading = $d->sensorLogs()->orderByDesc('record_time')->first();

            return [
                'id' => $d->id,
                'mac_address' => $d->mac_address,
                'device_type' => $d->device_type,
                'tank_name' => $d->tank?->tank_name,
                'pump_name' => $d->pump?->pump_name,
                'status' => $d->status,
                'control_mode' => $d->control_mode,
                'rssi' => $d->rssi,
                'uptime' => $d->uptime,
                'free_heap' => $d->free_heap,
                'firmware_version' => $d->firmware_version,
                'is_online' => $isOnline,
                'last_update' => $d->last_update?->toDateTimeString(),
                'last_update_ts' => $d->last_update?->timestamp ?? 0,
                'pump_change_ts' => $lastPumpLog?->timestamp?->timestamp ?? 0,
                'water_percentage' => $lastReading?->water_percentage ?? 0,
                'water_level' => $lastReading?->water_level ?? 0,
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
}
