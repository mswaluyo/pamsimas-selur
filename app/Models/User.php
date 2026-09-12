<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'username',
        'password',
        'full_name',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function getRoleNameAttribute(): string
    {
        return $this->role?->name ?? 'Viewer';
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'Administrator';
    }
}
