<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeterReading extends Model
{
    protected $fillable = [
        'customer_id', 'period', 'current_meter', 'photo_path', 'validated_by_user_id',
    ];
    public $timestamps = false;

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'meter_reading_id');
    }
}
