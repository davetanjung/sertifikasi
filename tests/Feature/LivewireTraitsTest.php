<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\Support\TestCreateUser;
use Tests\TestCase;

class LivewireTraitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_record_through_with_create_form_and_redirects(): void
    {
        Livewire::test(TestCreateUser::class)
            ->set('name', 'Dave')
            ->set('email', 'dave@example.com')
            ->call('submitAndBackToIndex')
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertTrue(User::where('email', 'dave@example.com')->exists());
    }

    public function test_it_validates_before_submitting(): void
    {
        Livewire::test(TestCreateUser::class)
            ->set('name', '')
            ->set('email', 'not-an-email')
            ->call('submitDefault')
            ->assertHasErrors(['name', 'email']);

        $this->assertSame(0, User::count());
    }

    public function test_it_reports_a_failure_from_submit_instead_of_crashing(): void
    {
        $component = new class extends TestCreateUser
        {
            public function submit(array $validated): never
            {
                throw new RuntimeException('boom');
            }
        };

        Livewire::test($component::class)
            ->set('name', 'Dave')
            ->set('email', 'dave@example.com')
            ->call('submitDefault')
            ->assertHasErrors('flash_danger');

        $this->assertSame(0, User::count());
    }
}
