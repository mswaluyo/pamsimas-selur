<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GaugeTemplate extends Model
{
    protected $fillable = ['name', 'description', 'html_code', 'css_code', 'js_code', 'is_core'];
    protected $casts = ['is_core' => 'boolean'];
}
