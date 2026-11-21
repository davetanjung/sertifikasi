<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuthService;
use App\Utilities\Constants\Const_Status;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsApproved
{
    /**
     * Log out a user whose registration is no longer approved
     * (for example, approval was revoked while the session was active).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->registration_status !== Const_Status::APPROVED) {
            AuthService::logout();

            return redirect()->route('login')
                ->withErrors(['flash_danger' => 'Your account is not approved.']);
        }

        return $next($request);
    }
}
