<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Utilities\Constants\Const_Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsAdmin
{
    /**
     * Only admins may pass, everyone else gets a 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role === Const_Role::ADMIN, 403);

        return $next($request);
    }
}
