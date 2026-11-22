<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Utilities\Constants\Const_Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_admin_is_true_only_for_the_admin_role(): void
    {
        $this->assertTrue(User::factory()->admin()->make()->isAdmin());
        $this->assertFalse(User::factory()->make(['role' => Const_Role::TENANT])->isAdmin());
        $this->assertFalse((new User)->isAdmin());
    }

    public function test_the_password_is_hashed_when_it_is_set(): void
    {
        $user = new User(['password' => '12345678']);

        $this->assertNotSame('12345678', $user->password);
        $this->assertTrue(password_verify('12345678', $user->password));
    }

    public function test_email_verified_at_is_cast_to_a_date(): void
    {
        $user = User::factory()->create(['email_verified_at' => '2026-01-01 10:00:00'])->fresh();

        $this->assertSame('2026-01-01', $user->email_verified_at->toDateString());
    }

    public function test_vehicles_returns_only_the_tenants_own_vehicles(): void
    {
        $user = User::factory()->create();
        $mine = Vehicle::factory()->for($user)->create();
        Vehicle::factory()->create();

        $this->assertSame([$mine->id], $user->vehicles->pluck('id')->all());
    }

    public function test_rentals_returns_only_the_tenants_own_rentals(): void
    {
        $user = User::factory()->create();
        $mine = Rental::factory()->create(['user_id' => $user->id]);
        Rental::factory()->create();

        $this->assertSame([$mine->id], $user->rentals->pluck('id')->all());
    }

    public function test_the_registration_status_and_role_can_be_mass_assigned(): void
    {
        $user = new User(['role' => Const_Role::ADMIN, 'registration_status' => 'approved', 'nik' => '3201010101010001', 'phone' => '0812']);

        $this->assertSame(Const_Role::ADMIN, $user->role);
        $this->assertSame('approved', $user->registration_status);
        $this->assertSame('3201010101010001', $user->nik);
    }
}
