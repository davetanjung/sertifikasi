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
use App\Services\ReportService;
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

    public function test_the_default_is_the_monthly_report_of_the_current_year(): void
    {
        $this->rent('2026-11-10');

        $this->page()
            ->assertSet('report', 'monthly')
            ->assertSet('year', 2026)
            ->assertSet('month', 11)
            ->assertViewHas('reportError', null)
            ->assertViewHas('rows', fn ($rows) => count($rows) === 12
                && array_keys($rows[0]) === ['month', 'rentals', 'rent_income', 'charging_income', 'total_income']
                && $rows[10]['rentals'] === 1
                && $rows[10]['rent_income'] === 3000.0);
    }

    public function test_each_report_type_returns_its_row_shape(): void
    {
        $rental = $this->rent('2026-11-10', Const_Type::GARAGE_ELECTRIC_CAR);
        $this->charge($rental, '2026-11-11');
        Carbon::setTestNow('2026-11-10 09:00:00');

        $this->page()->set('report', 'daily')
            ->assertViewHas('rows', fn ($rows) => count($rows) === 30
                && array_keys($rows[0]) === ['day', 'date', 'rentals', 'rent_income', 'charging_income', 'total_income']
                && $rows[9]['rentals'] === 1
                && $rows[10]['charging_income'] === 20000.0);

        $this->page()->set('report', 'garage_type')
            ->assertViewHas('rows', fn ($rows) => count($rows) === 3
                && array_keys($rows[0]) === ['garage_type', 'rentals', 'rent_income', 'charging_income', 'total_income']
                && collect($rows)->firstWhere('garage_type', Const_Type::GARAGE_ELECTRIC_CAR)['total_income'] === 23000.0);

        $this->page()->set('report', 'tenant')
            ->assertViewHas('rows', fn ($rows) => count($rows) === 1
                && array_keys($rows[0]) === ['user_id', 'name', 'email', 'rentals', 'rent_income', 'charging_income', 'total_income']
                && $rows[0]['user_id'] === $rental->user_id);
    }

    public function test_the_filters_are_applied(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $this->rent('2026-11-10', Const_Type::GARAGE_CAR, $alice);
        $this->rent('2026-11-10', Const_Type::GARAGE_ELECTRIC_CAR, $bob);

        $this->page()
            ->set('garage_type', Const_Type::GARAGE_CAR)
            ->assertViewHas('rows', fn ($rows) => $rows[10]['rentals'] === 1);

        $this->page()
            ->set('user_id', $bob->id)
            ->assertViewHas('rows', fn ($rows) => $rows[10]['rentals'] === 1 && $rows[10]['rent_income'] === 3000.0);

        $this->page()
            ->set('garage_type', Const_Type::GARAGE_BICYCLE)
            ->assertViewHas('rows', fn ($rows) => $rows[10]['rentals'] === 0);

        $this->page()
            ->set('report', 'tenant')
            ->set('garage_type', Const_Type::GARAGE_CAR)
            ->assertViewHas('rows', fn ($rows) => array_column($rows, 'name') === ['Alice']);

        $this->page()
            ->set('report', 'garage_type')
            ->set('user_id', $alice->id)
            ->assertViewHas('rows', fn ($rows) => collect($rows)->sum('rentals') === 1);

        $this->page()
            ->set('report', 'daily')
            ->set('month', 12)
            ->assertViewHas('rows', fn ($rows) => count($rows) === 31 && collect($rows)->sum('rentals') === 0);
    }

    public function test_an_empty_year_means_all_time_for_the_garage_type_and_tenant_reports(): void
    {
        $this->rent('2026-11-10');
        $this->rent('2027-03-05');
        Carbon::setTestNow('2026-11-10 09:00:00');

        foreach (['garage_type', 'tenant'] as $report) {
            $this->page()
                ->set('report', $report)
                ->set('year', '')
                ->set('month', '')
                ->assertViewHas('reportError', null)
                ->assertViewHas('rows', fn ($rows) => collect($rows)->sum('rentals') === 2);

            $this->page()
                ->set('report', $report)
                ->set('year', 2027)
                ->set('month', '')
                ->assertViewHas('rows', fn ($rows) => collect($rows)->sum('rentals') === 1);
        }
    }

    public function test_a_month_without_a_year_gives_a_report_error_not_an_exception(): void
    {
        foreach (['garage_type', 'tenant'] as $report) {
            $this->page()
                ->set('report', $report)
                ->set('year', '')
                ->set('month', 3)
                ->assertOk()
                ->assertViewHas('rows', [])
                ->assertViewHas('reportError', 'Month needs a year.');
        }
    }

    public function test_monthly_and_daily_require_their_year_and_month(): void
    {
        $this->page()->set('year', '')
            ->assertViewHas('reportError', 'Year is required.')
            ->assertViewHas('rows', []);

        $this->page()->set('report', 'daily')->set('month', '')
            ->assertViewHas('reportError', 'Month is required.');

        $this->page()->set('report', 'daily')->set('year', '')
            ->assertViewHas('reportError', 'Year is required.');
    }

    public function test_invalid_values_give_a_report_error(): void
    {
        $this->page()->set('report', 'weekly')->assertViewHas('reportError', 'Report type is not valid.');
        $this->page()->set('year', 'abc')->assertViewHas('reportError', 'Year is not valid.');
        $this->page()->set('year', 1999)->assertViewHas('reportError', 'Year is not valid.');
        $this->page()->set('report', 'daily')->set('month', 13)->assertViewHas('reportError', 'Month must be between 1 and 12.');
        $this->page()->set('garage_type', 'boat')->assertViewHas('reportError', 'Garage type is not valid.');
    }

    public function test_the_totals_equal_the_sum_of_the_rows(): void
    {
        $this->rent('2026-03-02');
        $electric = $this->rent('2026-11-10', Const_Type::GARAGE_ELECTRIC_CAR);
        $this->charge($electric, '2026-11-11');
        Carbon::setTestNow('2026-11-10 09:00:00');

        $this->page()->assertViewHas('totals', fn ($totals) => $totals === [
            'rentals' => 2,
            'rent_income' => 6000.0,
            'charging_income' => 20000.0,
            'total_income' => 26000.0,
        ]);

        $this->page()->set('report', 'garage_type')->set('month', '')
            ->assertViewHas('totals', fn ($totals) => $totals['rentals'] === 2 && $totals['total_income'] === 26000.0);
    }

    public function test_the_totals_are_zero_when_there_is_an_error(): void
    {
        $this->page()->set('year', '')->assertViewHas('totals', [
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
            ->assertViewHas('yearOptions', [2026 => '2026', 2027 => '2027'])
            ->assertViewHas('monthOptions', fn ($options) => count($options) === 12
                && $options[1] === 'January'
                && $options[12] === 'December')
            ->assertViewHas('garageTypeOptions', fn ($options) => array_keys($options) === Const_Type::GARAGE_TYPES)
            ->assertViewHas('reportOptions', fn ($options) => array_keys($options) === ['monthly', 'daily', 'garage_type', 'tenant']);
    }

    public function test_the_years_come_from_the_service(): void
    {
        $this->assertSame([2026, 2027], ReportService::years());
    }
}
