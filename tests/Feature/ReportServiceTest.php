<?php

namespace Tests\Feature;

use App\Exceptions\GeneralException;
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
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Rent a new garage (daily price 1000, electric price 2500 per kWh) through the service on the given
     * day, starting that day. A daily rental of 3 days costs 3000.
     */
    private function rent(string $date, string $garageType = Const_Type::GARAGE_CAR, ?User $tenant = null, int $days = 3): Rental
    {
        Carbon::setTestNow($date.' 09:00:00');

        $garage = Garage::factory()->create([
            'type' => $garageType,
            'daily_price' => 1000,
            'price_per_kwh' => $garageType === Const_Type::GARAGE_ELECTRIC_CAR ? 2500 : null,
        ]);

        $vehicleFactory = match ($garageType) {
            Const_Type::GARAGE_BICYCLE => Vehicle::factory()->bicycle(),
            Const_Type::GARAGE_ELECTRIC_CAR => Vehicle::factory()->electricCar(),
            default => Vehicle::factory()->car(),
        };

        $vehicle = $vehicleFactory->create($tenant === null ? [] : ['user_id' => $tenant->id]);

        return RentalService::create([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => $days,
            'payment_proof_path' => 'proofs/new.jpg',
        ]);
    }

    /**
     * Charge an electric rental on the given day: max 10 kW, current 2 kW, 8 x 2500 = 20000.
     */
    private function charge(Rental $rental, string $date): void
    {
        Carbon::setTestNow($date.' 10:00:00');

        ChargingTransactionService::create($rental, 2, 'proofs/charge.jpg');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function rowWhere(array $rows, string $key, int|string $value): array
    {
        foreach ($rows as $row) {
            if ($row[$key] === $value) {
                return $row;
            }
        }

        $this->fail("No row with {$key} = {$value}.");
    }

    public function test_monthly_returns_twelve_rows_with_zero_months(): void
    {
        $this->rent('2027-03-10');

        $rows = ReportService::monthly(2027);

        $this->assertCount(12, $rows);
        $this->assertSame(range(1, 12), array_column($rows, 'month'));
        $this->assertSame(
            ['month' => 1, 'rentals' => 0, 'rent_income' => 0.0, 'charging_income' => 0.0, 'total_income' => 0.0],
            $rows[0]
        );
        $this->assertSame(1, $rows[2]['rentals']);
        $this->assertSame(3000.0, $rows[2]['rent_income']);
    }

    public function test_monthly_ignores_other_years(): void
    {
        $this->rent('2026-03-10');

        $this->assertSame(0, array_sum(array_column(ReportService::monthly(2027), 'rentals')));
    }

    public function test_extension_adds_income_to_its_start_month_but_is_not_a_rental(): void
    {
        $rental = $this->rent('2027-01-30'); // 30 Jan to 1 Feb
        RentalService::extend($rental, '2027-02-05', 'proofs/ext.jpg'); // 2 to 5 Feb, 4 days

        $rows = ReportService::monthly(2027);

        $this->assertSame(1, $rows[0]['rentals']);
        $this->assertSame(3000.0, $rows[0]['rent_income']);
        $this->assertSame(0, $rows[1]['rentals']);
        $this->assertSame(4000.0, $rows[1]['rent_income']);
        $this->assertSame(4000.0, $rows[1]['total_income']);
    }

    public function test_rent_and_charging_income_sum_into_the_total(): void
    {
        $rental = $this->rent('2027-03-10', Const_Type::GARAGE_ELECTRIC_CAR);
        $this->charge($rental, '2027-03-11');

        $march = ReportService::monthly(2027)[2];

        $this->assertSame(1, $march['rentals']);
        $this->assertSame(3000.0, $march['rent_income']);
        $this->assertSame(20000.0, $march['charging_income']);
        $this->assertSame(23000.0, $march['total_income']);
    }

    public function test_charging_belongs_to_the_month_it_was_created(): void
    {
        $rental = $this->rent('2027-01-30', Const_Type::GARAGE_ELECTRIC_CAR); // until 1 Feb
        $this->charge($rental, '2027-02-01');

        $rows = ReportService::monthly(2027);

        $this->assertSame(3000.0, $rows[0]['total_income']);
        $this->assertSame(0.0, $rows[0]['charging_income']);
        $this->assertSame(0, $rows[1]['rentals']);
        $this->assertSame(20000.0, $rows[1]['charging_income']);
    }

    public function test_daily_rows_cover_every_day_of_the_month(): void
    {
        $this->assertCount(29, ReportService::daily(2028, 2));
        $this->assertCount(28, ReportService::daily(2027, 2));
        $this->assertCount(30, ReportService::daily(2027, 4));
        $this->assertCount(31, ReportService::daily(2027, 1));

        $rows = ReportService::daily(2028, 2);
        $this->assertSame(['day' => 1, 'date' => '2028-02-01', 'rentals' => 0, 'rent_income' => 0.0, 'charging_income' => 0.0, 'total_income' => 0.0], $rows[0]);
        $this->assertSame('2028-02-29', $rows[28]['date']);
    }

    public function test_daily_groups_rentals_by_start_day_and_charging_by_created_day(): void
    {
        $this->rent('2027-03-05');
        $rental = $this->rent('2027-03-10', Const_Type::GARAGE_ELECTRIC_CAR);
        $this->charge($rental, '2027-03-12');
        $this->rent('2027-04-05');

        $rows = ReportService::daily(2027, 3);

        $this->assertSame(3000.0, $this->rowWhere($rows, 'day', 5)['total_income']);
        $this->assertSame(3000.0, $this->rowWhere($rows, 'day', 10)['total_income']);
        $this->assertSame(0, $this->rowWhere($rows, 'day', 12)['rentals']);
        $this->assertSame(20000.0, $this->rowWhere($rows, 'day', 12)['charging_income']);
        $this->assertSame(2, array_sum(array_column($rows, 'rentals')));
    }

    public function test_garage_type_filter_on_monthly_and_daily(): void
    {
        $this->rent('2027-03-10', Const_Type::GARAGE_BICYCLE);
        $electric = $this->rent('2027-03-10', Const_Type::GARAGE_ELECTRIC_CAR);
        $this->charge($electric, '2027-03-10');

        $monthlyElectric = ReportService::monthly(2027, Const_Type::GARAGE_ELECTRIC_CAR)[2];
        $this->assertSame(1, $monthlyElectric['rentals']);
        $this->assertSame(23000.0, $monthlyElectric['total_income']);

        $monthlyBicycle = ReportService::monthly(2027, Const_Type::GARAGE_BICYCLE)[2];
        $this->assertSame(3000.0, $monthlyBicycle['total_income']);
        $this->assertSame(0.0, $monthlyBicycle['charging_income']);

        $dailyCar = ReportService::daily(2027, 3, Const_Type::GARAGE_CAR);
        $this->assertSame(0.0, array_sum(array_column($dailyCar, 'total_income')));

        $dailyElectric = ReportService::daily(2027, 3, Const_Type::GARAGE_ELECTRIC_CAR);
        $this->assertSame(23000.0, $this->rowWhere($dailyElectric, 'day', 10)['total_income']);
    }

    public function test_tenant_filter_on_monthly_and_daily(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $aliceRental = $this->rent('2027-03-10', Const_Type::GARAGE_ELECTRIC_CAR, $alice);
        $this->charge($aliceRental, '2027-03-10');
        $this->rent('2027-03-10', Const_Type::GARAGE_CAR, $bob);

        $monthlyAlice = ReportService::monthly(2027, null, $alice->id)[2];
        $this->assertSame(1, $monthlyAlice['rentals']);
        $this->assertSame(23000.0, $monthlyAlice['total_income']);

        $monthlyBob = ReportService::monthly(2027, null, $bob->id)[2];
        $this->assertSame(3000.0, $monthlyBob['total_income']);
        $this->assertSame(0.0, $monthlyBob['charging_income']);

        $dailyBob = ReportService::daily(2027, 3, null, $bob->id);
        $this->assertSame(3000.0, array_sum(array_column($dailyBob, 'total_income')));
    }

    public function test_by_garage_type_has_all_three_types_even_without_data(): void
    {
        $rows = ReportService::byGarageType();

        $this->assertSame(Const_Type::GARAGE_TYPES, array_column($rows, 'garage_type'));
        $this->assertSame(0.0, array_sum(array_column($rows, 'total_income')));
    }

    public function test_by_garage_type_honors_year_month_and_tenant_filters(): void
    {
        $alice = User::factory()->create();
        $this->rent('2027-03-10', Const_Type::GARAGE_BICYCLE, $alice);
        $electric = $this->rent('2027-03-10', Const_Type::GARAGE_ELECTRIC_CAR);
        $this->charge($electric, '2027-03-11');
        $this->rent('2027-04-10', Const_Type::GARAGE_BICYCLE);
        $this->rent('2026-03-10', Const_Type::GARAGE_BICYCLE);

        $all = ReportService::byGarageType();
        $this->assertSame(3, $this->rowWhere($all, 'garage_type', Const_Type::GARAGE_BICYCLE)['rentals']);

        $march = ReportService::byGarageType(2027, 3);
        $this->assertSame(1, $this->rowWhere($march, 'garage_type', Const_Type::GARAGE_BICYCLE)['rentals']);
        $this->assertSame(23000.0, $this->rowWhere($march, 'garage_type', Const_Type::GARAGE_ELECTRIC_CAR)['total_income']);

        $year = ReportService::byGarageType(2027);
        $this->assertSame(2, $this->rowWhere($year, 'garage_type', Const_Type::GARAGE_BICYCLE)['rentals']);

        $tenant = ReportService::byGarageType(2027, 3, $alice->id);
        $this->assertSame(1, $this->rowWhere($tenant, 'garage_type', Const_Type::GARAGE_BICYCLE)['rentals']);
        $this->assertSame(0, $this->rowWhere($tenant, 'garage_type', Const_Type::GARAGE_ELECTRIC_CAR)['rentals']);
        $this->assertCount(3, $tenant);
    }

    public function test_by_tenant_lists_only_active_tenants_ordered_by_total_then_name(): void
    {
        $zed = User::factory()->create(['name' => 'Zed']);
        $amy = User::factory()->create(['name' => 'Amy']);
        $big = User::factory()->create(['name' => 'Big']);
        User::factory()->create(['name' => 'Idle']);

        $this->rent('2027-03-10', Const_Type::GARAGE_CAR, $zed);
        $this->rent('2027-03-10', Const_Type::GARAGE_CAR, $amy);
        $bigRental = $this->rent('2027-03-10', Const_Type::GARAGE_ELECTRIC_CAR, $big);
        $this->charge($bigRental, '2027-03-11');

        $rows = ReportService::byTenant();

        $this->assertSame(['Big', 'Amy', 'Zed'], array_column($rows, 'name'));
        $this->assertSame($big->id, $rows[0]['user_id']);
        $this->assertSame($big->email, $rows[0]['email']);
        $this->assertSame(23000.0, $rows[0]['total_income']);
        $this->assertSame(1, $rows[1]['rentals']);
    }

    public function test_by_tenant_honors_year_month_and_garage_type_filters(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $this->rent('2027-03-10', Const_Type::GARAGE_CAR, $alice);
        $this->rent('2027-04-10', Const_Type::GARAGE_BICYCLE, $bob);

        $this->assertCount(2, ReportService::byTenant(2027));
        $this->assertSame(['Alice'], array_column(ReportService::byTenant(2027, 3), 'name'));
        $this->assertSame(['Bob'], array_column(ReportService::byTenant(2027, null, Const_Type::GARAGE_BICYCLE), 'name'));
        $this->assertSame([], ReportService::byTenant(2026));
    }

    public function test_invalid_filters_throw_a_general_exception(): void
    {
        $invalid = [
            'garage type' => fn () => ReportService::monthly(2027, 'boat'),
            'daily garage type' => fn () => ReportService::daily(2027, 3, 'boat'),
            'month 13' => fn () => ReportService::daily(2027, 13),
            'month 0' => fn () => ReportService::byGarageType(2027, 0),
            'month without year (garage type)' => fn () => ReportService::byGarageType(null, 3),
            'month without year (tenant)' => fn () => ReportService::byTenant(null, 3),
            'year too small' => fn () => ReportService::monthly(99),
            'tenant garage type' => fn () => ReportService::byTenant(null, null, 'boat'),
        ];

        foreach ($invalid as $name => $callback) {
            try {
                $callback();
            } catch (GeneralException) {
                continue;
            }

            $this->fail("Expected GeneralException for: {$name}.");
        }

        $this->assertCount(8, $invalid);
    }
}
