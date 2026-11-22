<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

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
