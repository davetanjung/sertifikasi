<?php

namespace Tests\Feature;

use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_factory_creates_a_row_with_uuid(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->assertTrue(Str::isUuid($vehicle->id));
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id]);
        $this->assertNotNull($vehicle->plate_number);
        $this->assertSame(Const_Type::VEHICLE_CAR, $vehicle->type);
    }

    public function test_vehicle_states_set_plate_and_capacity(): void
    {
        $bicycle = Vehicle::factory()->bicycle()->create();
        $motorcycle = Vehicle::factory()->motorcycle()->create();
        $electric = Vehicle::factory()->electricCar()->create();

        $this->assertNull($bicycle->plate_number);
        $this->assertNotNull($motorcycle->plate_number);
        $this->assertSame(Const_Type::VEHICLE_ELECTRIC_CAR, $electric->type);
        $this->assertSame('10.00', $electric->fresh()->maximum_capacity_kw);
    }

    public function test_garage_factory_creates_a_row_with_uuid(): void
    {
        $garage = Garage::factory()->create();

        $this->assertTrue(Str::isUuid($garage->id));
        $this->assertDatabaseHas('garages', ['id' => $garage->id]);
        $this->assertNull($garage->price_per_kwh);
        $this->assertFalse($garage->isElectric());
    }

    public function test_electric_garage_has_a_price_per_kwh(): void
    {
        $garage = Garage::factory()->electricCar()->create();

        $this->assertTrue($garage->isElectric());
        $this->assertNotNull($garage->price_per_kwh);
    }

    public function test_garage_codes_are_generated_per_type_and_increase(): void
    {
        $this->assertSame('S001', Garage::factory()->bicycle()->create()->code);
        $this->assertSame('S002', Garage::factory()->bicycle()->create()->code);
        $this->assertSame('C001', Garage::factory()->car()->create()->code);
        $this->assertSame('E001', Garage::factory()->electricCar()->create()->code);
    }

    public function test_garage_with_unknown_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Garage::factory()->create(['type' => 'boat']);
    }

    public function test_rental_factory_creates_a_row_with_uuid_and_relations(): void
    {
        $rental = Rental::factory()->create();

        $this->assertTrue(Str::isUuid($rental->id));
        $this->assertDatabaseHas('rentals', ['id' => $rental->id]);
        $this->assertSame(Const_Status::NEW, $rental->status);
        $this->assertInstanceOf(User::class, $rental->user);
        $this->assertInstanceOf(Garage::class, $rental->garage);
        $this->assertInstanceOf(Vehicle::class, $rental->vehicle);
        $this->assertSame(3, (int) $rental->start_date->diffInDays($rental->end_date) + 1);
    }

    public function test_monthly_rental_is_priced_at_25_times_the_daily_price(): void
    {
        $rental = Rental::factory()->monthly()->create();

        $this->assertEquals($rental->daily_price * 25, $rental->rent_amount);
        $this->assertTrue($rental->start_date->copy()->addMonthNoOverflow()->subDay()->isSameDay($rental->end_date));
    }

    public function test_rental_codes_are_generated_per_day_and_increase(): void
    {
        $today = now()->format('Ymd');

        $this->assertSame("RNT-{$today}-0001", Rental::factory()->create()->code);
        $this->assertSame("RNT-{$today}-0002", Rental::factory()->create()->code);
    }

    public function test_extension_row_keeps_its_explicit_code(): void
    {
        $rental = Rental::factory()->create();
        $extension = Rental::factory()->extension()->create([
            'user_id' => $rental->user_id,
            'garage_id' => $rental->garage_id,
            'vehicle_id' => $rental->vehicle_id,
            'code' => $rental->code,
        ]);

        $this->assertSame($rental->code, $extension->code);
        $this->assertSame(Const_Status::EXTENSION, $extension->status);
        $this->assertSame(2, $extension->days);
    }

    public function test_charging_transaction_factory_creates_a_consistent_row(): void
    {
        $charging = ChargingTransaction::factory()->create();

        $this->assertTrue(Str::isUuid($charging->id));
        $this->assertDatabaseHas('charging_transactions', ['id' => $charging->id]);
        $this->assertEquals(10 - $charging->current_capacity_kw, $charging->charged_kwh);
        $this->assertEquals($charging->charged_kwh * $charging->price_per_kwh, $charging->amount);
        $this->assertInstanceOf(Rental::class, $charging->rental);
    }

    public function test_main_relationships_resolve(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();
        $rental = Rental::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id]);
        ChargingTransaction::factory()->count(2)->create(['rental_id' => $rental->id]);

        $this->assertTrue($user->vehicles->contains($vehicle));
        $this->assertTrue($user->rentals->contains($rental));
        $this->assertTrue($vehicle->rentals->contains($rental));
        $this->assertTrue($rental->garage->rentals->contains($rental));
        $this->assertCount(2, $rental->chargingTransactions);
    }

    public function test_deleting_a_garage_that_has_a_rental_is_rejected(): void
    {
        $rental = Rental::factory()->create();

        $this->expectException(QueryException::class);

        $rental->garage->delete();
    }
}
