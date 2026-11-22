<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Auth;

use App\Livewire\Auth\Register;
use App\Models\User;
use App\Utilities\Constants\Const_Role;
use App\Utilities\Constants\Const_Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function filled(array $overrides = [])
    {
        $values = array_merge([
            'name' => 'Dave',
            'nik' => '3201010101010001',
            'phone' => '081234567890',
            'email' => 'dave@example.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ], $overrides);

        $component = Livewire::test(Register::class);

        foreach ($values as $field => $value) {
            $component->set($field, $value);
        }

        return $component;
    }

    public function test_render_shows_the_register_form(): void
    {
        Livewire::test(Register::class)->assertOk()->assertSet('registered', false)->assertSee('Register');
    }

    public function test_submit_creates_a_pending_tenant_and_marks_the_form_as_registered(): void
    {
        $this->filled()->call('submit')->assertHasNoErrors()->assertSet('registered', true);

        $user = User::where('email', 'dave@example.com')->firstOrFail();
        $this->assertSame(Const_Role::TENANT, $user->role);
        $this->assertSame(Const_Status::PENDING, $user->registration_status);
        $this->assertGuest();
    }

    public function test_submit_clears_the_password_fields_after_success(): void
    {
        $this->filled()->call('submit')->assertSet('password', null)->assertSet('password_confirmation', null);
    }

    public function test_submit_removes_spaces_from_the_nik_and_phone(): void
    {
        $this->filled(['nik' => '3201 0101 0101 0001', 'phone' => '0812 3456 7890'])->call('submit')->assertHasNoErrors();

        $user = User::where('email', 'dave@example.com')->firstOrFail();
        $this->assertSame('3201010101010001', $user->nik);
        $this->assertSame('081234567890', $user->phone);
    }

    public function test_rules_require_every_field(): void
    {
        Livewire::test(Register::class)
            ->call('submit')
            ->assertHasErrors(['name' => 'required', 'nik' => 'required', 'phone' => 'required', 'email' => 'required', 'password' => 'required'])
            ->assertSet('registered', false);
    }

    public function test_rules_check_the_formats(): void
    {
        $this->filled(['nik' => '12345'])->call('submit')->assertHasErrors(['nik' => 'digits']);
        $this->filled(['email' => 'not-an-email'])->call('submit')->assertHasErrors(['email' => 'email']);
        $this->filled(['password' => 'short', 'password_confirmation' => 'short'])->call('submit')->assertHasErrors(['password' => 'min']);
        $this->filled(['password_confirmation' => 'different'])->call('submit')->assertHasErrors(['password' => 'confirmed']);
        $this->filled(['phone' => str_repeat('1', 21)])->call('submit')->assertHasErrors(['phone' => 'max']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_rules_reject_a_nik_or_email_that_is_already_used(): void
    {
        User::factory()->create(['nik' => '3201010101010001', 'email' => 'dave@example.com']);

        $this->filled()->call('submit')->assertHasErrors(['nik' => 'unique', 'email' => 'unique']);
    }
}
