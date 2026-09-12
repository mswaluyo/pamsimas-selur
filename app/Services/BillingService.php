<?php

namespace App\Services;

use App\Models\IndicatorSetting;
use App\Models\Invoice;

/**
 * Kalkulasi keuangan & pembuatan invoice (port dari app\Core\BillingService).
 */
class BillingService
{
    public static function calculate(float $usage): array
    {
        $settings = IndicatorSetting::getSettings();
        $usageClean = max(0, $usage);
        $price = (float) ($settings['water_price'] ?? 0);
        $admin = (float) ($settings['admin_fee'] ?? 0);

        return [
            'usage' => $usageClean,
            'price' => $price,
            'admin' => $admin,
            'total' => ($usageClean * $price) + $admin,
        ];
    }

    public static function createInvoice(int $readingId, string $customerId, string $period, float $usage): Invoice
    {
        $bill = self::calculate($usage);

        return Invoice::updateOrCreate(
            ['customer_id' => $customerId, 'period' => $period],
            [
                'meter_reading_id' => $readingId,
                'water_usage' => $bill['usage'],
                'water_price' => $bill['price'],
                'admin_fee' => $bill['admin'],
                'total_bill' => $bill['total'],
                'status_bayar' => 'BELUM',
            ]
        );
    }
}
