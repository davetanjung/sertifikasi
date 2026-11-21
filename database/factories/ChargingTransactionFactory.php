<?php

namespace Database\Factories;

use App\Models\ChargingTransaction;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargingTransaction>
 */
class ChargingTransactionFactory extends Factory
{
    /**
     * Define the model's default state: a 10 kW battery charged from a random level.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currentCapacityKw = fake()->numberBetween(1, 9);
        $chargedKwh = 10 - $currentCapacityKw;
        $pricePerKwh = fake()->numberBetween(2, 5) * 1000;

        return [
            'rental_id' => Rental::factory(),
            'current_capacity_kw' => $currentCapacityKw,
            'charged_kwh' => $chargedKwh,
            'price_per_kwh' => $pricePerKwh,
            'amount' => $chargedKwh * $pricePerKwh,
            'payment_proof_path' => 'payment-proofs/'.fake()->uuid().'.jpg',
        ];
    }
}
