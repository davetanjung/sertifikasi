<?php

namespace Database\Seeders;

use App\Models\User;
use App\Utilities\Constants\Const_Role;
use App\Utilities\Constants\Const_Status;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Create the admin account. Only a seeder creates admins, tenants register themselves.
     * Safe to run again: the admin is found by email and updated instead of duplicated.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@test'],
            [
                'name' => 'Admin',
                'password' => '12345678',
                'nik' => '3171000000000001',
                'phone' => '081234567890',
                'role' => Const_Role::ADMIN,
                'registration_status' => Const_Status::APPROVED,
                'email_verified_at' => now(),
            ],
        );
    }
}
