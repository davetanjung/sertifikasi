<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\User;
use App\Utilities\Constants\Const_Role;
use App\Utilities\Constants\Const_Status;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_MESSAGE = 'These credentials do not match our records.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, string>
     */
    private function validRegistration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Budi',
            'nik' => '1234567890123456',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ], $overrides);
    }

    private function fillRegister($component, array $data)
    {
        foreach ($data as $key => $value) {
            $component->set($key, $value);
        }

        return $component;
    }

    public function test_login_and_register_pages_render_for_a_guest(): void
    {
        $this->get('/login')->assertOk()->assertSeeLivewire(Login::class);
        $this->get('/register')->assertOk()->assertSeeLivewire(Register::class);
    }

    public function test_logged_in_users_are_redirected_away_from_guest_pages(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get('/login')->assertRedirect('/dashboard');
        $this->get('/register')->assertRedirect('/dashboard');

        $this->actingAs(User::factory()->create());
        $this->get('/login')->assertRedirect('/dashboard');
    }

    public function test_admin_logs_in_and_is_redirected_to_the_dashboard(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'admin@example.com')
            ->set('password', 'password')
            ->call('process')
            ->assertRedirect(url('/dashboard'));

        $this->assertAuthenticated();
    }

    public function test_tenant_logs_in_and_is_redirected_to_the_dashboard(): void
    {
        User::factory()->create(['email' => 'tenant@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'tenant@example.com')
            ->set('password', 'password')
            ->call('process')
            ->assertRedirect(url('/dashboard'));

        $this->assertAuthenticated();
    }

    public function test_seeded_admin_email_without_a_dot_can_log_in(): void
    {
        $this->seed(AdminSeeder::class);

        Livewire::test(Login::class)
            ->set('email', 'admin@test')
            ->set('password', '12345678')
            ->call('process')
            ->assertHasNoErrors()
            ->assertRedirect(url('/dashboard'));

        $this->assertAuthenticated();
    }

    public function test_wrong_password_and_unknown_email_show_the_generic_message(): void
    {
        User::factory()->create(['email' => 'tenant@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'tenant@example.com')
            ->set('password', 'wrong-password')
            ->call('process')
            ->assertHasErrors(['flash_danger'])
            ->assertSee(self::GENERIC_MESSAGE);

        Livewire::test(Login::class)
            ->set('email', 'missing@example.com')
            ->set('password', 'password')
            ->call('process')
            ->assertHasErrors(['flash_danger'])
            ->assertSee(self::GENERIC_MESSAGE);

        $this->assertGuest();
    }

    public function test_pending_tenant_sees_the_waiting_message_and_is_not_authenticated(): void
    {
        User::factory()->pending()->create(['email' => 'tenant@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'tenant@example.com')
            ->set('password', 'password')
            ->call('process')
            ->assertHasErrors(['flash_danger'])
            ->assertSee('Your registration is still waiting for approval.');

        $this->assertGuest();
    }

    public function test_rejected_tenant_sees_the_rejected_message(): void
    {
        User::factory()->rejected()->create(['email' => 'tenant@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'tenant@example.com')
            ->set('password', 'password')
            ->call('process')
            ->assertHasErrors(['flash_danger'])
            ->assertSee('Your registration was rejected.');

        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        Livewire::test(Login::class)
            ->call('process')
            ->assertHasErrors(['email' => 'required', 'password' => 'required']);
    }

    public function test_register_requires_every_field(): void
    {
        Livewire::test(Register::class)
            ->call('submit')
            ->assertHasErrors([
                'name' => 'required',
                'nik' => 'required',
                'phone' => 'required',
                'email' => 'required',
                'password' => 'required',
            ]);
    }

    public function test_register_rejects_a_nik_that_is_not_16_digits(): void
    {
        $this->fillRegister(Livewire::test(Register::class), $this->validRegistration(['nik' => '12345']))
            ->call('submit')
            ->assertHasErrors(['nik' => 'digits']);

        $this->fillRegister(Livewire::test(Register::class), $this->validRegistration(['nik' => '12345678901234ab']))
            ->call('submit')
            ->assertHasErrors(['nik' => 'digits']);
    }

    public function test_register_rejects_a_duplicate_nik(): void
    {
        User::factory()->create(['nik' => '1234567890123456']);

        $this->fillRegister(Livewire::test(Register::class), $this->validRegistration())
            ->call('submit')
            ->assertHasErrors(['nik' => 'unique']);
    }

    public function test_register_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        $this->fillRegister(Livewire::test(Register::class), $this->validRegistration())
            ->call('submit')
            ->assertHasErrors(['email' => 'unique']);
    }

    public function test_register_rejects_an_invalid_email(): void
    {
        $this->fillRegister(Livewire::test(Register::class), $this->validRegistration(['email' => 'not-an-email']))
            ->call('submit')
            ->assertHasErrors(['email' => 'email']);
    }

    public function test_register_rejects_a_short_password(): void
    {
        $this->fillRegister(Livewire::test(Register::class), $this->validRegistration([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))
            ->call('submit')
            ->assertHasErrors(['password' => 'min']);
    }

    public function test_register_rejects_an_unconfirmed_password(): void
    {
        $this->fillRegister(Livewire::test(Register::class), $this->validRegistration([
            'password_confirmation' => 'different-pass',
        ]))
            ->call('submit')
            ->assertHasErrors(['password' => 'confirmed']);
    }

    public function test_valid_registration_creates_a_pending_tenant_without_logging_in(): void
    {
        $component = $this->fillRegister(Livewire::test(Register::class), $this->validRegistration([
            'nik' => '1234 5678 9012 3456',
        ]))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('registered', true)
            ->assertSet('password', null);

        $component->assertSee('Your registration was received.');

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame(Const_Role::TENANT, $user->role);
        $this->assertSame(Const_Status::PENDING, $user->registration_status);
        $this->assertSame('1234567890123456', $user->nik);
        $this->assertNotSame('secret-pass', $user->password);
        $this->assertTrue(Hash::check('secret-pass', $user->password));
        $this->assertGuest();
    }

    public function test_logout_logs_the_user_out_and_redirects_to_login(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_dashboard_redirects_a_guest_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_dashboard_is_open_to_an_approved_tenant(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Budi Tenant']))->get('/dashboard')
            ->assertOk()
            ->assertSee('Welcome, Budi Tenant');
    }

    public function test_dashboard_greets_an_admin_and_the_navbar_shows_logout(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Jane Admin']);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Welcome, Jane Admin')
            ->assertSee('Logout');
    }

    public function test_a_tenant_whose_approval_is_revoked_is_logged_out_on_the_next_request(): void
    {
        $tenant = User::factory()->create();
        $this->actingAs($tenant);

        $tenant->update(['registration_status' => Const_Status::REJECTED]);

        $this->get('/dashboard')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_dashboard_loads_the_livewire_assets_so_the_user_menu_works(): void
    {
        $admin = User::factory()->admin()->create();

        // The dashboard is a plain Blade page. Alpine ships with Livewire, so without these assets the
        // user menu (Alpine x-data) never opens and the Logout button cannot be reached.
        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('livewire.js', false)
            ->assertSee('x-data', false);
    }
}
