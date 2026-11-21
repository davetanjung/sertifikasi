<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use App\Utilities\Constants\Const_Type;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => Const_Type::VEHICLE_CAR,
            'plate_number' => fake()->unique()->bothify('B #### ???'),
            'brand' => fake()->company(),
            'model' => fake()->word(),
            'maximum_capacity_kw' => null,
        ];
    }

    /**
     * Indicate that the vehicle is a bicycle (no plate, no battery).
     */
    public function bicycle(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Const_Type::VEHICLE_BICYCLE,
            'plate_number' => null,
            'maximum_capacity_kw' => null,
        ]);
    }

    /**
     * Indicate that the vehicle is a motorcycle.
     */
    public function motorcycle(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Const_Type::VEHICLE_MOTORCYCLE,
            'maximum_capacity_kw' => null,
        ]);
    }

    /**
     * Indicate that the vehicle is a (non-electric) car.
     */
    public function car(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Const_Type::VEHICLE_CAR,
            'maximum_capacity_kw' => null,
        ]);
    }

    /**
     * Indicate that the vehicle is an electric car with a 10 kW battery.
     */
    public function electricCar(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Const_Type::VEHICLE_ELECTRIC_CAR,
            'maximum_capacity_kw' => 10,
        ]);
    }
}
