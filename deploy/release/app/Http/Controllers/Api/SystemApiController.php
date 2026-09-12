<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetectedDevice;
use App\Models\EventLog;
use App\Models\PumpLog;
use App\Models\SensorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemApiController extends Controller
{
    /**
     * GET /api/system/detected-devices — MAC yang mencoba akses tapi belum terdaftar.
     */
    public function detectedDevices()
    {
        return response()->json([
            'status' => 'success',
            'data' => DetectedDevice::orderByDesc('last_seen')->get(),
        ]);
    }

    /**
     * GET /api/fingerprint — fingerprint hardware server (untuk audit).
     */
    public function fingerprint()
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'php' => PHP_VERSION,
                'os' => php_uname('s') . ' ' . php_uname('r'),
                'host' => php_uname('n'),
                'laravel' => app()->version(),
                'server_time' => now()->toDateTimeString(),
            ],
        ]);
    }

    /**
     * GET /api/system/cleanup — pembersihan log lama (dipanggil cron).
     */
    public function cleanupLogs()
    {
        $deleted = [
            'sensor_logs' => SensorLog::where('record_time', '<', now()->subDays(90))->count(),
            'pump_logs' => PumpLog::where('timestamp', '<', now()->subDays(90))->count(),
            'event_logs' => EventLog::where('event_time', '<', now()->subDays(30))->count(),
        ];

        SensorLog::where('record_time', '<', now()->subDays(90))->delete();
        PumpLog::where('timestamp', '<', now()->subDays(90))->delete();
        EventLog::where('event_time', '<', now()->subDays(30))->delete();

        // Optimasi tabel
        DB::statement('OPTIMIZE TABLE sensor_logs, pump_logs, event_logs');

        return response()->json(['status' => 'success', 'deleted' => $deleted]);
    }
}
