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

    public function test_submit_creates_a_pending_tenant_and_marks_the_form_as_registered(): void
    {
        $this->filled()->call('submit')->assertHasNoErrors()->assertSet('registered', true);

        $user = User::where('email', 'dave@example.com')->firstOrFail();
        $this->assertSame(Const_Role::TENANT, $user->role);
        $this->assertSame(Const_Status::PENDING, $user->registration_status);
        $this->assertGuest();
    }
}
