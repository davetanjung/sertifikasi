<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Extension;

use App\Livewire\Extension\Index;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RentalService;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_rentals_lists_only_rentals_running_today(): void
    {
        $running = $this->rent();
        $this->rent(null, ['start_date' => '2026-11-20', 'days' => 2]);
        $ended = $this->rent();
        $ended->update(['start_date' => '2026-11-01', 'end_date' => '2026-11-05']);

        $this->page()->assertViewHas('rentals', fn ($rentals) => $rentals->pluck('id')->all() === [$running->id]);
    }

    public function test_rentals_lists_each_rental_once_as_its_new_row(): void
    {
        $rental = $this->rent();
        RentalService::extend($rental, '2026-11-14', 'e.jpg');
        Carbon::setTestNow('2026-11-13 09:00:00');

        $this->page()->assertViewHas('rentals', fn ($rentals) => $rentals->count() === 1 && $rentals->first()->status === Const_Status::NEW);
    }

    public function test_rentals_adds_the_latest_end_date_including_extensions(): void
    {
        $plain = $this->rent();
        $extended = $this->rent();
        RentalService::extend($extended, '2026-11-15', 'a.jpg');
        RentalService::extend($extended, '2026-11-18', 'b.jpg');

        $this->page()->assertViewHas('rentals', fn ($rentals) => $rentals->firstWhere('id', $plain->id)->latest_end_date === '2026-11-12'
            && $rentals->firstWhere('id', $extended->id)->latest_end_date === '2026-11-18');
    }

    public function test_matches_filters_by_rental_code_garage_code_tenant_and_plate_ignoring_case(): void
    {
        $first = $this->rent(Vehicle::factory()->car()->for(User::factory()->create(['name' => 'Alice Wonder']))->create(['plate_number' => 'B 1111 AA']));
        $second = $this->rent(Vehicle::factory()->car()->for(User::factory()->create(['name' => 'Bob Builder']))->create(['plate_number' => 'D 2222 BB']));

        $cases = [
            $first->code => [$first->id],
            $second->garage->code => [$second->id],
            'ALICE' => [$first->id],
            'd 2222' => [$second->id],
            'no match at all' => [],
        ];

        foreach ($cases as $keyword => $expected) {
            $this->page()->set('keyword', (string) $keyword)
                ->assertViewHas('rentals', fn ($rentals) => $rentals->pluck('id')->all() === $expected);
        }
    }

    public function test_a_blank_keyword_lists_everything_and_the_menu_title_is_set(): void
    {
        $this->rent();
        $this->rent();

        $this->page()->set('keyword', '   ')->assertSet('menuTitle', 'Extension')->assertViewHas('rentals', fn ($rentals) => $rentals->count() === 2);
    }

    public function test_the_page_runs_a_fixed_number_of_queries(): void
    {
        $this->rent();
        $this->rent();
        DB::enableQueryLog();
        $this->page();
        $few = count(DB::getQueryLog());

        foreach (range(1, 4) as $unused) {
            $this->rent();
        }

        DB::flushQueryLog();
        $this->page()->assertViewHas('rentals', fn ($rentals) => $rentals->count() === 6);

        $this->assertSame($few, count(DB::getQueryLog()));
    }
}
