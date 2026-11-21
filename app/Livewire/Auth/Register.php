<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Services\UserService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

final class Register extends Component
{
    public $name;

    public $nik;

    public $phone;

    public $email;

    public $password;

    public $password_confirmation;

    public bool $registered = false;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['required', 'digits:16', 'unique:users,nik'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function submit()
    {
        $this->nik = preg_replace('/\s+/', '', (string) $this->nik);
        $this->phone = preg_replace('/\s+/', '', (string) $this->phone);

        $this->validate();

        try {
            UserService::register([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'nik' => $this->nik,
                'phone' => $this->phone,
            ]);
        } catch (Throwable $exception) {
            $this->addError('flash_danger', _get_exception_message($exception, false));

            return false;
        }

        $this->registered = true;
        $this->reset(['password', 'password_confirmation']);

        return true;
    }

    public function render(): View
    {
        return view('auth.register')->layout('components.layouts.auth', ['title' => 'Register']);
    }
}
