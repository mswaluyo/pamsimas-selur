<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $roleIds = \DB::table('roles')->pluck('id', 'name');

        User::create([
            'username' => 'superadmin',
            'password' => Hash::make('admin123'),
            'full_name' => 'Super Administrator',
            'role_id' => $roleIds['Super Admin'],
        ]);

        User::create([
            'username' => 'admin',
            'password' => Hash::make('admin123'),
            'full_name' => 'Administrator PAMSIMAS',
            'role_id' => $roleIds['Admin'],
        ]);

        User::create([
            'username' => 'operator',
            'password' => Hash::make('operator123'),
            'full_name' => 'Operator Desa Selur',
            'role_id' => $roleIds['Operator'],
        ]);

        User::create([
            'username' => 'viewer',
            'password' => Hash::make('viewer123'),
            'full_name' => 'Viewer',
            'role_id' => $roleIds['Viewer'],
        ]);
    }
}
