<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureUserIsAdminTest extends TestCase
{
    private function handle(?User $user): Response
    {
        $request = Request::create('/user');
        $request->setUserResolver(fn () => $user);

        return (new EnsureUserIsAdmin)->handle($request, fn () => new Response('passed'));
    }

    public function test_an_admin_passes_to_the_next_handler(): void
    {
        $response = $this->handle(User::factory()->admin()->make());

        $this->assertSame('passed', $response->getContent());
    }

    public function test_a_tenant_gets_a_403(): void
    {
        try {
            $this->handle(User::factory()->make());
            $this->fail('A tenant should be refused.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_a_guest_gets_a_403(): void
    {
        $this->expectException(HttpException::class);

        $this->handle(null);
    }
}
