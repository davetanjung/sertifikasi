<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Rental;

use App\Livewire\Rental\Index;
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

class RentalIndexTest extends TestCase
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

    private function page(User $user)
    {
        return Livewire::actingAs($user)->test(Index::class);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rental(string $start, string $end, array $attributes = []): Rental
    {
        return Rental::factory()->create(array_merge(['start_date' => $start, 'end_date' => $end], $attributes));
    }

    public function test_mount_sets_the_default_sort(): void
    {
        $this->page($this->admin())->assertSet('sortField', 'created_at');
    }

    public function test_render_gives_the_period_and_rental_type_options(): void
    {
        $this->page($this->admin())
            ->assertViewHas('periodOptions', ['upcoming' => 'Upcoming', 'running' => 'Running', 'ended' => 'Ended'])
            ->assertViewHas('rentalTypeOptions', [Const_Type::RENTAL_DAILY => 'Daily', Const_Type::RENTAL_MONTHLY => 'Monthly']);
    }

    public function test_a_tenant_sees_only_their_own_rentals_and_no_extension_rows(): void
    {
        $tenant = User::factory()->create();
        $mine = $this->rental('2026-11-10', '2026-11-12', ['user_id' => $tenant->id]);
        Rental::factory()->extension()->create(['user_id' => $tenant->id]);
        $this->rental('2026-11-10', '2026-11-12');

        $this->page($tenant)->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$mine->id]);
    }

    public function test_an_admin_sees_every_tenants_rental_with_relations_loaded(): void
    {
        $this->rental('2026-11-10', '2026-11-12');
        $this->rental('2026-11-10', '2026-11-12');

        $this->page($this->admin())->assertViewHas('data', fn ($data) => $data->count() === 2
            && $data->first()->relationLoaded('garage')
            && $data->first()->relationLoaded('vehicle')
            && $data->first()->relationLoaded('user'));
    }

    public function test_render_works_out_the_period_status_of_each_rental(): void
    {
        $upcoming = $this->rental('2026-11-11', '2026-11-12');
        $running = $this->rental('2026-11-10', '2026-11-12');
        $ended = $this->rental('2026-11-01', '2026-11-09');

        $this->page($this->admin())->assertViewHas('data', fn ($data) => $data->firstWhere('id', $upcoming->id)->period_status === 'upcoming'
            && $data->firstWhere('id', $running->id)->period_status === 'running'
            && $data->firstWhere('id', $ended->id)->period_status === 'ended');
    }

    public function test_the_latest_end_date_includes_extensions_and_keeps_a_rental_running(): void
    {
        $vehicle = Vehicle::factory()->car()->create();
        $garage = Garage::factory()->car()->create();
        $rental = RentalService::create([
            'user_id' => $vehicle->user_id, 'vehicle_id' => $vehicle->id, 'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY, 'days' => 2, 'payment_proof_path' => 'p.jpg',
        ]); // 10 to 11 Nov
        RentalService::extend($rental, '2026-11-20', 'e.jpg');
        Carbon::setTestNow('2026-11-15 09:00:00');

        $this->page($this->admin())->assertViewHas('data', fn ($data) => $data->first()->latest_end_date === '2026-11-20'
            && $data->first()->period_status === 'running');
    }

    public function test_get_query_filters_by_rental_code_garage_code_and_plate(): void
    {
        $garage = Garage::factory()->car()->create(['code' => 'C042']);
        $vehicle = Vehicle::factory()->car()->create(['plate_number' => 'B 7777 ZZ']);
        $byCode = $this->rental('2026-11-10', '2026-11-12', ['code' => 'RNT-20261110-0007']);
        $byGarage = $this->rental('2026-11-10', '2026-11-12', ['garage_id' => $garage->id]);
        $byPlate = $this->rental('2026-11-10', '2026-11-12', ['vehicle_id' => $vehicle->id]);
        $this->rental('2026-11-10', '2026-11-12');

        foreach (['0007' => $byCode, 'c042' => $byGarage, '7777' => $byPlate] as $keyword => $expected) {
            $this->page($this->admin())->set('keyword', (string) $keyword)
                ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$expected->id]);
        }
    }

    public function test_get_query_filters_by_rental_type(): void
    {
        $monthly = Rental::factory()->monthly()->create();
        $this->rental('2026-11-10', '2026-11-12');

        $this->page($this->admin())->set('rental_type', Const_Type::RENTAL_MONTHLY)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$monthly->id]);
    }

    public function test_get_query_filters_by_period(): void
    {
        $upcoming = $this->rental('2026-11-11', '2026-11-12');
        $running = $this->rental('2026-11-10', '2026-11-12');
        $endsToday = $this->rental('2026-11-08', '2026-11-10');
        $ended = $this->rental('2026-11-01', '2026-11-09');

        $ids = fn (string $period): array => $this->page($this->admin())->set('period', $period)->viewData('data')->pluck('id')->sort()->values()->all();

        $this->assertSame([$upcoming->id], $ids('upcoming'));
        $this->assertSame(collect([$running->id, $endsToday->id])->sort()->values()->all(), $ids('running'));
        $this->assertSame([$ended->id], $ids('ended'));
    }

    public function test_sort_changes_the_order(): void
    {
        $big = $this->rental('2026-11-10', '2026-11-12', ['rent_amount' => 900000]);
        $small = $this->rental('2026-11-10', '2026-11-12', ['rent_amount' => 1000]);

        $this->page($this->admin())->call('sort', 'rent_amount')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$small->id, $big->id]);
    }
}
