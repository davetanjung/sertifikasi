<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Traits\Livewire\WithCreateForm;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class TestCreateUser extends Component
{
    use WithCreateForm;

    public $model = User::class;

    public $menuTitle = 'User';

    public $name;

    public $email;

    protected function submitRedirectUrl(): ?string
    {
        return '/';
    }

    public function submit(array $validated): User
    {
        return User::create($validated + ['password' => Hash::make('secret-password')]);
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
        ];
    }
}
