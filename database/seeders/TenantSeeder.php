<?php

namespace Database\Seeders;

use App\Services\UserService;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Ten demo tenants, registered through UserService: tenant1 to tenant7 are approved,
     * tenant8 is rejected and tenant9 and tenant10 stay pending for the admin to verify.
     */
    public function run(): void
    {
        $names = [
            'Budi Santoso',
            'Siti Rahayu',
            'Agus Wijaya',
            'Dewi Lestari',
            'Rizky Pratama',
            'Putri Anggraini',
            'Hendra Kusuma',
            'Rina Marlina',
            'Andi Firmansyah',
            'Maya Sari',
        ];

        foreach ($names as $index => $name) {
            $number = $index + 1;

            $tenant = UserService::register([
                'name' => $name,
                'email' => "tenant{$number}@test.com",
                'password' => '12345678',
                'nik' => '31710101019000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'phone' => '0812'.str_pad((string) (3456000 + $number), 7, '0', STR_PAD_LEFT),
            ]);

            if ($number <= 7) {
                UserService::approve($tenant);
            } elseif ($number === 8) {
                UserService::reject($tenant);
            }
        }
    }
}
