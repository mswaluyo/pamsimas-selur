<?php

namespace Database\Seeders;

use App\Models\FinancialSetting;
use App\Models\IndicatorSetting;
use App\Models\GaugeTemplate;
use Illuminate\Database\Seeder;

class FinancialSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key_name' => 'water_price', 'value' => '1500'],
            ['key_name' => 'admin_fee', 'value' => '5000'],
            ['key_name' => 'penalty_fee', 'value' => '10000'],
            ['key_name' => 'min_usage', 'value' => '10'],
            ['key_name' => 'village_name', 'value' => 'Desa Selur'],
            ['key_name' => 'operator_name', 'value' => 'BPD Selur'],
        ];
        foreach ($settings as $s) {
            FinancialSetting::create($s);
        }

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

        GaugeTemplate::create([
            'name' => 'Tank Gauge',
            'description' => 'Template default untuk tampilan tangki air',
            'html_code' => '<div class="gauge-container"><div class="gauge-fill" style="height: {{percentage}}%"></div></div>',
            'css_code' => '.gauge-container { width: 100px; height: 200px; border: 2px solid #333; } .gauge-fill { background: #3498db; }',
            'js_code' => '',
            'is_core' => true,
        ]);
    }
}
