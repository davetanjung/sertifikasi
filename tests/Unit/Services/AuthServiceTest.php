<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GeneralException;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_and_authenticates_an_approved_user(): void
    {
        $user = User::factory()->create(['email' => 'tenant@example.com']);

        $result = AuthService::login('tenant@example.com', 'password');

        $this->assertTrue($result->is($user));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_accepts_the_remember_flag(): void
    {
        $user = User::factory()->create(['email' => 'tenant@example.com']);

        AuthService::login('tenant@example.com', 'password', true);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_login_rejects_a_wrong_password_and_an_unknown_email_with_the_same_message(): void
    {
        User::factory()->create(['email' => 'tenant@example.com']);

        foreach ([['tenant@example.com', 'wrong-password'], ['nobody@example.com', 'password']] as [$email, $password]) {
            try {
                AuthService::login($email, $password);
                $this->fail('Login should fail.');
            } catch (GeneralException $exception) {
                $this->assertSame('These credentials do not match our records.', $exception->getMessage());
            }
        }

        $this->assertGuest();
    }

    public function test_login_tells_a_pending_user_with_the_right_password_to_wait(): void
    {
        User::factory()->pending()->create(['email' => 'pending@example.com']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Your registration is still waiting for approval.');

        AuthService::login('pending@example.com', 'password');
    }

    public function test_login_tells_a_rejected_user_with_the_right_password_so(): void
    {
        User::factory()->rejected()->create(['email' => 'rejected@example.com']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Your registration was rejected.');

        AuthService::login('rejected@example.com', 'password');
    }

    public function test_login_does_not_reveal_the_status_when_the_password_is_wrong(): void
    {
        User::factory()->pending()->create(['email' => 'pending@example.com']);

        $this->expectExceptionMessage('These credentials do not match our records.');

        AuthService::login('pending@example.com', 'wrong-password');
    }

    public function test_login_refuses_an_unknown_status(): void
    {
        User::factory()->create(['email' => 'odd@example.com', 'registration_status' => 'suspended']);

        $this->expectExceptionMessage('Your account is not allowed to log in.');

        AuthService::login('odd@example.com', 'password');
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create());

        AuthService::logout();

        $this->assertGuest();
    }

    public function test_home_is_the_dashboard_for_every_role(): void
    {
        $this->assertSame('/dashboard', AuthService::home(User::factory()->make()));
        $this->assertSame('/dashboard', AuthService::home(User::factory()->admin()->make()));
    }
}
