<?php

namespace Database\Factories;

use App\Models\User;
use App\Utilities\Constants\Const_Role;
use App\Utilities\Constants\Const_Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'nik' => fake()->unique()->numerify('################'),
            'phone' => fake()->numerify('08##########'),
            'role' => Const_Role::TENANT,
            'registration_status' => Const_Status::APPROVED,
        ];
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Const_Role::ADMIN,
            'registration_status' => Const_Status::APPROVED,
        ]);
    }

    /**
     * Indicate that the registration is waiting for verification.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'registration_status' => Const_Status::PENDING,
        ]);
    }

    /**
     * Indicate that the registration was rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'registration_status' => Const_Status::REJECTED,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
