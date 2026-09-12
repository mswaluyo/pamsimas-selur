<?php

namespace App\Http\Controllers;

use App\Models\PumpLog;
use Illuminate\Http\Request;

class PumpLogController extends Controller
{
    public function index(Request $request)
    {
        $deviceId = $request->query('device_id');
        $logs = PumpLog::with('device')->when($deviceId, fn ($q) => $q->where('device_id', $deviceId))
            ->orderByDesc('timestamp')->paginate(50)->withQueryString();

        return view('logs.pumps', [
            'logs' => $logs,
            'devices' => \App\Models\Device::orderBy('mac_address')->get(),
        ]);
    }
}
