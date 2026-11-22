<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Garage;

use App\Livewire\Garage\Show;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Utilities\Constants\Const_Status;
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

    public function test_render_says_available_when_no_rental_covers_today(): void
    {
        $garage = Garage::factory()->car()->create();
        Rental::factory()->create(['garage_id' => $garage->id, 'start_date' => '2026-11-11', 'end_date' => '2026-11-12']);

        $this->page($garage)->assertViewHas('status', Const_Status::AVAILABLE)->assertViewHas('usedToday', false);
    }

    public function test_render_says_used_when_a_rental_covers_today(): void
    {
        $garage = Garage::factory()->car()->create();
        Rental::factory()->create(['garage_id' => $garage->id, 'start_date' => '2026-11-10', 'end_date' => '2026-11-12']);

        $this->page($garage)->assertViewHas('status', Const_Status::USED)->assertViewHas('usedToday', true);
    }

    public function test_render_gives_the_ten_latest_rentals_with_user_and_vehicle(): void
    {
        $garage = Garage::factory()->car()->create();
        Rental::factory()->count(12)->create(['garage_id' => $garage->id]);
        Rental::factory()->create();

        $this->page($garage)->assertViewHas('rentals', fn ($rentals) => $rentals->count() === 10
            && $rentals->first()->relationLoaded('user')
            && $rentals->first()->relationLoaded('vehicle'));
    }
}
