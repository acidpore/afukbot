<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'mbgstore8080@gmail.com'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('@dmin12345'),
                'status'   => 'active',
                'role'     => 'super_admin',
            ]
        );
    }
}
