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

    /**
     * The monthly rows of a whole year.
     *
     * @return list<array<string, mixed>>
     */
    private function year(int $year, ?string $garageType = null, ?string $userId = null): array
    {
        return ReportService::forRange("{$year}-01-01", "{$year}-12-31", $garageType, $userId)['rows'];
    }

    /**
     * The daily rows of a whole month.
     *
     * @return list<array<string, mixed>>
     */
    private function month(int $year, int $month, ?string $garageType = null, ?string $userId = null): array
    {
        $start = Carbon::create($year, $month, 1);

        return ReportService::forRange($start->toDateString(), $start->copy()->endOfMonth()->toDateString(), $garageType, $userId)['rows'];
    }

    public function test_a_range_over_several_months_gives_one_row_per_month_with_zero_months(): void
    {
        $this->rent('2027-03-10');

        $report = ReportService::forRange('2027-01-01', '2027-12-31');

        $this->assertSame(ReportService::BY_MONTH, $report['granularity']);
        $this->assertCount(12, $report['rows']);
        $this->assertSame('2027-01', $report['rows'][0]['month']);
        $this->assertSame('2027-12', $report['rows'][11]['month']);
        $this->assertSame(
            ['month' => '2027-01', 'rentals' => 0, 'rent_income' => 0.0, 'charging_income' => 0.0, 'total_income' => 0.0],
            $report['rows'][0]
        );
        $this->assertSame(1, $report['rows'][2]['rentals']);
        $this->assertSame(3000.0, $report['rows'][2]['rent_income']);
    }

    public function test_a_range_inside_one_month_gives_one_row_per_day(): void
    {
        $report = ReportService::forRange('2028-02-01', '2028-02-29');

        $this->assertSame(ReportService::BY_DAY, $report['granularity']);
        $this->assertCount(29, $report['rows']);
        $this->assertSame(
            ['date' => '2028-02-01', 'rentals' => 0, 'rent_income' => 0.0, 'charging_income' => 0.0, 'total_income' => 0.0],
            $report['rows'][0]
        );
        $this->assertSame('2028-02-29', $report['rows'][28]['date']);
    }

    public function test_a_partial_range_in_one_month_only_has_the_days_asked_for(): void
    {
        $this->rent('2027-03-05');
        $this->rent('2027-03-20');

        $report = ReportService::forRange('2027-03-04', '2027-03-06');

        $this->assertSame(['2027-03-04', '2027-03-05', '2027-03-06'], array_column($report['rows'], 'date'));
        $this->assertSame(1, array_sum(array_column($report['rows'], 'rentals')));
    }

    public function test_a_single_day_gives_one_row(): void
    {
        $this->rent('2027-03-05');

        $report = ReportService::forRange('2027-03-05', '2027-03-05');

        $this->assertSame(ReportService::BY_DAY, $report['granularity']);
        $this->assertCount(1, $report['rows']);
        $this->assertSame(3000.0, $report['rows'][0]['total_income']);
    }

    public function test_two_dates_in_neighbouring_months_give_month_rows(): void
    {
        $report = ReportService::forRange('2027-03-31', '2027-04-01');

        $this->assertSame(ReportService::BY_MONTH, $report['granularity']);
        $this->assertSame(['2027-03', '2027-04'], array_column($report['rows'], 'month'));
    }

    public function test_a_range_over_several_years_gives_a_row_for_every_month(): void
    {
        $this->rent('2026-12-10');
        $this->rent('2027-01-10');

        $report = ReportService::forRange('2026-12-01', '2027-02-28');

        $this->assertSame(['2026-12', '2027-01', '2027-02'], array_column($report['rows'], 'month'));
        $this->assertSame([1, 1, 0], array_column($report['rows'], 'rentals'));
    }

    public function test_only_rentals_starting_inside_the_range_are_counted(): void
    {
        $this->rent('2026-03-10');
        $this->rent('2027-03-10');

        $this->assertSame(0, array_sum(array_column($this->year(2028), 'rentals')));
        $this->assertSame(1, array_sum(array_column($this->year(2027), 'rentals')));
        $this->assertSame(1, array_sum(array_column($this->year(2026), 'rentals')));
    }

    public function test_extension_adds_income_to_its_start_month_but_is_not_a_rental(): void
    {
        $rental = $this->rent('2027-01-30'); // 30 Jan to 1 Feb
        RentalService::extend($rental, '2027-02-05', 'proofs/ext.jpg'); // 2 to 5 Feb, 4 days

        $rows = $this->year(2027);

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

        $march = $this->year(2027)[2];

        $this->assertSame(1, $march['rentals']);
        $this->assertSame(3000.0, $march['rent_income']);
        $this->assertSame(20000.0, $march['charging_income']);
        $this->assertSame(23000.0, $march['total_income']);
    }

    public function test_charging_belongs_to_the_month_it_was_created(): void
    {
        $rental = $this->rent('2027-01-30', Const_Type::GARAGE_ELECTRIC_CAR); // until 1 Feb
        $this->charge($rental, '2027-02-01');

        $rows = $this->year(2027);

        $this->assertSame(3000.0, $rows[0]['total_income']);
        $this->assertSame(0.0, $rows[0]['charging_income']);
        $this->assertSame(0, $rows[1]['rentals']);
        $this->assertSame(20000.0, $rows[1]['charging_income']);
    }

    public function test_the_range_includes_the_end_date_itself(): void
    {
        $rental = $this->rent('2027-03-10', Const_Type::GARAGE_ELECTRIC_CAR);
        $this->charge($rental, '2027-03-12');

        $rows = ReportService::forRange('2027-03-10', '2027-03-12')['rows'];

        $this->assertSame(3000.0, $this->rowWhere($rows, 'date', '2027-03-10')['total_income']);
        $this->assertSame(20000.0, $this->rowWhere($rows, 'date', '2027-03-12')['charging_income']);
    }

    public function test_daily_groups_rentals_by_start_day_and_charging_by_created_day(): void
    {
        $this->rent('2027-03-05');
        $rental = $this->rent('2027-03-10', Const_Type::GARAGE_ELECTRIC_CAR);
        $this->charge($rental, '2027-03-12');
        $this->rent('2027-04-05');

        $rows = $this->month(2027, 3);

        $this->assertSame(3000.0, $this->rowWhere($rows, 'date', '2027-03-05')['total_income']);
        $this->assertSame(3000.0, $this->rowWhere($rows, 'date', '2027-03-10')['total_income']);
        $this->assertSame(0, $this->rowWhere($rows, 'date', '2027-03-12')['rentals']);
        $this->assertSame(20000.0, $this->rowWhere($rows, 'date', '2027-03-12')['charging_income']);
        $this->assertSame(2, array_sum(array_column($rows, 'rentals')));
    }

    public function test_garage_type_filter(): void
    {
        $this->rent('2027-03-10', Const_Type::GARAGE_BICYCLE);
        $electric = $this->rent('2027-03-10', Const_Type::GARAGE_ELECTRIC_CAR);
        $this->charge($electric, '2027-03-10');

        $monthlyElectric = $this->year(2027, Const_Type::GARAGE_ELECTRIC_CAR)[2];
        $this->assertSame(1, $monthlyElectric['rentals']);
        $this->assertSame(23000.0, $monthlyElectric['total_income']);

        $monthlyBicycle = $this->year(2027, Const_Type::GARAGE_BICYCLE)[2];
        $this->assertSame(3000.0, $monthlyBicycle['total_income']);
        $this->assertSame(0.0, $monthlyBicycle['charging_income']);

        $dailyCar = $this->month(2027, 3, Const_Type::GARAGE_CAR);
        $this->assertSame(0.0, array_sum(array_column($dailyCar, 'total_income')));

        $dailyElectric = $this->month(2027, 3, Const_Type::GARAGE_ELECTRIC_CAR);
        $this->assertSame(23000.0, $this->rowWhere($dailyElectric, 'date', '2027-03-10')['total_income']);
    }

    public function test_tenant_filter(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $aliceRental = $this->rent('2027-03-10', Const_Type::GARAGE_ELECTRIC_CAR, $alice);
        $this->charge($aliceRental, '2027-03-11');
        $this->rent('2027-03-10', Const_Type::GARAGE_CAR, $bob);

        $monthlyAlice = $this->year(2027, null, $alice->id)[2];
        $this->assertSame(1, $monthlyAlice['rentals']);
        $this->assertSame(23000.0, $monthlyAlice['total_income']);

        $monthlyBob = $this->year(2027, null, $bob->id)[2];
        $this->assertSame(3000.0, $monthlyBob['total_income']);
        $this->assertSame(0.0, $monthlyBob['charging_income']);

        $dailyBob = $this->month(2027, 3, null, $bob->id);
        $this->assertSame(3000.0, array_sum(array_column($dailyBob, 'total_income')));

        $both = $this->year(2027, Const_Type::GARAGE_CAR, $alice->id)[2];
        $this->assertSame(0, $both['rentals']);
    }

    public function test_invalid_filters_throw_a_general_exception_with_a_message(): void
    {
        $invalid = [
            'Start date is required.' => fn () => ReportService::forRange(null, '2027-03-01'),
            'End date is required.' => fn () => ReportService::forRange('2027-03-01', ''),
            'Start date is not valid.' => fn () => ReportService::forRange('15-03-2027', '2027-03-20'),
            'End date is not valid.' => fn () => ReportService::forRange('2027-03-01', '2027-13-45'),
            'Start date cannot be before 2026.' => fn () => ReportService::forRange('2025-12-31', '2027-03-20'),
            'End date cannot be before start date.' => fn () => ReportService::forRange('2027-03-20', '2027-03-19'),
            'Garage type is not valid.' => fn () => ReportService::forRange('2027-03-01', '2027-03-20', 'boat'),
        ];

        foreach ($invalid as $message => $callback) {
            try {
                $callback();
                $this->fail("Expected GeneralException: {$message}");
            } catch (GeneralException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }
        }
    }

    public function test_carbon_dates_are_accepted(): void
    {
        $report = ReportService::forRange(Carbon::parse('2027-03-01 15:00:00'), Carbon::parse('2027-03-03 08:00:00'));

        $this->assertSame(['2027-03-01', '2027-03-02', '2027-03-03'], array_column($report['rows'], 'date'));
    }
}
