<?php

namespace Database\Seeders;

use App\Services\GarageService;
use App\Utilities\Constants\Const_Type;
use Illuminate\Database\Seeder;

class GarageSeeder extends Seeder
{
    /**
     * Ten garages through GarageService::createBatch(): S001-S003, C001-C004 and E001-E003.
     */
    public function run(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_BICYCLE, 3, 5000);
        GarageService::createBatch(Const_Type::GARAGE_CAR, 4, 50000);
        GarageService::createBatch(Const_Type::GARAGE_ELECTRIC_CAR, 3, 75000, 2500);
    }
}
