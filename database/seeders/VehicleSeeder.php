<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\VehicleService;
use App\Utilities\Constants\Const_Type;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Ten vehicles for approved tenants (tenant1 to tenant7), created through VehicleService.
     */
    public function run(): void
    {
        $vehicles = [
            [1, Const_Type::VEHICLE_BICYCLE, null, 'Polygon', 'Path 3', null],
            [2, Const_Type::VEHICLE_BICYCLE, null, 'United', 'Clovis', null],
            [3, Const_Type::VEHICLE_MOTORCYCLE, 'B 3412 KLM', 'Honda', 'Vario 160', null],
            [4, Const_Type::VEHICLE_MOTORCYCLE, 'D 5521 TXA', 'Yamaha', 'NMAX', null],
            [5, Const_Type::VEHICLE_CAR, 'B 1234 XYZ', 'Toyota', 'Avanza', null],
            [6, Const_Type::VEHICLE_CAR, 'L 7788 AB', 'Honda', 'Brio', null],
            [7, Const_Type::VEHICLE_CAR, 'AB 4401 CD', 'Daihatsu', 'Xenia', null],
            [1, Const_Type::VEHICLE_ELECTRIC_CAR, 'B 9021 EVA', 'Hyundai', 'Ioniq 5', 12],
            [2, Const_Type::VEHICLE_ELECTRIC_CAR, 'B 9022 EVB', 'Wuling', 'Air ev', 10],
            [3, Const_Type::VEHICLE_ELECTRIC_CAR, 'D 9023 EVC', 'BYD', 'Atto 3', 12],
        ];

        foreach ($vehicles as [$tenantNumber, $type, $plate, $brand, $model, $capacity]) {
            VehicleService::create([
                'user_id' => User::where('email', "tenant{$tenantNumber}@test.com")->firstOrFail()->id,
                'type' => $type,
                'plate_number' => $plate,
                'brand' => $brand,
                'model' => $model,
                'maximum_capacity_kw' => $capacity,
            ]);
        }
    }
}
