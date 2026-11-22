<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

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

    /**
     * @return array<string, mixed>
     */
    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dave',
            'email' => 'dave@example.com',
            'password' => '12345678',
            'nik' => '3201010101010001',
            'phone' => '081234567890',
        ], $overrides);
    }

    public function test_register_saves_a_pending_tenant_with_a_hashed_password(): void
    {
        $user = UserService::register($this->registrationData());

        $this->assertTrue($user->exists);
        $this->assertSame(Const_Role::TENANT, $user->role);
        $this->assertSame(Const_Status::PENDING, $user->registration_status);
        $this->assertNotSame('12345678', $user->password);
        $this->assertTrue(password_verify('12345678', $user->password));
        $this->assertDatabaseHas('users', ['email' => 'dave@example.com', 'nik' => '3201010101010001']);
    }

    public function test_register_forces_role_and_status_whatever_the_input_says(): void
    {
        $user = UserService::register($this->registrationData([
            'role' => Const_Role::ADMIN,
            'registration_status' => Const_Status::APPROVED,
        ]));

        $this->assertSame(Const_Role::TENANT, $user->role);
        $this->assertSame(Const_Status::PENDING, $user->registration_status);
    }

    public function test_approve_moves_a_user_to_approved(): void
    {
        $user = User::factory()->pending()->create();

        $result = UserService::approve($user);

        $this->assertSame(Const_Status::APPROVED, $result->registration_status);
        $this->assertSame(Const_Status::APPROVED, $user->fresh()->registration_status);
    }

    public function test_reject_moves_a_user_to_rejected(): void
    {
        $user = User::factory()->pending()->create();

        $result = UserService::reject($user);

        $this->assertSame(Const_Status::REJECTED, $result->registration_status);
        $this->assertSame(Const_Status::REJECTED, $user->fresh()->registration_status);
    }

    public function test_change_status_accepts_only_approved_or_rejected(): void
    {
        $user = User::factory()->pending()->create();

        foreach ([Const_Status::PENDING, 'banned', ''] as $status) {
            try {
                UserService::changeStatus($user, $status);
                $this->fail("Status '{$status}' should be rejected.");
            } catch (GeneralException $exception) {
                $this->assertSame('Status must be approved or rejected.', $exception->getMessage());
            }
        }

        $this->assertSame(Const_Status::PENDING, $user->fresh()->registration_status);
    }

    public function test_change_status_can_reverse_an_earlier_decision(): void
    {
        $user = User::factory()->rejected()->create();

        $this->assertSame(Const_Status::APPROVED, UserService::changeStatus($user, Const_Status::APPROVED)->registration_status);
    }
}
