<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_render_shows_the_login_form_with_remember_off(): void
    {
        Livewire::test(Login::class)
            ->assertOk()
            ->assertSet('remember', false)
            ->assertSee('Login');
    }

    public function test_rules_require_an_email_and_a_password(): void
    {
        Livewire::test(Login::class)
            ->call('process')
            ->assertHasErrors(['email' => 'required', 'password' => 'required']);
    }

    public function test_process_logs_an_approved_user_in_and_redirects_to_the_dashboard(): void
    {
        $user = User::factory()->create(['email' => 'tenant@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'tenant@example.com')
            ->set('password', 'password')
            ->call('process')
            ->assertHasNoErrors()
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_process_passes_the_remember_flag_to_the_service(): void
    {
        $user = User::factory()->create(['email' => 'tenant@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'tenant@example.com')
            ->set('password', 'password')
            ->set('remember', true)
            ->call('process');

        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_process_shows_the_service_message_for_wrong_credentials(): void
    {
        User::factory()->create(['email' => 'tenant@example.com']);

        $component = Livewire::test(Login::class)
            ->set('email', 'tenant@example.com')
            ->set('password', 'wrong-password')
            ->call('process')
            ->assertNoRedirect();

        $this->assertSame('These credentials do not match our records.', $component->errors()->first('flash_danger'));
        $this->assertGuest();
    }

    public function test_process_shows_a_separate_message_for_pending_and_rejected_users(): void
    {
        User::factory()->pending()->create(['email' => 'pending@example.com']);
        User::factory()->rejected()->create(['email' => 'rejected@example.com']);

        $pending = Livewire::test(Login::class)->set('email', 'pending@example.com')->set('password', 'password')->call('process');
        $rejected = Livewire::test(Login::class)->set('email', 'rejected@example.com')->set('password', 'password')->call('process');

        $this->assertSame('Your registration is still waiting for approval.', $pending->errors()->first('flash_danger'));
        $this->assertSame('Your registration was rejected.', $rejected->errors()->first('flash_danger'));
        $this->assertGuest();
    }
}
