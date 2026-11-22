<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_returns_the_owner(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Vehicle::factory()->for($user)->create()->user->is($user));
    }

    public function test_rentals_returns_only_rentals_that_used_this_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();
        $mine = Rental::factory()->create(['vehicle_id' => $vehicle->id]);
        Rental::factory()->create();

        $this->assertSame([$mine->id], $vehicle->rentals->pluck('id')->all());
    }

    public function test_the_capacity_is_cast_to_two_decimals(): void
    {
        $vehicle = Vehicle::factory()->electricCar()->create(['maximum_capacity_kw' => 12.5])->fresh();

        $this->assertSame('12.50', $vehicle->maximum_capacity_kw);
    }

    public function test_the_plate_must_be_unique(): void
    {
        Vehicle::factory()->create(['plate_number' => 'B 1 AAA']);

        $this->expectException(QueryException::class);

        Vehicle::factory()->create(['plate_number' => 'B 1 AAA']);
    }

    public function test_bicycles_can_share_an_empty_plate(): void
    {
        Vehicle::factory()->bicycle()->count(2)->create();

        $this->assertSame(2, Vehicle::whereNull('plate_number')->count());
    }
}
