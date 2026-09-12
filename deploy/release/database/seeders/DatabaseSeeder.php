<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\AdminLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\FinancialSetting;
use App\Models\GaugeTemplate;
use App\Models\IndicatorSetting;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\Pump;
use App\Models\Sensor;
use App\Models\TankConfiguration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            UsersSeeder::class,
            FinancialSettingsSeeder::class,
            CustomersSeeder::class,
            IoTPresetSeeder::class,
            AlertsSeeder::class,
        ]);
    }
}
