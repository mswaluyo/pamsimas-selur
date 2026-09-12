<?php

namespace App\Http\Controllers;

use App\Models\SensorLog;
use Illuminate\Http\Request;

class SensorLogController extends Controller
{
    public function index(Request $request)
    {
        $deviceId = $request->query('device_id');
        $logs = SensorLog::with('device')->when($deviceId, fn ($q) => $q->where('device_id', $deviceId))
            ->orderByDesc('record_time')->paginate(50)->withQueryString();

        return view('logs.sensors', [
            'logs' => $logs,
            'devices' => \App\Models\Device::orderBy('mac_address')->get(),
        ]);
    }
}
