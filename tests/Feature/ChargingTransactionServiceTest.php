<?php

namespace Tests\Feature;

use App\Exceptions\GeneralException;
use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Services\ChargingTransactionService;
use App\Services\RentalService;
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
     * An electric car (max 10 kW) renting an electric garage (price 2500 per kWh) through the service.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function electricRental(array $overrides = []): Rental
    {
        $garage = Garage::factory()->electricCar()->create(['price_per_kwh' => 2500]);
        $vehicle = Vehicle::factory()->electricCar()->create();

        return RentalService::create(array_merge([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 3,
            'payment_proof_path' => 'proofs/new.jpg',
        ], $overrides));
    }

    private function assertRejected(callable $callback, string $message): void
    {
        try {
            $callback();
        } catch (GeneralException $exception) {
            $this->assertSame($message, $exception->getMessage());

            return;
        }

        $this->fail('Expected GeneralException was not thrown.');
    }

    public function test_charging_computes_kwh_and_amount_and_copies_the_price(): void
    {
        $rental = $this->electricRental();

        $transaction = ChargingTransactionService::create($rental, 2, 'proofs/charge.jpg');

        $fresh = $transaction->fresh();
        $this->assertSame($rental->id, $fresh->rental_id);
        $this->assertSame('2.00', $fresh->current_capacity_kw);
        $this->assertSame('8.00', $fresh->charged_kwh);
        $this->assertSame('2500.00', $fresh->price_per_kwh);
        $this->assertSame('20000.00', $fresh->amount);
        $this->assertSame('proofs/charge.jpg', $fresh->payment_proof_path);
    }

    public function test_decimal_capacity_is_handled_to_two_places(): void
    {
        $transaction = ChargingTransactionService::create($this->electricRental(), '2.5', 'proofs/charge.jpg');

        $this->assertSame('7.50', $transaction->fresh()->charged_kwh);
        $this->assertSame('18750.00', $transaction->fresh()->amount);
    }

    public function test_several_transactions_are_allowed_for_the_same_rental(): void
    {
        $rental = $this->electricRental();

        ChargingTransactionService::create($rental, 2, 'proofs/one.jpg');
        ChargingTransactionService::create($rental, 5, 'proofs/two.jpg');

        $this->assertSame(2, ChargingTransaction::where('rental_id', $rental->id)->count());
    }

    public function test_rejected_for_a_car_garage(): void
    {
        $garage = Garage::factory()->car()->create();
        $vehicle = Vehicle::factory()->electricCar()->create();
        $rental = RentalService::create([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 3,
            'payment_proof_path' => 'proofs/new.jpg',
        ]);

        $this->assertRejected(
            fn () => ChargingTransactionService::create($rental, 2, 'proofs/charge.jpg'),
            'Charging is only available in an electric car garage.'
        );
    }

    public function test_rejected_for_a_non_electric_vehicle(): void
    {
        // The rental service refuses a plain car in an electric garage, so the row is built by the factory.
        $rental = Rental::factory()->create([
            'garage_id' => Garage::factory()->electricCar(),
            'vehicle_id' => Vehicle::factory()->car(),
        ]);

        $this->assertRejected(
            fn () => ChargingTransactionService::create($rental, 2, 'proofs/charge.jpg'),
            'Charging is only available for an electric car with a maximum capacity.'
        );
    }

    public function test_rejected_when_the_rental_has_not_started(): void
    {
        $rental = $this->electricRental(['start_date' => '2026-11-12']);

        $this->assertRejected(
            fn () => ChargingTransactionService::create($rental, 2, 'proofs/charge.jpg'),
            'The rental is not running today.'
        );
    }

    public function test_rejected_when_the_rental_has_ended(): void
    {
        $rental = $this->electricRental(['days' => 3]); // 10 to 12 Nov
        Carbon::setTestNow('2026-11-13 09:00:00');

        $this->assertRejected(
            fn () => ChargingTransactionService::create($rental, 2, 'proofs/charge.jpg'),
            'The rental is not running today.'
        );
    }

    public function test_allowed_during_an_extension_window(): void
    {
        $rental = $this->electricRental(['days' => 3]); // 10 to 12 Nov
        RentalService::extend($rental, '2026-11-15', 'proofs/ext.jpg');
        Carbon::setTestNow('2026-11-14 09:00:00');

        $transaction = ChargingTransactionService::create($rental, 2, 'proofs/charge.jpg');

        $this->assertSame($rental->id, $transaction->rental_id);
    }

    public function test_rejected_when_passing_an_extension_row(): void
    {
        $rental = $this->electricRental(['days' => 3]);
        $extension = RentalService::extend($rental, '2026-11-15', 'proofs/ext.jpg');

        $this->assertRejected(
            fn () => ChargingTransactionService::create($extension, 2, 'proofs/charge.jpg'),
            'Charging can only be added to the original rental, not an extension.'
        );
    }

    public function test_current_capacity_above_the_maximum_is_rejected(): void
    {
        $rental = $this->electricRental();

        $this->assertRejected(
            fn () => ChargingTransactionService::create($rental, 11, 'proofs/charge.jpg'),
            'Current capacity must be between 0 and 10 kW.'
        );
    }

    public function test_current_capacity_equal_to_the_maximum_means_the_battery_is_full(): void
    {
        $rental = $this->electricRental();

        $this->assertRejected(
            fn () => ChargingTransactionService::create($rental, 10, 'proofs/charge.jpg'),
            'Battery is already full.'
        );
        $this->assertSame(0, ChargingTransaction::count());
    }

    public function test_negative_current_capacity_is_rejected(): void
    {
        $rental = $this->electricRental();

        $this->assertRejected(
            fn () => ChargingTransactionService::create($rental, -1, 'proofs/charge.jpg'),
            'Current capacity must be between 0 and 10 kW.'
        );
    }

    public function test_preview_matches_create_and_saves_nothing(): void
    {
        $rental = $this->electricRental();

        $preview = ChargingTransactionService::preview($rental, 2);

        $this->assertSame(0, ChargingTransaction::count());
        $this->assertSame(8.0, $preview['charged_kwh']);
        $this->assertSame(2500.0, $preview['price_per_kwh']);
        $this->assertSame(20000.0, $preview['amount']);

        $saved = ChargingTransactionService::create($rental, 2, 'proofs/charge.jpg')->fresh();

        $this->assertSame($preview['charged_kwh'], (float) $saved->charged_kwh);
        $this->assertSame($preview['price_per_kwh'], (float) $saved->price_per_kwh);
        $this->assertSame($preview['amount'], (float) $saved->amount);
    }

    public function test_preview_applies_the_same_rules_as_create(): void
    {
        $rental = $this->electricRental();

        $this->assertRejected(
            fn () => ChargingTransactionService::preview($rental, 10),
            'Battery is already full.'
        );
        $this->assertRejected(
            fn () => ChargingTransactionService::preview($rental, 11),
            'Current capacity must be between 0 and 10 kW.'
        );
        $this->assertSame(0, ChargingTransaction::count());
    }

    public function test_create_still_requires_a_payment_proof(): void
    {
        $this->assertRejected(
            fn () => ChargingTransactionService::create($this->electricRental(), 2, ''),
            'Payment proof is required.'
        );
        $this->assertSame(0, ChargingTransaction::count());
    }

    public function test_zero_current_capacity_charges_the_full_battery(): void
    {
        $transaction = ChargingTransactionService::create($this->electricRental(), 0, 'proofs/charge.jpg');

        $this->assertSame('10.00', $transaction->fresh()->charged_kwh);
        $this->assertSame('25000.00', $transaction->fresh()->amount);
    }
}
