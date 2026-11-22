<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Garage;

use App\Livewire\Garage\Index;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GarageIndexTest extends TestCase
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

    private function page()
    {
        return Livewire::actingAs(User::factory()->admin()->create())->test(Index::class);
    }

    private function rented(string $start, string $end): Garage
    {
        $garage = Garage::factory()->car()->create();
        Rental::factory()->create(['garage_id' => $garage->id, 'start_date' => $start, 'end_date' => $end]);

        return $garage;
    }

    public function test_mount_is_for_admins_and_sorts_by_code_ascending(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Index::class)->assertForbidden();
        $this->page()->assertOk()->assertSet('sortField', 'code')->assertSet('sortAsc', true);
    }
}
