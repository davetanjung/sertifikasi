<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Services\ChargingTransactionService;
use App\Services\RentalService;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargingTransactionServiceTest extends TestCase
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
     * A running rental of an electric car (10 kW maximum) in an electric garage (2.500 per kWh).
     *
     * @param  array<string, mixed>  $rentalOverrides
     */
    private function electricRental(array $rentalOverrides = []): Rental
    {
        $garage = Garage::factory()->electricCar()->create(['price_per_kwh' => 2500]);
        $vehicle = Vehicle::factory()->electricCar()->create(['maximum_capacity_kw' => 10]);

        return Rental::factory()->create(array_merge([
            'user_id' => $vehicle->user_id,
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
            'days' => 3,
        ], $rentalOverrides));
    }

    private function assertChargingFails(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected GeneralException was not thrown.');
        } catch (GeneralException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    // ---- preview ----------------------------------------------------------

    public function test_preview_charges_the_missing_kwh_times_the_garage_price(): void
    {
        $preview = ChargingTransactionService::preview($this->electricRental(), 2);

        $this->assertSame(['charged_kwh' => 8.0, 'price_per_kwh' => 2500.0, 'amount' => 20000.0], $preview);
    }

    public function test_preview_accepts_numeric_strings_and_decimals(): void
    {
        $preview = ChargingTransactionService::preview($this->electricRental(), '2.5');

        $this->assertSame(7.5, $preview['charged_kwh']);
        $this->assertSame(18750.0, $preview['amount']);
    }

    public function test_preview_accepts_an_empty_battery(): void
    {
        $this->assertSame(10.0, ChargingTransactionService::preview($this->electricRental(), 0)['charged_kwh']);
    }

    public function test_preview_saves_nothing(): void
    {
        ChargingTransactionService::preview($this->electricRental(), 2);

        $this->assertDatabaseCount('charging_transactions', 0);
    }

    public function test_preview_rejects_a_full_battery(): void
    {
        $this->assertChargingFails(fn () => ChargingTransactionService::preview($this->electricRental(), 10), 'Battery is already full.');
    }

    public function test_preview_rejects_a_battery_below_zero_or_above_the_maximum(): void
    {
        $rental = $this->electricRental();

        foreach ([-1, 10.5, 11] as $value) {
            $this->assertChargingFails(fn () => ChargingTransactionService::preview($rental, $value), 'Current capacity must be between 0 and 10 kW.');
        }
    }

    public function test_preview_rejects_a_value_that_is_not_a_number(): void
    {
        $this->assertChargingFails(fn () => ChargingTransactionService::preview($this->electricRental(), 'abc'), 'Current capacity must be a number.');
    }

    public function test_preview_rejects_an_extension_row(): void
    {
        $original = $this->electricRental();
        $extension = RentalService::extend($original, '2026-11-15', 'extension-proofs/a.jpg');

        $this->assertChargingFails(fn () => ChargingTransactionService::preview($extension, 2), 'Charging can only be added to the original rental, not an extension.');
    }

    public function test_preview_rejects_a_rental_that_is_not_running_today(): void
    {
        $booked = $this->electricRental(['start_date' => '2026-11-20', 'end_date' => '2026-11-22']);
        $ended = $this->electricRental(['start_date' => '2026-11-01', 'end_date' => '2026-11-05']);

        $this->assertChargingFails(fn () => ChargingTransactionService::preview($booked, 2), 'The rental is not running today.');
        $this->assertChargingFails(fn () => ChargingTransactionService::preview($ended, 2), 'The rental is not running today.');
    }

    public function test_preview_rejects_an_electric_car_in_a_normal_car_garage(): void
    {
        $rental = $this->electricRental();
        $rental->garage->update(['type' => Const_Type::GARAGE_CAR, 'price_per_kwh' => null]);

        $this->assertChargingFails(fn () => ChargingTransactionService::preview($rental->fresh(), 2), 'Charging is only available in an electric car garage.');
    }

    public function test_preview_rejects_a_vehicle_that_is_not_an_electric_car_with_a_capacity(): void
    {
        $car = $this->electricRental();
        $car->vehicle->update(['type' => Const_Type::VEHICLE_CAR]);

        $noCapacity = $this->electricRental();
        $noCapacity->vehicle->update(['maximum_capacity_kw' => null]);

        $message = 'Charging is only available for an electric car with a maximum capacity.';
        $this->assertChargingFails(fn () => ChargingTransactionService::preview($car->fresh(), 2), $message);
        $this->assertChargingFails(fn () => ChargingTransactionService::preview($noCapacity->fresh(), 2), $message);
    }

    public function test_preview_rejects_an_electric_garage_without_a_kwh_price(): void
    {
        $rental = $this->electricRental();
        $rental->garage->update(['price_per_kwh' => null]);

        $this->assertChargingFails(fn () => ChargingTransactionService::preview($rental->fresh(), 2), 'The garage has no price per kWh.');
    }

    // ---- create -----------------------------------------------------------

    public function test_create_saves_the_transaction_with_the_copied_price_and_amount(): void
    {
        $rental = $this->electricRental();

        $transaction = ChargingTransactionService::create($rental, 2, 'charging-proofs/a.jpg');

        $this->assertTrue($transaction->exists);
        $this->assertSame($rental->id, $transaction->rental_id);
        $this->assertSame('2.00', $transaction->current_capacity_kw);
        $this->assertSame('8.00', $transaction->charged_kwh);
        $this->assertSame('2500.00', $transaction->price_per_kwh);
        $this->assertSame('20000.00', $transaction->amount);
        $this->assertSame('charging-proofs/a.jpg', $transaction->payment_proof_path);
    }

    public function test_create_keeps_the_old_price_when_the_garage_price_changes_later(): void
    {
        $rental = $this->electricRental();
        $transaction = ChargingTransactionService::create($rental, 2, 'charging-proofs/a.jpg');

        $rental->garage->update(['price_per_kwh' => 9999]);

        $this->assertSame('2500.00', $transaction->fresh()->price_per_kwh);
    }

    public function test_create_allows_many_transactions_on_one_rental(): void
    {
        $rental = $this->electricRental();

        ChargingTransactionService::create($rental, 2, 'charging-proofs/a.jpg');
        ChargingTransactionService::create($rental, 5, 'charging-proofs/b.jpg');

        $this->assertSame(2, $rental->chargingTransactions()->count());
    }

    public function test_create_requires_a_payment_proof(): void
    {
        $this->assertChargingFails(fn () => ChargingTransactionService::create($this->electricRental(), 2, ''), 'Payment proof is required.');
        $this->assertDatabaseCount('charging_transactions', 0);
    }

    public function test_create_applies_the_same_rules_as_preview(): void
    {
        $rental = $this->electricRental();

        $this->assertChargingFails(fn () => ChargingTransactionService::create($rental, 10, 'charging-proofs/a.jpg'), 'Battery is already full.');
        $this->assertChargingFails(fn () => ChargingTransactionService::create($rental, 11, 'charging-proofs/a.jpg'), 'Current capacity must be between 0 and 10 kW.');
        $this->assertDatabaseCount('charging_transactions', 0);
        $this->assertSame(Const_Status::NEW, $rental->status);
    }
}
