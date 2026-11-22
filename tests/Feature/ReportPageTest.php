<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Report\Index;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ChargingTransactionService;
use App\Services\RentalService;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportPageTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function page()
    {
        return Livewire::actingAs($this->admin())->test(Index::class);
    }

    /**
     * The page set to every month of 2026.
     */
    private function yearPage()
    {
        return $this->page()->set('start_date', '2026-01-01')->set('end_date', '2026-12-31');
    }

    /**
     * Rent a new garage (daily price 1000) on the given day for 3 days, so it costs 3000.
     */
    private function rent(string $date, string $garageType = Const_Type::GARAGE_CAR, ?User $tenant = null): Rental
    {
        Carbon::setTestNow($date.' 09:00:00');

        $garage = Garage::factory()->create([
            'type' => $garageType,
            'daily_price' => 1000,
            'price_per_kwh' => $garageType === Const_Type::GARAGE_ELECTRIC_CAR ? 2500 : null,
        ]);

        $vehicleFactory = $garageType === Const_Type::GARAGE_ELECTRIC_CAR
            ? Vehicle::factory()->electricCar()
            : Vehicle::factory()->car();

        $vehicle = $vehicleFactory->create($tenant === null ? [] : ['user_id' => $tenant->id]);

        return RentalService::create([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 3,
            'payment_proof_path' => 'proofs/new.jpg',
        ]);
    }

    /**
     * Charge an electric rental: max 10 kW, current 2 kW, 8 x 2500 = 20000.
     */
    private function charge(Rental $rental, string $date): void
    {
        Carbon::setTestNow($date.' 10:00:00');

        ChargingTransactionService::create($rental, 2, 'proofs/charge.jpg');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('report.index'))->assertRedirect(route('login'));
    }

    public function test_a_tenant_gets_403(): void
    {
        $tenant = User::factory()->create();

        $this->actingAs($tenant)->get(route('report.index'))->assertForbidden();
        Livewire::actingAs($tenant)->test(Index::class)->assertForbidden();
    }

    public function test_an_admin_can_open_the_page(): void
    {
        $this->actingAs($this->admin())->get(route('report.index'))->assertOk()->assertSeeLivewire(Index::class);
    }

    public function test_the_default_is_today_with_one_day_row(): void
    {
        $this->rent('2026-11-10');
        $this->rent('2026-11-09');
        Carbon::setTestNow('2026-11-10 09:00:00');

        $this->page()
            ->assertSet('start_date', '2026-11-10')
            ->assertSet('end_date', '2026-11-10')
            ->assertViewHas('isDaily', true)
            ->assertViewHas('reportError', null)
            ->assertViewHas('rows', fn ($rows) => count($rows) === 1
                && array_keys($rows[0]) === ['date', 'rentals', 'rent_income', 'charging_income', 'total_income']
                && $rows[0]['date'] === '2026-11-10'
                && $rows[0]['rentals'] === 1
                && $rows[0]['rent_income'] === 3000.0);
    }

    public function test_a_range_over_several_months_shows_one_row_per_month(): void
    {
        $this->rent('2026-11-10');
        Carbon::setTestNow('2026-11-10 09:00:00');

        $this->page()->set('start_date', '2026-01-01')->set('end_date', '2026-12-31')
            ->assertViewHas('isDaily', false)
            ->assertViewHas('rows', fn ($rows) => count($rows) === 12
                && array_keys($rows[0]) === ['month', 'rentals', 'rent_income', 'charging_income', 'total_income']
                && $rows[10]['month'] === '2026-11'
                && $rows[10]['rentals'] === 1);
    }

    public function test_a_range_inside_one_month_shows_one_row_per_day(): void
    {
        $rental = $this->rent('2026-11-10', Const_Type::GARAGE_ELECTRIC_CAR);
        $this->charge($rental, '2026-11-11');
        Carbon::setTestNow('2026-11-10 09:00:00');

        $this->page()->set('start_date', '2026-11-01')->set('end_date', '2026-11-30')
            ->assertViewHas('isDaily', true)
            ->assertViewHas('rows', fn ($rows) => count($rows) === 30
                && array_keys($rows[0]) === ['date', 'rentals', 'rent_income', 'charging_income', 'total_income']
                && $rows[9]['rentals'] === 1
                && $rows[10]['charging_income'] === 20000.0);

        $this->page()->set('start_date', '2026-11-10')->set('end_date', '2026-11-12')
            ->assertViewHas('rows', fn ($rows) => array_column($rows, 'date') === ['2026-11-10', '2026-11-11', '2026-11-12']);
    }

    public function test_the_date_range_changes_the_period(): void
    {
        $this->rent('2026-11-10');
        $this->rent('2027-03-05');
        Carbon::setTestNow('2026-11-10 09:00:00');

        $this->yearPage()->assertViewHas('totals', fn ($totals) => $totals['rentals'] === 1);
        $this->page()->set('start_date', '2027-01-01')->set('end_date', '2027-12-31')
            ->assertViewHas('rows', fn ($rows) => $rows[2]['rentals'] === 1);
        $this->page()->set('start_date', '2026-01-01')->set('end_date', '2027-12-31')
            ->assertViewHas('rows', fn ($rows) => count($rows) === 24)
            ->assertViewHas('totals', fn ($totals) => $totals['rentals'] === 2);
    }

    public function test_the_garage_type_and_tenant_filters_narrow_the_rows(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $this->rent('2026-11-10', Const_Type::GARAGE_CAR, $alice);
        $this->rent('2026-11-10', Const_Type::GARAGE_ELECTRIC_CAR, $bob);

        $this->yearPage()
            ->assertViewHas('rows', fn ($rows) => $rows[10]['rentals'] === 2);

        $this->yearPage()
            ->set('garage_type', Const_Type::GARAGE_CAR)
            ->assertViewHas('rows', fn ($rows) => $rows[10]['rentals'] === 1);

        $this->yearPage()
            ->set('user_id', $bob->id)
            ->assertViewHas('rows', fn ($rows) => $rows[10]['rentals'] === 1 && $rows[10]['rent_income'] === 3000.0);

        $this->yearPage()
            ->set('garage_type', Const_Type::GARAGE_CAR)
            ->set('user_id', $bob->id)
            ->assertViewHas('rows', fn ($rows) => $rows[10]['rentals'] === 0);

        $this->yearPage()
            ->set('garage_type', Const_Type::GARAGE_BICYCLE)
            ->assertViewHas('rows', fn ($rows) => $rows[10]['rentals'] === 0);

        $this->page()
            ->set('start_date', '2026-11-01')
            ->set('end_date', '2026-11-30')
            ->set('user_id', $alice->id)
            ->assertViewHas('rows', fn ($rows) => collect($rows)->sum('rentals') === 1);
    }

    public function test_the_page_shows_the_filters_and_no_report_tabs(): void
    {
        $this->actingAs($this->admin())->get(route('report.index'))
            ->assertOk()
            ->assertSee('Start date')
            ->assertSee('End date')
            ->assertSee('Garage type')
            ->assertSee('Tenant')
            ->assertDontSee('Per garage type')
            ->assertDontSee('Per tenant')
            ->assertDontSee('aria-label="Report type"', false);
    }

    public function test_the_first_column_and_labels_follow_the_range(): void
    {
        $this->rent('2026-11-10');
        Carbon::setTestNow('2026-11-10 09:00:00');

        $this->yearPage()->assertSeeHtml('>Month</th>')->assertSee('November 2026')
            ->set('start_date', '2026-11-01')->set('end_date', '2026-11-30')
            ->assertSeeHtml('>Day</th>')->assertSee('2026-11-10');
    }

    public function test_a_missing_date_gives_a_report_error_not_an_exception(): void
    {
        $this->page()->set('start_date', '')
            ->assertOk()
            ->assertViewHas('reportError', 'Start date is required.')
            ->assertViewHas('rows', []);

        $this->page()->set('end_date', null)
            ->assertViewHas('reportError', 'End date is required.');
    }

    public function test_invalid_values_give_a_report_error(): void
    {
        $this->page()->set('start_date', 'abc')->assertViewHas('reportError', 'Start date is not valid.');
        $this->page()->set('start_date', '2025-12-31')->assertViewHas('reportError', 'Start date cannot be before 2026.');
        $this->page()->set('end_date', '2025-12-31')->assertViewHas('reportError', 'End date cannot be before start date.');
        $this->page()->set('garage_type', 'boat')->assertViewHas('reportError', 'Garage type is not valid.');
    }

    public function test_the_totals_equal_the_sum_of_the_rows(): void
    {
        $this->rent('2026-03-02');
        $electric = $this->rent('2026-11-10', Const_Type::GARAGE_ELECTRIC_CAR);
        $this->charge($electric, '2026-11-11');
        Carbon::setTestNow('2026-11-10 09:00:00');

        $this->yearPage()->assertViewHas('totals', fn ($totals) => $totals === [
            'rentals' => 2,
            'rent_income' => 6000.0,
            'charging_income' => 20000.0,
            'total_income' => 26000.0,
        ]);

        $this->page()->set('start_date', '2026-11-01')->set('end_date', '2026-11-30')
            ->assertViewHas('totals', fn ($totals) => $totals['rentals'] === 1 && $totals['total_income'] === 23000.0);
    }

    public function test_the_totals_are_zero_when_there_is_an_error(): void
    {
        $this->page()->set('start_date', '')->assertViewHas('totals', [
            'rentals' => 0,
            'rent_income' => 0.0,
            'charging_income' => 0.0,
            'total_income' => 0.0,
        ]);
    }

    public function test_the_options(): void
    {
        $approved = User::factory()->create(['name' => 'Zed']);
        $first = User::factory()->create(['name' => 'Amy']);
        User::factory()->pending()->create(['name' => 'Pending']);
        User::factory()->rejected()->create(['name' => 'Rejected']);

        $this->page()
            ->assertViewHas('tenantOptions', fn ($options) => array_values($options) === ['Amy', 'Zed']
                && array_keys($options) === [$first->id, $approved->id])
            ->assertViewHas('garageTypeOptions', fn ($options) => array_keys($options) === Const_Type::GARAGE_TYPES);
    }

    public function test_reset_filters_goes_back_to_the_default_filters(): void
    {
        $tenant = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('start_date', '2027-03-01')
            ->set('end_date', '2027-03-31')
            ->set('garage_type', Const_Type::GARAGE_CAR)
            ->set('user_id', $tenant->id)
            ->call('resetFilters')
            ->assertSet('start_date', '2026-11-10')
            ->assertSet('end_date', '2026-11-10')
            ->assertSet('garage_type', null)
            ->assertSet('user_id', null);
    }
}
