<?php

namespace Database\Factories;

use App\Models\Garage;
use App\Utilities\Constants\Const_Type;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Garage>
 */
class GarageFactory extends Factory
{
    /**
     * Define the model's default state. The code is left out so HasAutoNumber generates it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => Const_Type::GARAGE_CAR,
            'daily_price' => fake()->numberBetween(5, 20) * 10000,
            'price_per_kwh' => null,
        ];
    }

    /**
     * Indicate that the garage is a bicycle garage.
     */
    public function bicycle(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Const_Type::GARAGE_BICYCLE,
            'price_per_kwh' => null,
        ]);
    }

    /**
     * Indicate that the garage is a car garage.
     */
    public function car(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Const_Type::GARAGE_CAR,
            'price_per_kwh' => null,
        ]);
    }

    /**
     * Indicate that the garage is an electric car garage with a charging price.
     */
    public function electricCar(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Const_Type::GARAGE_ELECTRIC_CAR,
            'price_per_kwh' => fake()->numberBetween(2, 5) * 1000,
        ]);
    }
}
