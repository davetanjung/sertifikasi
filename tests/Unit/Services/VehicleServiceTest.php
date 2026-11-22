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

    public function test_create_keeps_the_plate_and_capacity_of_an_electric_car(): void
    {
        $vehicle = VehicleService::create($this->data(Const_Type::VEHICLE_ELECTRIC_CAR, ['maximum_capacity_kw' => 12.5]));

        $this->assertSame('B 1234 XYZ', $vehicle->plate_number);
        $this->assertSame('12.50', $vehicle->maximum_capacity_kw);
        $this->assertDatabaseCount('vehicles', 1);
    }
}
