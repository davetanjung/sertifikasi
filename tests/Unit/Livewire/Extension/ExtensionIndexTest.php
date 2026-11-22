<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Extension;

use App\Livewire\Extension\Index;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RentalService;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExtensionIndexTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function rent(?Vehicle $vehicle = null, array $overrides = []): Rental
    {
        $vehicle ??= Vehicle::factory()->car()->create();

        return RentalService::create(array_merge([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => Garage::factory()->car()->create(['daily_price' => 100000])->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 3,
            'payment_proof_path' => 'p.jpg',
        ], $overrides));
    }

    public function test_mount_is_for_admins_only(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Index::class)->assertForbidden();
        $this->page()->assertOk();
    }
}
