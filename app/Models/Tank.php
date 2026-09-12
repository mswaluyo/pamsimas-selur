<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tank extends Model
{
    public $timestamps = false;
    protected $table = 'tank_configurations';
    protected $fillable = ['tank_name', 'tank_shape', 'height', 'rectangular_dim_id', 'circular_dim_id'];

    public function devices()
    {
        return $this->hasMany(Device::class, 'tank_id');
    }
}
