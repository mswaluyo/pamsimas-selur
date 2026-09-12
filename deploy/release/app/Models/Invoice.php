<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'meter_reading_id', 'customer_id', 'period',
        'water_usage', 'water_price', 'admin_fee', 'total_bill', 'status_bayar',
    ];
    public $timestamps = false;

    public function payments()
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }
}
