<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

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

    private function carGarage(int $dailyPrice = 100000, ?string $code = null): Garage
    {
        return Garage::factory()->car()->create(array_filter(['daily_price' => $dailyPrice, 'code' => $code]));
    }

    private function carOf(?User $user = null): Vehicle
    {
        return Vehicle::factory()->car()->for($user ?? User::factory()->create())->create();
    }

    /**
     * Rent through the service: a daily rental of 3 days starting today unless overridden.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function rent(?Garage $garage = null, ?Vehicle $vehicle = null, array $overrides = []): Rental
    {
        $garage ??= $this->carGarage();
        $vehicle ??= $this->carOf();

        return RentalService::create(array_merge([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 3,
            'payment_proof_path' => 'payment-proofs/new.jpg',
        ], $overrides));
    }

    private function assertRentalFails(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected GeneralException was not thrown.');
        } catch (GeneralException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    // ---- create -----------------------------------------------------------

    public function test_create_saves_a_daily_rental_with_dates_amount_code_and_copied_price(): void
    {
        $rental = $this->rent($this->carGarage(100000), null, ['days' => 5]);

        $this->assertSame(Const_Status::NEW, $rental->status);
        $this->assertSame('2026-11-10', $rental->start_date->toDateString());
        $this->assertSame('2026-11-14', $rental->end_date->toDateString());
        $this->assertSame(5, $rental->days);
        $this->assertSame('100000.00', $rental->daily_price);
        $this->assertSame('500000.00', $rental->rent_amount);
        $this->assertSame('RNT-20261110-0001', $rental->code);
        $this->assertSame('payment-proofs/new.jpg', $rental->payment_proof_path);
    }

    public function test_create_prices_a_monthly_rental_at_25_times_the_daily_price(): void
    {
        $rental = $this->rent($this->carGarage(100000), null, ['rental_type' => Const_Type::RENTAL_MONTHLY, 'days' => null]);

        $this->assertSame(30, $rental->days);
        $this->assertSame('2026-12-09', $rental->end_date->toDateString());
        $this->assertSame('2500000.00', $rental->rent_amount);
    }

    public function test_create_allows_a_booking_in_the_future_and_numbers_codes_in_sequence(): void
    {
        $first = $this->rent(null, null, ['start_date' => '2026-11-20']);
        $second = $this->rent();

        $this->assertSame('2026-11-20', $first->start_date->toDateString());
        $this->assertSame('RNT-20261110-0001', $first->code);
        $this->assertSame('RNT-20261110-0002', $second->code);
    }

    public function test_create_does_not_change_old_rentals_when_the_garage_price_changes_later(): void
    {
        $garage = $this->carGarage(100000);
        $rental = $this->rent($garage);

        $garage->update(['daily_price' => 999999]);

        $this->assertSame('100000.00', $rental->fresh()->daily_price);
    }

    public function test_create_rejects_a_missing_garage_or_vehicle(): void
    {
        $garage = $this->carGarage();
        $vehicle = $this->carOf();

        $this->assertRentalFails(fn () => $this->rent($garage, $vehicle, ['garage_id' => 'missing']), 'Garage was not found.');
        $this->assertRentalFails(fn () => $this->rent($garage, $vehicle, ['vehicle_id' => 'missing']), 'Vehicle was not found.');
    }

    public function test_create_rejects_a_vehicle_that_belongs_to_another_tenant(): void
    {
        $vehicle = $this->carOf();

        $this->assertRentalFails(
            fn () => $this->rent(null, $vehicle, ['user_id' => User::factory()->create()->id]),
            'The vehicle does not belong to this tenant.',
        );
    }

    public function test_create_requires_a_payment_proof(): void
    {
        foreach ([null, ''] as $path) {
            $this->assertRentalFails(fn () => $this->rent(null, null, ['payment_proof_path' => $path]), 'Payment proof is required.');
        }

        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_create_rejects_a_vehicle_that_does_not_fit_the_garage_type(): void
    {
        $bicycleGarage = Garage::factory()->bicycle()->create();

        $this->assertRentalFails(fn () => $this->rent($bicycleGarage), 'This vehicle does not fit that garage type.');
    }

    public function test_create_rejects_a_garage_that_is_booked_on_those_dates(): void
    {
        $garage = $this->carGarage();
        $this->rent($garage, null, ['days' => 3]); // 10 to 12 Nov

        $this->assertRentalFails(fn () => $this->rent($garage, null, ['start_date' => '2026-11-12']), 'Garage is not available for those dates.');
        $this->assertSame('2026-11-13', $this->rent($garage, null, ['start_date' => '2026-11-13'])->start_date->toDateString());
    }

    public function test_create_rejects_a_vehicle_that_is_in_another_garage_on_those_dates(): void
    {
        $vehicle = $this->carOf();
        $this->rent(null, $vehicle, ['days' => 3]);

        $this->assertRentalFails(fn () => $this->rent(null, $vehicle, ['days' => 2]), 'This vehicle is already rented for those dates.');
    }

    public function test_create_lets_a_tenant_rent_several_garages_at_once(): void
    {
        $user = User::factory()->create();

        $this->rent(null, $this->carOf($user));
        $this->rent(null, $this->carOf($user));

        $this->assertSame(2, Rental::where('user_id', $user->id)->count());
    }

    public function test_create_applies_the_date_rules_of_period(): void
    {
        $this->assertRentalFails(fn () => $this->rent(null, null, ['start_date' => '2026-11-09']), 'Start date cannot be before today.');
        $this->assertRentalFails(fn () => $this->rent(null, null, ['days' => 30]), 'Days must be between 1 and 29 for this month.');
        $this->assertDatabaseCount('rentals', 0);
    }

    // ---- preview ----------------------------------------------------------

    public function test_preview_returns_the_terms_without_saving_anything(): void
    {
        $garage = $this->carGarage(100000);

        $preview = RentalService::preview(['garage_id' => $garage->id, 'rental_type' => Const_Type::RENTAL_DAILY, 'days' => 4]);

        $this->assertSame([
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-13',
            'days' => 4,
            'daily_price' => 100000.0,
            'rent_amount' => 400000.0,
            'rental_type' => Const_Type::RENTAL_DAILY,
        ], $preview);
        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_preview_prices_a_monthly_rental_at_25_times_the_daily_price(): void
    {
        $preview = RentalService::preview([
            'garage_id' => $this->carGarage(100000)->id,
            'rental_type' => Const_Type::RENTAL_MONTHLY,
            'start_date' => '2026-12-01',
        ]);

        $this->assertSame(31, $preview['days']);
        $this->assertSame(2500000.0, $preview['rent_amount']);
        $this->assertSame('2026-12-31', $preview['end_date']);
    }

    public function test_preview_rejects_a_missing_garage_and_bad_dates(): void
    {
        $garage = $this->carGarage();

        $this->assertRentalFails(fn () => RentalService::preview(['rental_type' => Const_Type::RENTAL_DAILY, 'days' => 1]), 'Garage was not found.');
        $this->assertRentalFails(
            fn () => RentalService::preview(['garage_id' => $garage->id, 'rental_type' => Const_Type::RENTAL_DAILY, 'start_date' => '2026-01-01', 'days' => 1]),
            'Start date cannot be before today.',
        );
    }

    // ---- activeRentals ----------------------------------------------------

    public function test_active_rentals_lists_only_rentals_running_today_ordered_by_garage_code(): void
    {
        $b = $this->rent($this->carGarage(100000, 'C002'));
        $a = $this->rent($this->carGarage(100000, 'C001'));
        $this->rent(null, null, ['start_date' => '2026-11-20']); // booked, not running
        $ended = $this->rent();
        $ended->update(['start_date' => '2026-11-01', 'end_date' => '2026-11-05']);

        $active = RentalService::activeRentals();

        $this->assertSame([$a->id, $b->id], $active->pluck('id')->all());
        $this->assertTrue($active->first()->relationLoaded('garage'));
        $this->assertTrue($active->first()->relationLoaded('vehicle'));
        $this->assertTrue($active->first()->relationLoaded('user'));
    }

    public function test_active_rentals_returns_the_new_row_once_even_when_it_has_extensions(): void
    {
        $rental = $this->rent();
        RentalService::extend($rental, '2026-11-14', 'extension-proofs/a.jpg');

        $active = RentalService::activeRentals();

        $this->assertCount(1, $active);
        $this->assertSame(Const_Status::NEW, $active->first()->status);
    }

    public function test_active_rentals_keeps_a_rental_running_only_through_its_extension(): void
    {
        $rental = $this->rent(null, null, ['days' => 3]); // ends 12 Nov
        RentalService::extend($rental, '2026-11-20', 'extension-proofs/a.jpg');

        Carbon::setTestNow('2026-11-15 09:00:00');

        $this->assertSame([$rental->id], RentalService::activeRentals()->pluck('id')->all());
    }

    public function test_active_rentals_is_empty_when_nothing_is_running(): void
    {
        $this->assertCount(0, RentalService::activeRentals());
    }

    // ---- period -----------------------------------------------------------

    public function test_period_ends_on_start_plus_days_minus_one(): void
    {
        $this->assertSame(
            ['start_date' => '2026-11-25', 'end_date' => '2026-11-27', 'days' => 3],
            RentalService::period(Const_Type::RENTAL_DAILY, '2026-11-25', 3),
        );
        $this->assertSame('2026-11-25', RentalService::period(Const_Type::RENTAL_DAILY, '2026-11-25', 1)['end_date']);
        $this->assertSame('2026-12-02', RentalService::period(Const_Type::RENTAL_DAILY, '2026-11-28', 5)['end_date']);
    }

    public function test_period_accepts_numeric_string_days_and_carbon_dates(): void
    {
        $this->assertSame(4, RentalService::period(Const_Type::RENTAL_DAILY, '2026-11-25', '4')['days']);
        $this->assertSame('2026-11-20', RentalService::period(Const_Type::RENTAL_DAILY, Carbon::parse('2026-11-20 15:00:00'), 1)['start_date']);
    }

    public function test_period_uses_today_when_the_start_date_is_blank(): void
    {
        foreach ([null, ''] as $blank) {
            $period = RentalService::period(Const_Type::RENTAL_DAILY, $blank, 2);

            $this->assertSame('2026-11-10', $period['start_date']);
            $this->assertSame('2026-11-11', $period['end_date']);
        }
    }

    public function test_period_rejects_a_past_start_date(): void
    {
        $this->assertRentalFails(fn () => RentalService::period(Const_Type::RENTAL_DAILY, '2026-11-09', 2), 'Start date cannot be before today.');
    }

    public function test_period_limits_daily_days_to_the_days_in_the_month_minus_one(): void
    {
        $this->assertSame(29, RentalService::period(Const_Type::RENTAL_DAILY, '2026-11-20', 29)['days']);
        $this->assertRentalFails(fn () => RentalService::period(Const_Type::RENTAL_DAILY, '2026-11-20', 30), 'Days must be between 1 and 29 for this month.');
        $this->assertSame(27, RentalService::period(Const_Type::RENTAL_DAILY, '2027-02-01', 27)['days']);
        $this->assertRentalFails(fn () => RentalService::period(Const_Type::RENTAL_DAILY, '2027-02-01', 28), 'Days must be between 1 and 27 for this month.');
    }

    public function test_period_rejects_zero_negative_missing_and_non_numeric_days(): void
    {
        foreach ([0, -1, '0'] as $days) {
            $this->assertRentalFails(fn () => RentalService::period(Const_Type::RENTAL_DAILY, '2026-11-25', $days), 'Days must be between 1 and 29 for this month.');
        }

        foreach ([null, '', 'abc'] as $days) {
            $this->assertRentalFails(fn () => RentalService::period(Const_Type::RENTAL_DAILY, '2026-11-25', $days), 'Number of days is required for a daily rental.');
        }
    }

    public function test_period_makes_a_monthly_rental_last_the_whole_start_month(): void
    {
        $this->assertSame(
            ['start_date' => '2026-11-25', 'end_date' => '2026-12-24', 'days' => 30],
            RentalService::period(Const_Type::RENTAL_MONTHLY, '2026-11-25'),
        );
        $this->assertSame(31, RentalService::period(Const_Type::RENTAL_MONTHLY, '2026-12-01', 5)['days']);
        $this->assertSame('2027-02-28', RentalService::period(Const_Type::RENTAL_MONTHLY, '2027-02-01')['end_date']);
    }

    public function test_period_rejects_an_unknown_rental_type(): void
    {
        $this->assertRentalFails(fn () => RentalService::period('weekly', '2026-11-25', 2), 'Rental type is not valid.');
    }

    // ---- extend -----------------------------------------------------------

    public function test_extend_adds_an_extension_row_with_the_same_code_and_the_next_dates(): void
    {
        $rental = $this->rent($this->carGarage(100000)); // 10 to 12 Nov

        $extension = RentalService::extend($rental, '2026-11-15', 'extension-proofs/a.jpg');

        $this->assertSame(Const_Status::EXTENSION, $extension->status);
        $this->assertSame($rental->code, $extension->code);
        $this->assertSame('2026-11-13', $extension->start_date->toDateString());
        $this->assertSame('2026-11-15', $extension->end_date->toDateString());
        $this->assertSame(3, $extension->days);
        $this->assertSame('300000.00', $extension->rent_amount);
        $this->assertSame('100000.00', $extension->daily_price);
        $this->assertSame('extension-proofs/a.jpg', $extension->payment_proof_path);
        $this->assertSame($rental->user_id, $extension->user_id);
        $this->assertSame($rental->garage_id, $extension->garage_id);
        $this->assertSame($rental->vehicle_id, $extension->vehicle_id);
    }

    public function test_extend_charges_a_monthly_rental_by_the_added_days_times_the_daily_price(): void
    {
        $rental = $this->rent($this->carGarage(100000), null, ['rental_type' => Const_Type::RENTAL_MONTHLY, 'days' => null]); // to 9 Dec

        $extension = RentalService::extend($rental, '2026-12-12', 'extension-proofs/a.jpg');

        $this->assertSame(3, $extension->days);
        $this->assertSame('300000.00', $extension->rent_amount);
    }

    public function test_extend_can_start_from_an_extension_row_and_continues_after_the_latest_end(): void
    {
        $rental = $this->rent();
        $first = RentalService::extend($rental, '2026-11-15', 'extension-proofs/a.jpg');

        $second = RentalService::extend($first, '2026-11-17', 'extension-proofs/b.jpg');

        $this->assertSame('2026-11-16', $second->start_date->toDateString());
        $this->assertSame(2, $second->days);
        $this->assertSame($rental->code, $second->code);
        $this->assertSame(3, Rental::where('code', $rental->code)->count());
    }

    public function test_extend_requires_a_payment_proof_and_saves_nothing_without_it(): void
    {
        $rental = $this->rent();

        $this->assertRentalFails(fn () => RentalService::extend($rental, '2026-11-15', ''), 'Payment proof is required.');
        $this->assertDatabaseCount('rentals', 1);
    }

    public function test_extend_rejects_a_rental_that_has_already_ended(): void
    {
        $rental = $this->rent();
        Carbon::setTestNow('2026-11-20 09:00:00');

        $this->assertRentalFails(fn () => RentalService::extend($rental, '2026-11-25', 'extension-proofs/a.jpg'), 'The rental has already ended and cannot be extended.');
    }

    public function test_extend_only_moves_the_end_date_later(): void
    {
        $rental = $this->rent(); // ends 12 Nov

        foreach (['2026-11-12', '2026-11-11', '2026-11-01'] as $date) {
            $this->assertRentalFails(fn () => RentalService::extend($rental, $date, 'extension-proofs/a.jpg'), 'The new end date must be after the current end date.');
        }
    }

    public function test_extend_is_blocked_by_another_booking_of_the_garage(): void
    {
        $garage = $this->carGarage();
        $rental = $this->rent($garage);
        $this->rent($garage, null, ['start_date' => '2026-11-16', 'days' => 2]);

        $this->assertRentalFails(fn () => RentalService::extend($rental, '2026-11-17', 'extension-proofs/a.jpg'), 'Garage is booked from 2026-11-16.');
        $this->assertSame('2026-11-15', RentalService::extend($rental, '2026-11-15', 'extension-proofs/a.jpg')->end_date->toDateString());
    }

    public function test_extend_is_blocked_when_the_vehicle_is_booked_in_another_garage(): void
    {
        $vehicle = $this->carOf();
        $rental = $this->rent(null, $vehicle); // 10 to 12 Nov
        $this->rent(null, $vehicle, ['start_date' => '2026-11-14', 'days' => 2]);

        $this->assertRentalFails(fn () => RentalService::extend($rental, '2026-11-15', 'extension-proofs/a.jpg'), 'This vehicle is already rented for those dates.');
    }

    // ---- previewExtension -------------------------------------------------

    public function test_preview_extension_shows_what_extend_would_save_without_saving(): void
    {
        $rental = $this->rent($this->carGarage(100000));

        $preview = RentalService::previewExtension($rental, '2026-11-15');

        $this->assertSame([
            'current_end_date' => '2026-11-12',
            'start_date' => '2026-11-13',
            'end_date' => '2026-11-15',
            'added_days' => 3,
            'daily_price' => 100000.0,
            'rent_amount' => 300000.0,
        ], $preview);
        $this->assertDatabaseCount('rentals', 1);

        $saved = RentalService::extend($rental, '2026-11-15', 'extension-proofs/a.jpg');
        $this->assertSame($preview['added_days'], $saved->days);
        $this->assertSame($preview['rent_amount'], (float) $saved->rent_amount);
    }

    public function test_preview_extension_applies_the_same_rules_as_extend(): void
    {
        $garage = $this->carGarage();
        $rental = $this->rent($garage);
        $this->rent($garage, null, ['start_date' => '2026-11-16', 'days' => 2]);

        $this->assertRentalFails(fn () => RentalService::previewExtension($rental, '2026-11-12'), 'The new end date must be after the current end date.');
        $this->assertRentalFails(fn () => RentalService::previewExtension($rental, '2026-11-17'), 'Garage is booked from 2026-11-16.');
    }

    // ---- latestEndDate ----------------------------------------------------

    public function test_latest_end_date_is_the_end_date_of_a_rental_without_extensions(): void
    {
        $this->assertSame('2026-11-12', RentalService::latestEndDate($this->rent())->toDateString());
    }

    public function test_latest_end_date_includes_extensions_from_any_row(): void
    {
        $rental = $this->rent();
        $first = RentalService::extend($rental, '2026-11-15', 'extension-proofs/a.jpg');
        RentalService::extend($first, '2026-11-18', 'extension-proofs/b.jpg');

        $this->assertSame('2026-11-18', RentalService::latestEndDate($rental)->toDateString());
        $this->assertSame('2026-11-18', RentalService::latestEndDate($first)->toDateString());
    }

    public function test_latest_end_date_ignores_other_rentals_of_the_same_garage(): void
    {
        $garage = $this->carGarage();
        $rental = $this->rent($garage);
        $this->rent($garage, null, ['start_date' => '2026-11-20', 'days' => 5]);

        $this->assertSame('2026-11-12', RentalService::latestEndDate($rental)->toDateString());
    }

    // ---- isActive ---------------------------------------------------------

    public function test_is_active_is_true_while_today_is_inside_the_rental(): void
    {
        $rental = $this->rent();

        $this->assertTrue(RentalService::isActive($rental));

        Carbon::setTestNow('2026-11-12 23:00:00');
        $this->assertTrue(RentalService::isActive($rental));
    }

    public function test_is_active_is_false_before_the_start_and_after_the_end(): void
    {
        $booked = $this->rent(null, null, ['start_date' => '2026-11-20']);
        $this->assertFalse(RentalService::isActive($booked));

        $rental = $this->rent();
        Carbon::setTestNow('2026-11-13 00:00:00');
        $this->assertFalse(RentalService::isActive($rental));
    }

    public function test_is_active_counts_the_extension_as_part_of_the_rental(): void
    {
        $rental = $this->rent();
        $extension = RentalService::extend($rental, '2026-11-15', 'extension-proofs/a.jpg');

        Carbon::setTestNow('2026-11-14 09:00:00');

        $this->assertTrue(RentalService::isActive($rental));
        $this->assertTrue(RentalService::isActive($extension));

        Carbon::setTestNow('2026-11-16 09:00:00');
        $this->assertFalse(RentalService::isActive($rental));
    }
}
