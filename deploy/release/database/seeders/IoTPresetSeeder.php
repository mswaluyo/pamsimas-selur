<?php

namespace Database\Seeders;

use App\Models\Pump;
use App\Models\Sensor;
use App\Models\TankConfiguration;
use App\Models\Device;
use Illuminate\Database\Seeder;

class IoTPresetSeeder extends Seeder
{
    public function run(): void
    {
        // Pumps
        Pump::create([
            'pump_name' => 'Pompa Utama',
            'flow_rate_lps' => 5.0,
            'power_hp' => 1.5,
            'power_watt' => 750,
            'delay_seconds' => 10,
            'on_duration_seconds' => 300,
            'off_duration_seconds' => 900,
        ]);

        Pump::create([
            'pump_name' => 'Pompa Cadangan',
            'flow_rate_lps' => 3.0,
            'power_hp' => 1.0,
            'power_watt' => 500,
            'delay_seconds' => 15,
            'on_duration_seconds' => 200,
            'off_duration_seconds' => 1200,
        ]);

        // Sensors
        Sensor::create([
            'sensor_name' => 'Sensor Tangki 1',
            'sensor_type' => 'JSN-SR04T',
            'full_tank_distance' => 30,
            'trigger_percentage' => 70,
        ]);

        Sensor::create([
            'sensor_name' => 'Sensor Tangki 2',
            'sensor_type' => 'JSN-SR04T',
            'full_tank_distance' => 25,
            'trigger_percentage' => 75,
        ]);

        // Tank
        $tank1 = TankConfiguration::create([
            'tank_name' => 'Tangki Utama',
            'tank_shape' => 'kotak',
            'height' => 200,
        ]);

        // Device
        Device::create([
            'mac_address' => 'AA:BB:CC:DD:EE:01',
            'device_type' => 'MONITOR',
            'tank_id' => $tank1->id,
            'pump_id' => 1,
            'sensor_id' => 1,
            'status' => 'ON',
            'control_mode' => 'AUTO',
            'firmware_version' => '1.0.0',
        ]);
    }
}
