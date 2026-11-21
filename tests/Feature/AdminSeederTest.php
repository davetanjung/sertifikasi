<?php

namespace Tests\Feature;

use App\Models\User;
use App\Utilities\Constants\Const_Role;
use App\Utilities\Constants\Const_Status;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_approved_admin_with_the_known_credentials(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'admin@test')->firstOrFail();

        $this->assertSame(Const_Role::ADMIN, $admin->role);
        $this->assertSame(Const_Status::APPROVED, $admin->registration_status);
        $this->assertTrue(Hash::check('12345678', $admin->password));
    }

    public function test_running_it_twice_does_not_duplicate_the_admin(): void
    {
        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@test')->count());
    }
}
