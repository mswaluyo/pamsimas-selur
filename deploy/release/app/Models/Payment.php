<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id', 'customer_id', 'period', 'amount', 'method', 'receipt_number', 'user_id', 'paid_at',
    ];
    public $timestamps = false;
}
