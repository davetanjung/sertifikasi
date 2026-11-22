<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_password_is_hashed_when_it_is_set(): void
    {
        $user = new User(['password' => '12345678']);

        $this->assertNotSame('12345678', $user->password);
        $this->assertTrue(password_verify('12345678', $user->password));
    }
}
