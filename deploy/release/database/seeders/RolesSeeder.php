<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Super Admin', 'Admin', 'Operator', 'Viewer'];
        foreach ($roles as $role) {
            \DB::table('roles')->updateOrInsert(['name' => $role]);
        }
    }
}
