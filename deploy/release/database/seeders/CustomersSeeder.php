<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\MeterReading;
use App\Models\Invoice;
use App\Models\AdminLog;
use Illuminate\Database\Seeder;

class CustomersSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['customer_id' => 'PTA-0001', 'name' => 'Ahmad Sudrajat', 'address' => 'RT 01/RW 02', 'phone' => '6285157275801'],
            ['customer_id' => 'PTA-0002', 'name' => 'Budi Santoso', 'address' => 'RT 01/RW 03', 'phone' => '6285157275802'],
            ['customer_id' => 'PTA-0003', 'name' => 'Cahyo Widodo', 'address' => 'RT 02/RW 01', 'phone' => '6285157275803'],
            ['customer_id' => 'PTA-0004', 'name' => 'Dewi Lestari', 'address' => 'RT 02/RW 02', 'phone' => '6285157275804'],
            ['customer_id' => 'PTA-0005', 'name' => 'Eko Prasetyo', 'address' => 'RT 03/RW 01', 'phone' => '6285157275805'],
            ['customer_id' => 'PTA-0006', 'name' => 'Fitri Handayani', 'address' => 'RT 03/RW 02', 'phone' => '6285157275806'],
            ['customer_id' => 'PTA-0007', 'name' => 'Gunawan Wibowo', 'address' => 'RT 04/RW 01', 'phone' => '6285157275807'],
            ['customer_id' => 'PTA-0008', 'name' => 'Hani Susanti', 'address' => 'RT 04/RW 02', 'phone' => '6285157275808'],
            ['customer_id' => 'PTA-0009', 'name' => 'Irfan Hakim', 'address' => 'RT 05/RW 01', 'phone' => '6285157275809'],
            ['customer_id' => 'PTA-0010', 'name' => 'Joko Widodo', 'address' => 'RT 05/RW 02', 'phone' => '6285157275810'],
        ];

        $period = date('Y-m');

        foreach ($customers as $i => $c) {
            Customer::create($c);

            $reading = MeterReading::create([
                'customer_id' => $c['customer_id'],
                'period' => $period,
                'current_meter' => rand(100, 500) + (rand(0, 99) / 100),
            ]);

            $usage = rand(10, 30);
            Invoice::create([
                'customer_id' => $c['customer_id'],
                'period' => $period,
                'water_usage' => $usage,
                'water_price' => 1500,
                'admin_fee' => 5000,
                'total_bill' => ($usage * 1500) + 5000,
                'status_bayar' => $i < 3 ? 'LUNAS' : 'BELUM',
            ]);
        }

        AdminLog::create([
            'user_id' => 1,
            'action' => 'system_install',
            'details' => 'PAMSIMAS berhasil di-install dengan 10 data pelanggan dummy.',
        ]);
    }
}
