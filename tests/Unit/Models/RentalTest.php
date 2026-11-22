<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Utilities\Constants\Const_Status;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_auto_number_prefix_is_rnt_plus_todays_date(): void
    {
        $this->assertSame('RNT-20261110-', (new Rental)->autoNumberPrefix());

        Carbon::setTestNow('2027-01-05 09:00:00');

        $this->assertSame('RNT-20270105-', (new Rental)->autoNumberPrefix());
    }

    public function test_the_code_is_generated_on_create_with_a_four_digit_number_per_day(): void
    {
        $first = Rental::factory()->create(['code' => null]);
        $second = Rental::factory()->create(['code' => null]);

        $this->assertSame('RNT-20261110-0001', $first->code);
        $this->assertSame('RNT-20261110-0002', $second->code);

        Carbon::setTestNow('2026-11-11 09:00:00');

        $this->assertSame('RNT-20261111-0001', Rental::factory()->create(['code' => null])->code);
    }

    public function test_an_extension_row_keeps_the_code_it_was_given(): void
    {
        $original = Rental::factory()->create();

        $extension = Rental::factory()->extension()->create(['code' => $original->code]);

        $this->assertSame($original->code, $extension->code);
        $this->assertSame(Const_Status::EXTENSION, $extension->status);
    }

    public function test_dates_are_cast_to_carbon_and_amounts_to_two_decimals(): void
    {
        $rental = Rental::factory()->create([
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-22',
            'daily_price' => 100000,
            'rent_amount' => 300000,
        ])->fresh();

        $this->assertInstanceOf(Carbon::class, $rental->start_date);
        $this->assertSame('2026-11-22', $rental->end_date->toDateString());
        $this->assertSame('100000.00', $rental->daily_price);
        $this->assertSame('300000.00', $rental->rent_amount);
    }

    public function test_user_garage_and_vehicle_return_the_linked_records(): void
    {
        $user = User::factory()->create();
        $garage = Garage::factory()->car()->create();
        $vehicle = Vehicle::factory()->car()->for($user)->create();

        $rental = Rental::factory()->create(['user_id' => $user->id, 'garage_id' => $garage->id, 'vehicle_id' => $vehicle->id]);

        $this->assertTrue($rental->user->is($user));
        $this->assertTrue($rental->garage->is($garage));
        $this->assertTrue($rental->vehicle->is($vehicle));
    }

    public function test_charging_transactions_returns_only_its_own_transactions(): void
    {
        $rental = Rental::factory()->create();
        $mine = ChargingTransaction::factory()->create(['rental_id' => $rental->id]);
        ChargingTransaction::factory()->create();

        $this->assertSame([$mine->id], $rental->chargingTransactions->pluck('id')->all());
    }
}
