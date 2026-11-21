<?php

namespace Tests\Feature;

use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RentalService;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalServiceTest extends TestCase
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

    /**
     * Rent through the service with sensible defaults (a daily rental of 3 days).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function rent(Garage $garage, Vehicle $vehicle, array $overrides = []): Rental
    {
        return RentalService::create(array_merge([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 3,
            'payment_proof_path' => 'proofs/new.jpg',
        ], $overrides));
    }

    private function carGarage(int $dailyPrice = 100000): Garage
    {
        return Garage::factory()->car()->create(['daily_price' => $dailyPrice]);
    }

    private function carOf(?User $user = null): Vehicle
    {
        return Vehicle::factory()->car()->for($user ?? User::factory()->create())->create();
    }

    public function test_daily_create_sets_dates_amount_status_code_and_price(): void
    {
        $rental = $this->rent($this->carGarage(100000), $this->carOf(), ['days' => 5]);

        $this->assertSame(Const_Status::NEW, $rental->status);
        $this->assertSame('2026-11-10', $rental->start_date->toDateString());
        $this->assertSame('2026-11-14', $rental->end_date->toDateString());
        $this->assertSame(5, $rental->days);
        $this->assertSame('500000.00', $rental->fresh()->rent_amount);
        $this->assertSame('100000.00', $rental->fresh()->daily_price);
        $this->assertSame('RNT-20261110-0001', $rental->code);
    }

    public function test_monthly_create_uses_the_days_of_the_month_and_25_times_the_price(): void
    {
        $rental = $this->rent($this->carGarage(100000), $this->carOf(), [
            'rental_type' => Const_Type::RENTAL_MONTHLY,
            'days' => null,
        ]);

        $this->assertSame(30, $rental->days);
        $this->assertSame('2026-11-10', $rental->start_date->toDateString());
        $this->assertSame('2026-12-09', $rental->end_date->toDateString());
        $this->assertSame('2500000.00', $rental->fresh()->rent_amount);
    }

    public function test_monthly_price_is_25_times_even_in_a_31_day_month(): void
    {
        $rental = $this->rent($this->carGarage(100000), $this->carOf(), [
            'rental_type' => Const_Type::RENTAL_MONTHLY,
            'start_date' => '2026-12-01',
        ]);

        $this->assertSame(31, $rental->days);
        $this->assertSame('2026-12-31', $rental->end_date->toDateString());
        $this->assertSame('2500000.00', $rental->fresh()->rent_amount);
    }

    public function test_daily_maximum_is_days_in_month_minus_one(): void
    {
        $garage = $this->carGarage();
        $vehicle = $this->carOf();

        Carbon::setTestNow('2026-11-01 09:00:00');

        $rental = $this->rent($garage, $vehicle, ['days' => 29]);

        $this->assertSame('2026-11-29', $rental->end_date->toDateString());

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Days must be between 1 and 29 for this month.');
        $this->rent($this->carGarage(), $vehicle, ['days' => 30]);
    }

    public function test_daily_requires_days_and_at_least_one(): void
    {
        $garage = $this->carGarage();
        $vehicle = $this->carOf();

        try {
            $this->rent($garage, $vehicle, ['days' => null]);
            $this->fail('Expected GeneralException was not thrown.');
        } catch (GeneralException $exception) {
            $this->assertSame('Number of days is required for a daily rental.', $exception->getMessage());
        }

        $this->expectException(GeneralException::class);
        $this->rent($garage, $vehicle, ['days' => 0]);
    }

    public function test_invalid_rental_type_is_rejected(): void
    {
        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Rental type is not valid.');

        $this->rent($this->carGarage(), $this->carOf(), ['rental_type' => 'weekly']);
    }

    public function test_past_start_date_is_rejected(): void
    {
        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Start date cannot be before today.');

        $this->rent($this->carGarage(), $this->carOf(), ['start_date' => '2026-11-09']);
    }

    public function test_future_start_date_is_allowed_as_a_booking(): void
    {
        $rental = $this->rent($this->carGarage(), $this->carOf(), ['start_date' => '2026-11-20', 'days' => 2]);

        $this->assertSame('2026-11-20', $rental->start_date->toDateString());
        $this->assertSame('2026-11-21', $rental->end_date->toDateString());
    }

    public function test_vehicle_of_another_user_is_rejected(): void
    {
        $vehicle = $this->carOf();
        $other = User::factory()->create();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('The vehicle does not belong to this tenant.');

        $this->rent($this->carGarage(), $vehicle, ['user_id' => $other->id]);
    }

    public function test_bicycle_and_motorcycle_fit_only_a_bicycle_garage(): void
    {
        $user = User::factory()->create();
        $bicycle = Vehicle::factory()->bicycle()->for($user)->create();
        $motorcycle = Vehicle::factory()->motorcycle()->for($user)->create();

        $this->assertSame(Const_Status::NEW, $this->rent(Garage::factory()->bicycle()->create(), $bicycle)->status);
        $this->assertSame(Const_Status::NEW, $this->rent(Garage::factory()->bicycle()->create(), $motorcycle)->status);

        foreach ([$bicycle, $motorcycle] as $vehicle) {
            foreach ([Garage::factory()->car()->create(), Garage::factory()->electricCar()->create()] as $garage) {
                $this->assertRejected(fn () => $this->rent($garage, $vehicle));
            }
        }
    }

    public function test_car_fits_only_a_car_garage(): void
    {
        $car = $this->carOf();

        $this->assertSame(Const_Status::NEW, $this->rent(Garage::factory()->car()->create(), $car)->status);
        $this->assertRejected(fn () => $this->rent(Garage::factory()->bicycle()->create(), $car));
        $this->assertRejected(fn () => $this->rent(Garage::factory()->electricCar()->create(), $car));
    }

    public function test_electric_car_fits_a_car_or_an_electric_garage_but_not_a_bicycle_garage(): void
    {
        $electric = Vehicle::factory()->electricCar()->create();

        $this->assertSame(Const_Status::NEW, $this->rent(Garage::factory()->car()->create(), $electric)->status);
        // The same vehicle cannot be in two garages at once, so the second rental starts after the first (10 to 12 Nov) ends.
        $this->assertSame(Const_Status::NEW, $this->rent(Garage::factory()->electricCar()->create(), $electric, ['start_date' => '2026-11-13'])->status);
        $this->assertRejected(fn () => $this->rent(Garage::factory()->bicycle()->create(), $electric));
    }

    public function test_overlapping_dates_on_the_same_garage_are_rejected(): void
    {
        $garage = $this->carGarage();
        $this->rent($garage, $this->carOf(), ['days' => 5]); // 10 to 14 Nov

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Garage is not available for those dates');

        $this->rent($garage, $this->carOf(), ['start_date' => '2026-11-14', 'days' => 2]);
    }

    public function test_back_to_back_rentals_are_allowed(): void
    {
        $garage = $this->carGarage();
        $this->rent($garage, $this->carOf(), ['days' => 5]); // ends 14 Nov

        $second = $this->rent($garage, $this->carOf(), ['start_date' => '2026-11-15', 'days' => 2]);

        $this->assertSame('2026-11-15', $second->start_date->toDateString());
    }

    public function test_a_different_garage_with_the_same_dates_is_allowed(): void
    {
        $this->rent($this->carGarage(), $this->carOf());

        $second = $this->rent($this->carGarage(), $this->carOf());

        $this->assertSame('RNT-20261110-0002', $second->code);
    }

    public function test_overlap_with_an_extension_row_is_rejected(): void
    {
        $garage = $this->carGarage();
        $rental = $this->rent($garage, $this->carOf(), ['days' => 3]); // 10 to 12 Nov
        RentalService::extend($rental, '2026-11-16', 'proofs/ext.jpg'); // 13 to 16 Nov

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Garage is not available for those dates');

        $this->rent($garage, $this->carOf(), ['start_date' => '2026-11-15', 'days' => 2]);
    }

    public function test_extend_adds_a_row_with_the_same_code_and_chained_dates(): void
    {
        $rental = $this->rent($this->carGarage(100000), $this->carOf(), ['days' => 3]); // 10 to 12 Nov

        $extension = RentalService::extend($rental, '2026-11-14', 'proofs/ext.jpg');

        $this->assertSame(Const_Status::EXTENSION, $extension->status);
        $this->assertSame($rental->code, $extension->code);
        $this->assertSame('2026-11-13', $extension->start_date->toDateString());
        $this->assertSame('2026-11-14', $extension->end_date->toDateString());
        $this->assertSame(2, $extension->days);
        $this->assertSame('200000.00', $extension->fresh()->rent_amount);
        $this->assertSame('proofs/ext.jpg', $extension->payment_proof_path);
        $this->assertSame($rental->vehicle_id, $extension->vehicle_id);
        $this->assertSame('2026-11-14', RentalService::latestEndDate($rental)->toDateString());
    }

    public function test_extending_a_monthly_rental_costs_added_days_times_the_daily_price(): void
    {
        $rental = $this->rent($this->carGarage(100000), $this->carOf(), [
            'rental_type' => Const_Type::RENTAL_MONTHLY,
            'days' => null,
        ]); // 10 Nov to 9 Dec

        $extension = RentalService::extend($rental, '2026-12-12', 'proofs/ext.jpg');

        $this->assertSame(3, $extension->days);
        $this->assertSame('300000.00', $extension->fresh()->rent_amount);
        $this->assertSame(Const_Type::RENTAL_MONTHLY, $extension->rental_type);
    }

    public function test_a_second_extension_chains_from_the_first(): void
    {
        $rental = $this->rent($this->carGarage(100000), $this->carOf(), ['days' => 3]);
        $first = RentalService::extend($rental, '2026-11-14', 'proofs/ext1.jpg');

        $second = RentalService::extend($first, '2026-11-17', 'proofs/ext2.jpg');

        $this->assertSame('2026-11-15', $second->start_date->toDateString());
        $this->assertSame(3, $second->days);
        $this->assertSame('300000.00', $second->fresh()->rent_amount);
        $this->assertSame($rental->code, $second->code);
        $this->assertSame('2026-11-17', RentalService::latestEndDate($rental)->toDateString());
    }

    public function test_extend_rejects_shortening_and_the_same_end_date(): void
    {
        $rental = $this->rent($this->carGarage(), $this->carOf(), ['days' => 3]); // ends 12 Nov

        foreach (['2026-11-12', '2026-11-11'] as $newEnd) {
            try {
                RentalService::extend($rental, $newEnd, 'proofs/ext.jpg');
                $this->fail('Expected GeneralException was not thrown.');
            } catch (GeneralException $exception) {
                $this->assertSame('The new end date must be after the current end date.', $exception->getMessage());
            }
        }

        $this->assertSame(1, Rental::count());
    }

    public function test_extend_rejects_a_rental_that_has_ended(): void
    {
        $rental = $this->rent($this->carGarage(), $this->carOf(), ['days' => 3]); // ends 12 Nov
        Carbon::setTestNow('2026-11-13 09:00:00');

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('The rental has already ended and cannot be extended.');

        RentalService::extend($rental, '2026-11-20', 'proofs/ext.jpg');
    }

    public function test_extend_rejects_an_overlap_with_the_booking_of_another_code(): void
    {
        $garage = $this->carGarage();
        $rental = $this->rent($garage, $this->carOf(), ['days' => 3]); // 10 to 12 Nov
        $this->rent($garage, $this->carOf(), ['start_date' => '2026-11-16', 'days' => 3]); // 16 to 18 Nov

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Garage is booked from 2026-11-16');

        RentalService::extend($rental, '2026-11-16', 'proofs/ext.jpg');
    }

    public function test_extend_accepts_a_booking_that_starts_right_after_the_new_end(): void
    {
        $garage = $this->carGarage();
        $rental = $this->rent($garage, $this->carOf(), ['days' => 3]); // 10 to 12 Nov
        $this->rent($garage, $this->carOf(), ['start_date' => '2026-11-16', 'days' => 3]);

        $extension = RentalService::extend($rental, '2026-11-15', 'proofs/ext.jpg');

        $this->assertSame('2026-11-15', $extension->end_date->toDateString());
    }

    public function test_create_requires_a_payment_proof(): void
    {
        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Payment proof is required.');

        $this->rent($this->carGarage(), $this->carOf(), ['payment_proof_path' => '']);
    }

    public function test_a_vehicle_cannot_be_rented_in_two_garages_for_overlapping_dates(): void
    {
        $vehicle = $this->carOf();
        $this->rent($this->carGarage(), $vehicle, ['days' => 5]); // 10 to 14 Nov

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('This vehicle is already rented for those dates.');

        $this->rent($this->carGarage(), $vehicle, ['start_date' => '2026-11-12', 'days' => 3]);
    }

    public function test_a_vehicle_can_be_rented_in_another_garage_right_after_its_first_rental_ends(): void
    {
        $vehicle = $this->carOf();
        $this->rent($this->carGarage(), $vehicle, ['days' => 5]); // 10 to 14 Nov

        $second = $this->rent($this->carGarage(), $vehicle, ['start_date' => '2026-11-15', 'days' => 3]);

        $this->assertSame('2026-11-15', $second->start_date->toDateString());
    }

    public function test_the_same_tenant_can_rent_two_garages_at_once_with_two_vehicles(): void
    {
        $user = User::factory()->create();
        $this->rent($this->carGarage(), $this->carOf($user), ['days' => 5]);

        $second = $this->rent($this->carGarage(), $this->carOf($user), ['days' => 5]);

        $this->assertSame(2, Rental::query()->where('user_id', $user->id)->count());
        $this->assertNotNull($second->id);
    }

    public function test_extend_rejects_an_overlap_with_the_same_vehicle_in_another_garage(): void
    {
        $vehicle = $this->carOf();
        $rental = $this->rent($this->carGarage(), $vehicle, ['days' => 3]); // 10 to 12 Nov
        $this->rent($this->carGarage(), $vehicle, ['start_date' => '2026-11-14', 'days' => 3]); // 14 to 16 Nov, other garage

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('This vehicle is already rented for those dates.');

        RentalService::extend($rental, '2026-11-15', 'proofs/ext.jpg');
    }

    public function test_is_running_today_follows_the_start_and_latest_end_dates(): void
    {
        $rental = $this->rent($this->carGarage(), $this->carOf(), ['start_date' => '2026-11-12', 'days' => 2]); // 12 to 13

        $this->assertFalse(RentalService::isRunningToday($rental));

        Carbon::setTestNow('2026-11-13 09:00:00');
        $this->assertTrue(RentalService::isRunningToday($rental));

        RentalService::extend($rental, '2026-11-15', 'proofs/ext.jpg');
        Carbon::setTestNow('2026-11-15 09:00:00');
        $this->assertTrue(RentalService::isRunningToday($rental));

        Carbon::setTestNow('2026-11-16 09:00:00');
        $this->assertFalse(RentalService::isRunningToday($rental));
    }

    /**
     * Assert the callback throws a GeneralException.
     */
    private function assertRejected(callable $callback): void
    {
        try {
            $callback();
        } catch (GeneralException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Expected GeneralException was not thrown.');
    }
}
