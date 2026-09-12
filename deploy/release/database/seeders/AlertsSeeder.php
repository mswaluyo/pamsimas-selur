<?php

namespace Database\Seeders;

use App\Models\Alert;
use Illuminate\Database\Seeder;

class AlertsSeeder extends Seeder
{
    public function run(): void
    {
        Alert::create([
            'alert_type' => 'system',
            'title' => 'Sistem PAMSIMAS Aktif',
            'message' => 'Sistem monitoring air PAMSIMAS berhasil di-deploy dan siap digunakan.',
            'severity' => 'low',
            'status' => 'active',
        ]);

        Alert::create([
            'alert_type' => 'device',
            'title' => 'Device AA:BB:CC:DD:EE:01 Online',
            'message' => 'Perangkat IoT berhasil terhubung ke sistem.',
            'device_id' => 1,
            'severity' => 'low',
            'status' => 'active',
        ]);
    }
}
