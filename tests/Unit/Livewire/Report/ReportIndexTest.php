<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Report;

use App\Livewire\Report\Index;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportIndexTest extends TestCase
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

    private function rental(string $start, float $amount, ?Garage $garage = null, ?User $user = null): Rental
    {
        return Rental::factory()->create([
            'user_id' => ($user ?? User::factory()->create())->id,
            'garage_id' => ($garage ?? Garage::factory()->car()->create())->id,
            'start_date' => $start,
            'end_date' => $start,
            'days' => 1,
            'rent_amount' => $amount,
        ]);
    }

    public function test_mount_is_for_admins_and_starts_on_today(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Index::class)->assertForbidden();

        $this->page()->assertOk()->assertSet('start_date', '2026-11-10')->assertSet('end_date', '2026-11-10')
            ->assertSet('garage_type', null)->assertSet('user_id', null);
    }
}
