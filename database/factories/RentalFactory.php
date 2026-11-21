<?php

namespace Database\Factories;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
{
    /**
     * Define the model's default state: a new daily rental of 3 days starting today.
     * The code is left out so HasAutoNumber generates it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = today();
        $days = 3;
        $dailyPrice = fake()->numberBetween(5, 20) * 10000;

        return [
            'user_id' => User::factory(),
            'garage_id' => Garage::factory(),
            'vehicle_id' => Vehicle::factory(),
            'status' => Const_Status::NEW,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'start_date' => $startDate->toDateString(),
            'end_date' => $startDate->copy()->addDays($days - 1)->toDateString(),
            'days' => $days,
            'daily_price' => $dailyPrice,
            'rent_amount' => $dailyPrice * $days,
            'payment_proof_path' => 'payment-proofs/'.fake()->uuid().'.jpg',
        ];
    }

    /**
     * Indicate that the rental is monthly: one month counted from the start date, priced at 25 x daily price.
     */
    public function monthly(): static
    {
        return $this->state(function (array $attributes) {
            $startDate = today();
            $endDate = $startDate->copy()->addMonthNoOverflow()->subDay();

            return [
                'rental_type' => Const_Type::RENTAL_MONTHLY,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'days' => (int) $startDate->diffInDays($endDate) + 1,
                'rent_amount' => $attributes['daily_price'] * 25,
            ];
        });
    }

    /**
     * Indicate that the row is an extension: starts the day after the original 3 days and adds 2 days.
     * The code is copied from the original rental, so pass it with `['code' => ...]` when needed.
     */
    public function extension(): static
    {
        return $this->state(function (array $attributes) {
            $startDate = today()->addDays(3);
            $days = 2;

            return [
                'code' => 'RNT-'.today()->format('Ymd').'-'.str_pad((string) fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
                'status' => Const_Status::EXTENSION,
                'start_date' => $startDate->toDateString(),
                'end_date' => $startDate->copy()->addDays($days - 1)->toDateString(),
                'days' => $days,
                'rent_amount' => $attributes['daily_price'] * $days,
            ];
        });
    }
}
