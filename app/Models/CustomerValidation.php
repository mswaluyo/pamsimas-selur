<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerValidation extends Model
{
    protected $table = 'customer_validations';
    protected $primaryKey = 'session_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['session_id', 'lid', 'phone', 'angka_sementara', 'foto_path', 'status'];
}
