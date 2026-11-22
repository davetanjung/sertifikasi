<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Garage;

use App\Livewire\Garage\Show;
use App\Models\Garage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GarageShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-11-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function page(Garage $garage)
    {
        return Livewire::actingAs(User::factory()->admin()->create())->test(Show::class, ['obj' => $garage]);
    }

    public function test_mount_is_for_admins_only(): void
    {
        $garage = Garage::factory()->car()->create();

        Livewire::actingAs(User::factory()->create())->test(Show::class, ['obj' => $garage])->assertForbidden();
        $this->page($garage)->assertOk();
    }
}
