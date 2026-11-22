<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureUserIsApproved;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EnsureUserIsApprovedTest extends TestCase
{
    use RefreshDatabase;

    private function handle(?User $user): Response
    {
        $request = Request::create('/dashboard');
        $request->setUserResolver(fn () => $user);

        return (new EnsureUserIsApproved)->handle($request, fn () => new Response('passed'));
    }

    public function test_an_approved_user_passes_to_the_next_handler(): void
    {
        $this->assertSame('passed', $this->handle(User::factory()->create())->getContent());
    }

    public function test_a_guest_passes_through_because_the_auth_middleware_handles_guests(): void
    {
        $this->assertSame('passed', $this->handle(null)->getContent());
    }

    public function test_a_user_who_is_no_longer_approved_is_logged_out_and_sent_to_login(): void
    {
        foreach ([User::factory()->pending()->create(), User::factory()->rejected()->create()] as $user) {
            $this->actingAs($user);

            $response = $this->handle($user);

            $this->assertInstanceOf(RedirectResponse::class, $response);
            $this->assertSame(route('login'), $response->getTargetUrl());
            $this->assertSame('Your account is not approved.', $response->getSession()->get('errors')->first('flash_danger'));
            $this->assertGuest();
        }
    }
}
