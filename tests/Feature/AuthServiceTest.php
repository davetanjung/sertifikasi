<?php

namespace Tests\Feature;

use App\Exceptions\GeneralException;
use App\Models\User;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_MESSAGE = 'These credentials do not match our records.';

    private function assertLoginFails(string $email, string $password, string $message): void
    {
        try {
            AuthService::login($email, $password);
            $this->fail('Expected GeneralException was not thrown.');
        } catch (GeneralException $e) {
            $this->assertSame($message, $e->getMessage());
        }

        $this->assertGuest();
    }

    public function test_approved_tenant_logs_in(): void
    {
        $user = User::factory()->create(['email' => 'tenant@example.com']);

        $result = AuthService::login('tenant@example.com', 'password');

        $this->assertTrue($result->is($user));
        $this->assertAuthenticatedAs($user);
    }

    public function test_approved_admin_logs_in(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

        AuthService::login('admin@example.com', 'password');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_password_and_unknown_email_give_the_same_message(): void
    {
        User::factory()->create(['email' => 'tenant@example.com']);

        $this->assertLoginFails('tenant@example.com', 'wrong-password', self::GENERIC_MESSAGE);
        $this->assertLoginFails('missing@example.com', 'password', self::GENERIC_MESSAGE);
    }

    public function test_pending_tenant_with_right_password_gets_the_pending_message(): void
    {
        User::factory()->pending()->create(['email' => 'tenant@example.com']);

        $this->assertLoginFails('tenant@example.com', 'password', 'Your registration is still waiting for approval.');
    }

    public function test_rejected_tenant_gets_the_rejected_message(): void
    {
        User::factory()->rejected()->create(['email' => 'tenant@example.com']);

        $this->assertLoginFails('tenant@example.com', 'password', 'Your registration was rejected.');
    }

    public function test_pending_tenant_with_wrong_password_gets_the_generic_message(): void
    {
        User::factory()->pending()->create(['email' => 'tenant@example.com']);

        $this->assertLoginFails('tenant@example.com', 'wrong-password', self::GENERIC_MESSAGE);
    }

    public function test_full_flow_register_then_pending_then_approve_then_login(): void
    {
        $user = UserService::register([
            'name' => 'Budi',
            'email' => 'tenant@example.com',
            'password' => 'secret-pass',
            'nik' => '1234567890123456',
            'phone' => '081234567890',
        ]);

        $this->assertLoginFails('tenant@example.com', 'secret-pass', 'Your registration is still waiting for approval.');

        UserService::approve($user);

        AuthService::login('tenant@example.com', 'secret-pass');

        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_flag_sets_a_remember_token(): void
    {
        $user = User::factory()->create(['email' => 'tenant@example.com', 'remember_token' => null]);

        AuthService::login('tenant@example.com', 'password', true);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_logout_clears_authentication(): void
    {
        User::factory()->create(['email' => 'tenant@example.com']);
        AuthService::login('tenant@example.com', 'password');
        $this->assertAuthenticated();

        AuthService::logout();

        $this->assertGuest();
        $this->assertNull(Auth::user());
    }

    public function test_home_is_the_dashboard_for_every_role(): void
    {
        $this->assertSame('/dashboard', AuthService::home(User::factory()->admin()->make()));
        $this->assertSame('/dashboard', AuthService::home(User::factory()->make()));
    }
}
