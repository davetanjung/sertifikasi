<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * WithoutModelEvents is not used on purpose: HasAutoNumber generates garage and
     * rental codes in a creating event, so seeders that create them need events on.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            TenantSeeder::class,
            VehicleSeeder::class,
            GarageSeeder::class,
            RentalSeeder::class,
        ]);
    }
}
