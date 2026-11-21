<?php

namespace Tests\Feature;

use App\Exceptions\GeneralException;
use App\Models\User;
use App\Services\UserService;
use App\Utilities\Constants\Const_Role;
use App\Utilities\Constants\Const_Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_forces_tenant_and_pending(): void
    {
        $user = UserService::register([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'secret-pass',
            'nik' => '1234567890123456',
            'phone' => '081234567890',
            'role' => Const_Role::ADMIN,
            'registration_status' => Const_Status::APPROVED,
        ]);

        $this->assertSame(Const_Role::TENANT, $user->role);
        $this->assertSame(Const_Status::PENDING, $user->registration_status);
        $this->assertNotSame('secret-pass', $user->password);
        $this->assertDatabaseHas('users', [
            'email' => 'budi@example.com',
            'role' => Const_Role::TENANT,
            'registration_status' => Const_Status::PENDING,
        ]);
    }

    public function test_approve_a_pending_tenant(): void
    {
        $user = User::factory()->pending()->create();

        $result = UserService::approve($user);

        $this->assertSame(Const_Status::APPROVED, $result->registration_status);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'registration_status' => Const_Status::APPROVED]);
    }

    public function test_reject_a_pending_tenant(): void
    {
        $user = User::factory()->pending()->create();

        $result = UserService::reject($user);

        $this->assertSame(Const_Status::REJECTED, $result->registration_status);
    }

    public function test_change_status_rejects_other_target_statuses(): void
    {
        $user = User::factory()->pending()->create();

        $this->expectException(GeneralException::class);

        UserService::changeStatus($user, Const_Status::PENDING);
    }
}
