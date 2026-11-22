<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\VehicleService;
use App\Utilities\Constants\Const_Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function data(string $type, array $overrides = []): array
    {
        return array_merge([
            'user_id' => User::factory()->create()->id,
            'type' => $type,
            'plate_number' => 'B 1234 XYZ',
            'brand' => 'Brand',
            'model' => 'Model',
            'maximum_capacity_kw' => 10,
        ], $overrides);
    }

    public function test_create_drops_the_plate_and_capacity_of_a_bicycle(): void
    {
        $vehicle = VehicleService::create($this->data(Const_Type::VEHICLE_BICYCLE));

        $this->assertNull($vehicle->plate_number);
        $this->assertNull($vehicle->maximum_capacity_kw);
    }

    public function test_create_keeps_the_plate_but_drops_the_capacity_of_a_motorcycle_and_a_car(): void
    {
        foreach ([Const_Type::VEHICLE_MOTORCYCLE, Const_Type::VEHICLE_CAR] as $type) {
            $vehicle = VehicleService::create($this->data($type, ['plate_number' => 'B '.$type]));

            $this->assertSame('B '.$type, $vehicle->plate_number);
            $this->assertNull($vehicle->maximum_capacity_kw);
        }
    }

    public function test_create_keeps_the_plate_and_capacity_of_an_electric_car(): void
    {
        $vehicle = VehicleService::create($this->data(Const_Type::VEHICLE_ELECTRIC_CAR, ['maximum_capacity_kw' => 12.5]));

        $this->assertSame('B 1234 XYZ', $vehicle->plate_number);
        $this->assertSame('12.50', $vehicle->maximum_capacity_kw);
        $this->assertDatabaseCount('vehicles', 1);
    }

    public function test_create_saves_the_vehicle_to_its_owner(): void
    {
        $data = $this->data(Const_Type::VEHICLE_CAR);

        $this->assertSame($data['user_id'], VehicleService::create($data)->user_id);
    }
}
