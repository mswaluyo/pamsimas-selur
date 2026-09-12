<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['customer_id', 'name', 'address', 'phone', 'lid'];
    public $timestamps = false;

    public function readings()
    {
        return $this->hasMany(MeterReading::class, 'customer_id', 'customer_id');
    }

    public function latestReading()
    {
        return $this->hasOne(MeterReading::class, 'customer_id', 'customer_id')->latestOfMany('period');
    }
}
