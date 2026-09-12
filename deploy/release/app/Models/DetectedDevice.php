<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetectedDevice extends Model
{
    public $timestamps = false;
    protected $fillable = ['mac_address', 'first_seen', 'last_seen', 'hits', 'fingerprint'];

    protected $casts = [
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
    ];
}
