<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestCreateUser;

uses(RefreshDatabase::class);

it('creates a record through WithCreateForm and redirects', function () {
    Livewire::test(TestCreateUser::class)
        ->set('name', 'Dave')
        ->set('email', 'dave@example.com')
        ->call('submitAndBackToIndex')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect(User::where('email', 'dave@example.com')->exists())->toBeTrue();
});

it('validates before submitting', function () {
    Livewire::test(TestCreateUser::class)
        ->set('name', '')
        ->set('email', 'not-an-email')
        ->call('submitDefault')
        ->assertHasErrors(['name', 'email']);

    expect(User::count())->toBe(0);
});

it('reports a failure from submit instead of crashing', function () {
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

    expect(User::count())->toBe(0);
});
