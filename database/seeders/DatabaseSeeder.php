<?php

namespace Database\Seeders;

use App\Models\GaugeTemplate;
use App\Models\IndicatorSetting;
use App\Models\Role;
use App\Models\Sensor;
use App\Models\Pump;
use App\Models\Tank;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles
        foreach (['Administrator', 'Operator', 'Kasir', 'Viewer'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }

        // Akun default
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('admin123'),
                'full_name' => 'Administrator',
                'role_id' => Role::where('name', 'Administrator')->first()->id,
            ]
        );
        User::firstOrCreate(
            ['username' => 'kasir'],
            [
                'password' => Hash::make('kasir123'),
                'full_name' => 'Kasir Desa',
                'role_id' => Role::where('name', 'Kasir')->first()->id,
            ]
        );

        // Master data minimal
        if (Tank::count() === 0) {
            Tank::create(['tank_name' => 'Tandon Utama', 'tank_shape' => 'kotak', 'height' => 200]);
        }
        if (Pump::count() === 0) {
            Pump::create(['pump_name' => 'Pompa Utama', 'flow_rate_lps' => 1.5, 'power_watt' => 370]);
        }
        if (Sensor::count() === 0) {
            Sensor::create(['sensor_name' => 'Sensor Ultrasonik 1', 'sensor_type' => 'JSN-SR04T', 'full_tank_distance' => 30, 'trigger_percentage' => 70]);
        }

        // Pengaturan indikator & tarif
        if (IndicatorSetting::count() === 0) {
            IndicatorSetting::create([
                'threshold_low' => 30,
                'color_low' => '#e74c3c',
                'threshold_medium' => 70,
                'color_medium' => '#f39c12',
                'color_high' => '#27ae60',
                'active_template_id' => 'tank_gauge',
                'water_price' => 1500,
                'admin_fee' => 5000,
            ]);
        }

        // Template gauge bawaan
        $templates = [
            ['name' => 'tank_gauge', 'description' => 'Gauge tangan vertikal (air naik-turun)', 'is_core' => true],
            ['name' => 'three_quarter_gauge', 'description' => 'Gauge melingkar 3/4', 'is_core' => true],
            ['name' => 'simple_bar_gauge', 'description' => 'Bar sederhana horizontal', 'is_core' => true],
        ];
        foreach ($templates as $t) {
            GaugeTemplate::firstOrCreate(['name' => $t['name']], $t);
        }
    }
}
