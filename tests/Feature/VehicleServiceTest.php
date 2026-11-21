<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\VehicleService;
use App\Utilities\Constants\Const_Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bicycle_drops_the_plate(): void
    {
        $user = User::factory()->create();

        $vehicle = VehicleService::create([
            'user_id' => $user->id,
            'type' => Const_Type::VEHICLE_BICYCLE,
            'plate_number' => 'B 1234 XYZ',
            'brand' => 'Polygon',
            'model' => 'Heist',
            'maximum_capacity_kw' => 10,
        ]);

        $this->assertNull($vehicle->plate_number);
        $this->assertNull($vehicle->maximum_capacity_kw);
    }

    public function test_non_electric_car_drops_the_capacity(): void
    {
        $user = User::factory()->create();

        $vehicle = VehicleService::create([
            'user_id' => $user->id,
            'type' => Const_Type::VEHICLE_CAR,
            'plate_number' => 'B 1111 AAA',
            'brand' => 'Toyota',
            'model' => 'Avanza',
            'maximum_capacity_kw' => 10,
        ]);

        $this->assertSame('B 1111 AAA', $vehicle->plate_number);
        $this->assertNull($vehicle->maximum_capacity_kw);
    }

    public function test_electric_car_keeps_the_capacity(): void
    {
        $user = User::factory()->create();

        $vehicle = VehicleService::create([
            'user_id' => $user->id,
            'type' => Const_Type::VEHICLE_ELECTRIC_CAR,
            'plate_number' => 'B 2222 EVA',
            'brand' => 'Hyundai',
            'model' => 'Ioniq',
            'maximum_capacity_kw' => 10,
        ]);

        $this->assertSame('B 2222 EVA', $vehicle->plate_number);
        $this->assertSame('10.00', $vehicle->fresh()->maximum_capacity_kw);
    }
}
