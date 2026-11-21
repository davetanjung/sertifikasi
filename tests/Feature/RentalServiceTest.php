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
use Illuminate\Support\Facades\DB;
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

    public function test_is_active_follows_the_start_and_latest_end_dates(): void
    {
        $rental = $this->rent($this->carGarage(), $this->carOf(), ['start_date' => '2026-11-12', 'days' => 2]); // 12 to 13

        $this->assertFalse(RentalService::isActive($rental));

        Carbon::setTestNow('2026-11-13 09:00:00');
        $this->assertTrue(RentalService::isActive($rental));

        RentalService::extend($rental, '2026-11-15', 'proofs/ext.jpg');
        Carbon::setTestNow('2026-11-15 09:00:00');
        $this->assertTrue(RentalService::isActive($rental));

        Carbon::setTestNow('2026-11-16 09:00:00');
        $this->assertFalse(RentalService::isActive($rental));
    }

    public function test_preview_matches_a_daily_create_and_saves_nothing(): void
    {
        $garage = $this->carGarage(100000);

        $preview = RentalService::preview([
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 5,
        ]);

        $this->assertSame(0, Rental::count());

        $rental = $this->rent($garage, $this->carOf(), ['days' => 5])->fresh();

        $this->assertSame($rental->start_date->toDateString(), $preview['start_date']);
        $this->assertSame($rental->end_date->toDateString(), $preview['end_date']);
        $this->assertSame($rental->days, $preview['days']);
        $this->assertSame((float) $rental->daily_price, $preview['daily_price']);
        $this->assertSame((float) $rental->rent_amount, $preview['rent_amount']);
        $this->assertSame(Const_Type::RENTAL_DAILY, $preview['rental_type']);
    }

    public function test_preview_matches_a_monthly_create_and_saves_nothing(): void
    {
        $garage = $this->carGarage(100000);

        $preview = RentalService::preview([
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_MONTHLY,
            'start_date' => '2026-12-01',
        ]);

        $this->assertSame(0, Rental::count());

        $rental = $this->rent($garage, $this->carOf(), [
            'rental_type' => Const_Type::RENTAL_MONTHLY,
            'start_date' => '2026-12-01',
            'days' => null,
        ])->fresh();

        $this->assertSame('2026-12-31', $preview['end_date']);
        $this->assertSame($rental->end_date->toDateString(), $preview['end_date']);
        $this->assertSame(31, $preview['days']);
        $this->assertSame((float) $rental->rent_amount, $preview['rent_amount']);
        $this->assertSame(2500000.0, $preview['rent_amount']);
    }

    public function test_preview_rejects_a_bad_type_too_many_days_and_a_past_date(): void
    {
        $garage = $this->carGarage();

        $this->assertRejected(fn () => RentalService::preview(['garage_id' => $garage->id, 'rental_type' => 'weekly']));
        $this->assertRejected(fn () => RentalService::preview(['garage_id' => $garage->id, 'rental_type' => Const_Type::RENTAL_DAILY, 'days' => 30]));
        $this->assertRejected(fn () => RentalService::preview(['garage_id' => $garage->id, 'rental_type' => Const_Type::RENTAL_DAILY, 'days' => 2, 'start_date' => '2026-11-09']));
        $this->assertRejected(fn () => RentalService::preview(['garage_id' => 'missing', 'rental_type' => Const_Type::RENTAL_DAILY, 'days' => 2]));
        $this->assertSame(0, Rental::count());
    }

    public function test_active_rentals_returns_the_new_row_once_with_relations_loaded(): void
    {
        $withExtensions = $this->rent(Garage::factory()->car()->create(['code' => 'C002']), $this->carOf(), ['days' => 3]); // 10 to 12 Nov
        RentalService::extend($withExtensions, '2026-11-14', 'proofs/ext1.jpg');
        RentalService::extend($withExtensions, '2026-11-16', 'proofs/ext2.jpg');
        $plain = $this->rent(Garage::factory()->car()->create(['code' => 'C001']), $this->carOf(), ['days' => 2]);

        // Only the extension row covers this day, the new row ended on the 12th.
        Carbon::setTestNow('2026-11-14 09:00:00');
        $running = RentalService::activeRentals();

        $this->assertSame([$withExtensions->id], $running->pluck('id')->all());
        $this->assertSame(Const_Status::NEW, $running->first()->status);
        $this->assertTrue($running->first()->relationLoaded('garage'));
        $this->assertTrue($running->first()->relationLoaded('vehicle'));
        $this->assertTrue($running->first()->relationLoaded('user'));

        Carbon::setTestNow('2026-11-11 09:00:00');
        $this->assertSame(['C001', 'C002'], RentalService::activeRentals()->map(fn (Rental $rental) => $rental->garage->code)->all());
        $this->assertContains($plain->id, RentalService::activeRentals()->pluck('id')->all());
    }

    public function test_active_rentals_excludes_ended_and_future_rentals(): void
    {
        $this->rent($this->carGarage(), $this->carOf(), ['days' => 2]); // 10 to 11 Nov
        $this->rent($this->carGarage(), $this->carOf(), ['start_date' => '2026-11-20', 'days' => 2]);

        Carbon::setTestNow('2026-11-15 09:00:00');

        $this->assertCount(0, RentalService::activeRentals());

        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->assertCount(1, RentalService::activeRentals());
    }

    public function test_active_rentals_uses_a_fixed_number_of_queries(): void
    {
        foreach (range(1, 4) as $unused) {
            $this->rent($this->carGarage(), $this->carOf());
        }

        DB::enableQueryLog();
        $this->assertCount(4, RentalService::activeRentals());

        $this->assertLessThanOrEqual(4, count(DB::getQueryLog()));
    }

    public function test_period_for_a_daily_rental_defaults_to_today(): void
    {
        $this->assertSame(
            ['start_date' => '2026-11-10', 'end_date' => '2026-11-14', 'days' => 5],
            RentalService::period(Const_Type::RENTAL_DAILY, null, '5'),
        );
    }

    public function test_period_for_a_monthly_rental_covers_the_start_month_length(): void
    {
        $this->assertSame(
            ['start_date' => '2026-12-01', 'end_date' => '2026-12-31', 'days' => 31],
            RentalService::period(Const_Type::RENTAL_MONTHLY, '2026-12-01'),
        );
    }

    public function test_period_accepts_a_carbon_start_date(): void
    {
        $period = RentalService::period(Const_Type::RENTAL_DAILY, Carbon::parse('2026-11-12 15:00:00'), 2);

        $this->assertSame('2026-11-12', $period['start_date']);
        $this->assertSame('2026-11-13', $period['end_date']);
    }

    public function test_period_rejects_invalid_input_with_the_create_messages(): void
    {
        $cases = [
            ['weekly', null, 2, 'Rental type is not valid.'],
            [Const_Type::RENTAL_DAILY, '2026-11-09', 2, 'Start date cannot be before today.'],
            [Const_Type::RENTAL_DAILY, null, null, 'Number of days is required for a daily rental.'],
            [Const_Type::RENTAL_DAILY, null, 'abc', 'Number of days is required for a daily rental.'],
            [Const_Type::RENTAL_DAILY, null, 0, 'Days must be between 1 and 29 for this month.'],
            [Const_Type::RENTAL_DAILY, null, 30, 'Days must be between 1 and 29 for this month.'],
        ];

        foreach ($cases as [$type, $start, $days, $message]) {
            try {
                RentalService::period($type, $start, $days);
                $this->fail('Expected GeneralException was not thrown.');
            } catch (GeneralException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }
        }
    }

    public function test_preview_extension_matches_extend_and_saves_nothing(): void
    {
        $rental = $this->rent($this->carGarage(100000), $this->carOf(), ['days' => 3]); // 10 to 12 Nov

        $preview = RentalService::previewExtension($rental, '2026-11-15');

        $this->assertSame(1, Rental::count());
        $this->assertSame([
            'current_end_date' => '2026-11-12',
            'start_date' => '2026-11-13',
            'end_date' => '2026-11-15',
            'added_days' => 3,
            'daily_price' => 100000.0,
            'rent_amount' => 300000.0,
        ], $preview);

        $extension = RentalService::extend($rental, '2026-11-15', 'proofs/ext.jpg');

        $this->assertSame($preview['start_date'], $extension->start_date->toDateString());
        $this->assertSame($preview['end_date'], $extension->end_date->toDateString());
        $this->assertSame($preview['added_days'], $extension->days);
        $this->assertSame($preview['rent_amount'], (float) $extension->fresh()->rent_amount);
    }

    public function test_preview_extension_of_a_monthly_rental_uses_the_daily_price(): void
    {
        $rental = $this->rent($this->carGarage(100000), $this->carOf(), [
            'rental_type' => Const_Type::RENTAL_MONTHLY,
            'days' => null,
        ]); // 10 Nov to 9 Dec

        $preview = RentalService::previewExtension($rental, '2026-12-12');

        $this->assertSame(3, $preview['added_days']);
        $this->assertSame(300000.0, $preview['rent_amount']);
    }

    public function test_preview_extension_chains_from_the_latest_extension_with_any_row(): void
    {
        $rental = $this->rent($this->carGarage(100000), $this->carOf(), ['days' => 3]);
        $first = RentalService::extend($rental, '2026-11-14', 'proofs/ext1.jpg');

        $preview = RentalService::previewExtension($first, Carbon::parse('2026-11-16'));

        $this->assertSame('2026-11-14', $preview['current_end_date']);
        $this->assertSame('2026-11-15', $preview['start_date']);
        $this->assertSame(2, $preview['added_days']);
    }

    public function test_preview_extension_applies_the_same_rules_as_extend(): void
    {
        $garage = $this->carGarage();
        $rental = $this->rent($garage, $this->carOf(), ['days' => 3]); // 10 to 12 Nov
        $this->rent($garage, $this->carOf(), ['start_date' => '2026-11-16', 'days' => 3]); // 16 to 18 Nov, same garage

        $cases = [
            'same end date' => [$rental, '2026-11-12', 'The new end date must be after the current end date.'],
            'shorter' => [$rental, '2026-11-11', 'The new end date must be after the current end date.'],
            'garage booked' => [$rental, '2026-11-16', 'Garage is booked from 2026-11-16.'],
        ];

        foreach ($cases as $name => [$target, $newEnd, $message]) {
            try {
                RentalService::previewExtension($target, $newEnd);
                $this->fail("Expected GeneralException for {$name}.");
            } catch (GeneralException $exception) {
                $this->assertSame($message, $exception->getMessage(), $name);
            }
        }

        // The same vehicle is in another garage from 20 Nov.
        $vehicle = $this->carOf();
        $vehicleRental = $this->rent($this->carGarage(), $vehicle, ['days' => 3]); // 10 to 12 Nov
        $this->rent($this->carGarage(), $vehicle, ['start_date' => '2026-11-20', 'days' => 2]);

        try {
            RentalService::previewExtension($vehicleRental, '2026-11-21');
            $this->fail('Expected GeneralException for the vehicle overlap.');
        } catch (GeneralException $exception) {
            $this->assertSame('This vehicle is already rented for those dates.', $exception->getMessage());
        }
    }

    public function test_preview_extension_rejects_a_rental_that_has_ended(): void
    {
        $rental = $this->rent($this->carGarage(), $this->carOf(), ['days' => 3]); // ends 12 Nov
        Carbon::setTestNow('2026-11-13 09:00:00');

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('The rental has already ended and cannot be extended.');

        RentalService::previewExtension($rental, '2026-11-20');
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
