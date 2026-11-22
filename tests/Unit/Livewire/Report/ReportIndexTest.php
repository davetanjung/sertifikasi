<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Report;

use App\Livewire\Report\Index;
use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Utilities\Constants\Const_Type;
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

    public function test_reset_filters_goes_back_to_today_and_clears_the_other_filters(): void
    {
        $this->page()
            ->set('start_date', '2027-01-01')->set('end_date', '2027-12-31')
            ->set('garage_type', Const_Type::GARAGE_CAR)->set('user_id', User::factory()->create()->id)
            ->call('resetFilters')
            ->assertSet('start_date', '2026-11-10')->assertSet('end_date', '2026-11-10')
            ->assertSet('garage_type', null)->assertSet('user_id', null);
    }

    public function test_report_gives_day_rows_for_a_range_inside_one_month(): void
    {
        $this->rental('2026-11-10', 3000);

        $this->page()->assertViewHas('isDaily', true)->assertViewHas('reportError', null)
            ->assertViewHas('rows', fn ($rows) => count($rows) === 1 && $rows[0]['date'] === '2026-11-10' && $rows[0]['rent_income'] === 3000.0);
    }

    public function test_report_gives_month_rows_for_a_longer_range(): void
    {
        $this->rental('2026-11-10', 3000);

        $this->page()->set('start_date', '2026-01-01')->set('end_date', '2026-12-31')
            ->assertViewHas('isDaily', false)
            ->assertViewHas('rows', fn ($rows) => count($rows) === 12 && $rows[10]['month'] === '2026-11' && $rows[10]['rentals'] === 1);
    }

    public function test_report_applies_the_garage_type_and_tenant_filters(): void
    {
        $alice = User::factory()->create();
        $this->rental('2026-11-10', 3000, Garage::factory()->car()->create(), $alice);
        $this->rental('2026-11-10', 5000, Garage::factory()->bicycle()->create());

        $this->page()->assertViewHas('totals', fn ($totals) => $totals['rent_income'] === 8000.0);
        $this->page()->set('garage_type', Const_Type::GARAGE_CAR)->assertViewHas('totals', fn ($totals) => $totals['rent_income'] === 3000.0);
        $this->page()->set('user_id', $alice->id)->assertViewHas('totals', fn ($totals) => $totals['rent_income'] === 3000.0);
        $this->page()->set('garage_type', Const_Type::GARAGE_BICYCLE)->set('user_id', $alice->id)->assertViewHas('totals', fn ($totals) => $totals['rent_income'] === 0.0);
    }

    public function test_optional_string_treats_an_empty_filter_as_no_filter(): void
    {
        $this->rental('2026-11-10', 3000);

        $this->page()->set('garage_type', '')->set('user_id', '')->assertViewHas('reportError', null)->assertViewHas('totals', fn ($totals) => $totals['rentals'] === 1);
    }

    public function test_report_errors_are_shown_instead_of_thrown(): void
    {
        $this->page()->set('start_date', '')->assertOk()->assertViewHas('reportError', 'Start date is required.')->assertViewHas('rows', []);
        $this->page()->set('end_date', '2026-11-09')->assertViewHas('reportError', 'End date cannot be before start date.');
        $this->page()->set('garage_type', 'boat')->assertViewHas('reportError', 'Garage type is not valid.');
    }

    public function test_totals_sum_the_rows_and_round_the_money(): void
    {
        $electric = Garage::factory()->electricCar()->create();
        $rental = $this->rental('2026-11-10', 3000.555, $electric);
        $this->rental('2026-11-10', 1000);
        ChargingTransaction::factory()->create(['rental_id' => $rental->id, 'amount' => 20000, 'created_at' => '2026-11-10 10:00:00']);

        $this->page()->assertViewHas('totals', [
            'rentals' => 2,
            'rent_income' => 4000.56,
            'charging_income' => 20000.0,
            'total_income' => 24000.56,
        ]);
    }

    public function test_totals_are_zero_when_the_report_has_an_error(): void
    {
        $this->page()->set('start_date', 'abc')->assertViewHas('totals', ['rentals' => 0, 'rent_income' => 0.0, 'charging_income' => 0.0, 'total_income' => 0.0]);
    }

    public function test_render_gives_the_garage_type_and_tenant_options(): void
    {
        $zed = User::factory()->create(['name' => 'Zed']);
        $amy = User::factory()->create(['name' => 'Amy']);
        User::factory()->pending()->create();
        User::factory()->rejected()->create();

        $this->page()
            ->assertViewHas('garageTypeOptions', fn ($options) => array_keys($options) === Const_Type::GARAGE_TYPES)
            ->assertViewHas('tenantOptions', fn ($options) => array_keys($options) === [$amy->id, $zed->id] && array_values($options) === ['Amy', 'Zed']);
    }
}
