<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Services\AuthService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

final class Login extends Component
{
    public $email;

    public $password;

    public bool $remember = false;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function process()
    {
        $this->validate();

        try {
            $user = AuthService::login((string) $this->email, (string) $this->password, $this->remember);
        } catch (Throwable $exception) {
            $this->addError('flash_danger', _get_exception_message($exception, false));

            return false;
        }

        return redirect()->intended(AuthService::home($user));
    }

    public function render(): View
    {
        return view('auth.login')->layout('components.layouts.auth', ['title' => 'Login']);
    }
}
