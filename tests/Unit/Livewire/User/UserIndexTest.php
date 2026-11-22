<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\User;

use App\Livewire\User\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function page()
    {
        return Livewire::actingAs(User::factory()->admin()->create())->test(Index::class);
    }

    public function test_mount_is_for_admins_only(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Index::class)->assertForbidden();
        $this->page()->assertOk()->assertSet('sortField', 'created_at');
    }
}
