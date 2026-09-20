<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    public function overview()
    {
        return view('monitoring.overview', [
            'devices' => \App\Models\Device::with('tank')->get(),
        ]);
    }

    public function system()
    {
        return view('monitoring.system', [
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'serverSoftware' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
            'memoryUsage' => round(memory_get_peak_usage(true) / 1048576, 2) . ' MB',
            'diskFree' => round(disk_free_space(base_path()) / 1073741824, 2) . ' GB',
            'diskTotal' => round(disk_total_space(base_path()) / 1073741824, 2) . ' GB',
            'timezone' => config('app.timezone'),
        ]);
    }

    public function database()
    {
        $driver = DB::connection()->getDriverName();

        // SQLite (dipakai di STB Armbian): tidak ada SHOW TABLE STATUS.
        if ($driver === 'sqlite') {
            $names = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"))
                ->pluck('name');
            $tables = $names->map(function ($name) {
                try {
                    $rows = (int) DB::table($name)->count();
                } catch (\Throwable $e) {
                    $rows = 0;
                }
                return ['name' => $name, 'rows' => $rows, 'size_mb' => 0, 'engine' => 'sqlite'];
            });
            $dbFile = config('database.connections.sqlite.database', database_path('database.sqlite'));
            $dbSizeMb = is_string($dbFile) && is_file($dbFile) ? round(filesize($dbFile) / 1048576, 2) : 0;

            return view('monitoring.database', ['tables' => $tables, 'dbSizeMb' => $dbSizeMb]);
        }

        $tables = DB::select('SHOW TABLE STATUS');
        return view('monitoring.database', [
            'tables' => collect($tables)->map(fn ($t) => [
                'name' => $t->Name,
                'rows' => $t->Rows,
                'size_mb' => round(($t->Data_length + $t->Index_length) / 1048576, 2),
                'engine' => $t->Engine,
            ]),
            'dbSizeMb' => round(collect($tables)->sum(fn ($t) => $t->Data_length + $t->Index_length) / 1048576, 2),
        ]);
    }

    public function performance()
    {
        $startTime = microtime(true);
        DB::select('SELECT 1');
        $queryMs = round((microtime(true) - $startTime) * 1000, 2);

        return view('monitoring.performance', [
            'queryMs' => $queryMs,
            'sensorLogsLast24h' => \App\Models\SensorLog::where('record_time', '>=', now()->subDay())->count(),
            'pumpLogsLast24h' => \App\Models\PumpLog::where('timestamp', '>=', now()->subDay())->count(),
        ]);
    }
}
