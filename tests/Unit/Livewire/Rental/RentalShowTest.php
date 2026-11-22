<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Rental;

use App\Livewire\Rental\Show;
use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RentalShowTest extends TestCase
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

    private function rental(User $tenant, array $attributes = []): Rental
    {
        return Rental::factory()->create(array_merge([
            'user_id' => $tenant->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
            'days' => 3,
            'daily_price' => 100000,
            'rent_amount' => 300000,
        ], $attributes));
    }

    private function page(Rental $rental, ?User $user = null)
    {
        return Livewire::actingAs($user ?? $rental->user)->test(Show::class, ['obj' => $rental]);
    }

    public function test_mount_allows_the_owner_and_admins_but_not_other_tenants_and_not_extension_rows(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rental($tenant);
        $extension = Rental::factory()->extension()->create(['user_id' => $tenant->id]);

        $this->page($rental)->assertOk();
        $this->page($rental, User::factory()->admin()->create())->assertOk();
        $this->page($rental, User::factory()->create())->assertForbidden();
        $this->page($extension)->assertNotFound();
    }

    public function test_mount_loads_the_garage_vehicle_and_user(): void
    {
        $rental = $this->rental(User::factory()->create());

        $this->page($rental)->assertSet('obj', fn ($obj) => $obj->relationLoaded('garage') && $obj->relationLoaded('vehicle') && $obj->relationLoaded('user'));
    }

    public function test_render_lists_the_extensions_oldest_first_and_the_charging_newest_first(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rental($tenant);
        $late = Rental::factory()->extension()->create(['user_id' => $tenant->id, 'garage_id' => $rental->garage_id, 'code' => $rental->code, 'start_date' => '2026-11-16', 'end_date' => '2026-11-17']);
        $early = Rental::factory()->extension()->create(['user_id' => $tenant->id, 'garage_id' => $rental->garage_id, 'code' => $rental->code, 'start_date' => '2026-11-13', 'end_date' => '2026-11-15']);
        Rental::factory()->extension()->create();
        $old = ChargingTransaction::factory()->create(['rental_id' => $rental->id, 'created_at' => '2026-11-09 10:00:00']);
        $new = ChargingTransaction::factory()->create(['rental_id' => $rental->id, 'created_at' => '2026-11-10 10:00:00']);

        $this->page($rental)
            ->assertViewHas('extensions', fn ($rows) => $rows->pluck('id')->all() === [$early->id, $late->id])
            ->assertViewHas('chargings', fn ($rows) => $rows->pluck('id')->all() === [$new->id, $old->id]);
    }

    public function test_render_works_out_the_latest_end_date_and_the_totals(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rental($tenant);
        Rental::factory()->extension()->create(['user_id' => $tenant->id, 'garage_id' => $rental->garage_id, 'code' => $rental->code, 'start_date' => '2026-11-13', 'end_date' => '2026-11-14', 'rent_amount' => 200000]);
        ChargingTransaction::factory()->create(['rental_id' => $rental->id, 'amount' => 80000]);

        $this->page($rental)
            ->assertViewHas('latestEndDate', fn ($date) => $date->toDateString() === '2026-11-14')
            ->assertViewHas('totalRent', 500000.0)
            ->assertViewHas('totalCharging', 80000.0)
            ->assertViewHas('grandTotal', 580000.0);
    }

    public function test_render_says_whether_the_rental_is_active_today(): void
    {
        $running = $this->rental(User::factory()->create());
        $upcoming = $this->rental(User::factory()->create(), ['start_date' => '2026-11-20', 'end_date' => '2026-11-22']);
        $ended = $this->rental(User::factory()->create(), ['start_date' => '2026-11-01', 'end_date' => '2026-11-05']);

        $this->page($running)->assertViewHas('isActive', true);
        $this->page($upcoming)->assertViewHas('isActive', false);
        $this->page($ended)->assertViewHas('isActive', false);
    }

    public function test_can_charge_needs_the_owner_a_running_electric_rental_and_a_capacity(): void
    {
        $tenant = User::factory()->create();
        $electricGarage = Garage::factory()->electricCar()->create();
        $electricCar = Vehicle::factory()->electricCar()->for($tenant)->create();
        $rental = $this->rental($tenant, ['garage_id' => $electricGarage->id, 'vehicle_id' => $electricCar->id]);

        $this->page($rental)->assertViewHas('canCharge', true);
        $this->page($rental, User::factory()->admin()->create())->assertViewHas('canCharge', false);

        $carGarage = $this->rental($tenant, ['garage_id' => Garage::factory()->car()->create()->id, 'vehicle_id' => $electricCar->id]);
        $this->page($carGarage)->assertViewHas('canCharge', false);

        $plainCar = $this->rental($tenant, ['garage_id' => $electricGarage->id, 'vehicle_id' => Vehicle::factory()->car()->for($tenant)->create()->id]);
        $this->page($plainCar)->assertViewHas('canCharge', false);

        $ended = $this->rental($tenant, ['garage_id' => $electricGarage->id, 'vehicle_id' => $electricCar->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-05']);
        $this->page($ended)->assertViewHas('canCharge', false);
    }

    public function test_can_extend_is_for_admins_on_a_rental_that_has_not_ended(): void
    {
        $tenant = User::factory()->create();
        $running = $this->rental($tenant);
        $ended = $this->rental($tenant, ['start_date' => '2026-11-01', 'end_date' => '2026-11-05']);
        $admin = User::factory()->admin()->create();

        $this->page($running, $admin)->assertViewHas('canExtend', true);
        $this->page($running)->assertViewHas('canExtend', false);
        $this->page($ended, $admin)->assertViewHas('canExtend', false);
    }
}
