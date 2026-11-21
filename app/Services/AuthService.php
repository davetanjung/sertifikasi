<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GeneralException;
use App\Models\User;
use App\Utilities\Constants\Const_Status;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class AuthService
{
    /**
     * Log a user in. Only approved users may pass (spec 3.2).
     *
     * The status is checked after the password, so the form never reveals
     * the status of an account to someone who does not know its password.
     *
     * @throws GeneralException
     */
    public static function login(string $email, string $password, bool $remember = false): User
    {
        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new GeneralException('These credentials do not match our records.');
        }

        if ($user->registration_status !== Const_Status::APPROVED) {
            $message = match ($user->registration_status) {
                Const_Status::PENDING => 'Your registration is still waiting for approval.',
                Const_Status::REJECTED => 'Your registration was rejected.',
                default => 'Your account is not allowed to log in.',
            };

            throw new GeneralException($message);
        }

        Auth::login($user, $remember);
        session()->regenerate();

        return $user;
    }

    public static function logout(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }

    /**
     * Route name or path where the user lands after login.
     */
    public static function home(User $user): string
    {
        return '/dashboard';
    }
}
