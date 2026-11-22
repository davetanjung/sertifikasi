<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GeneralException;
use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Services\ReportService;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * A rental row with a fixed start date and amount.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function rental(string $startDate, float $amount, ?Garage $garage = null, ?User $user = null, array $overrides = []): Rental
    {
        return Rental::factory()->create(array_merge([
            'user_id' => ($user ?? User::factory()->create())->id,
            'garage_id' => ($garage ?? Garage::factory()->car()->create())->id,
            'start_date' => $startDate,
            'end_date' => $startDate,
            'days' => 1,
            'rent_amount' => $amount,
        ], $overrides));
    }

    private function charging(Rental $rental, string $createdAt, float $amount): ChargingTransaction
    {
        return ChargingTransaction::factory()->create([
            'rental_id' => $rental->id,
            'amount' => $amount,
            'created_at' => $createdAt,
        ]);
    }

    private function assertReportFails(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected GeneralException was not thrown.');
        } catch (GeneralException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    // ---- forRange: shape --------------------------------------------------

    public function test_a_range_over_several_months_returns_one_row_per_month_even_without_data(): void
    {
        $report = ReportService::forRange('2026-01-01', '2026-12-31');

        $this->assertSame(ReportService::BY_MONTH, $report['granularity']);
        $this->assertCount(12, $report['rows']);
        $this->assertSame('2026-01', $report['rows'][0]['month']);
        $this->assertSame('2026-12', $report['rows'][11]['month']);
        $this->assertSame(
            ['month' => '2026-03', 'rentals' => 0, 'rent_income' => 0.0, 'charging_income' => 0.0, 'total_income' => 0.0],
            $report['rows'][2],
        );
    }

    public function test_a_range_inside_one_month_returns_one_row_per_day(): void
    {
        $report = ReportService::forRange('2026-11-01', '2026-11-30');

        $this->assertSame(ReportService::BY_DAY, $report['granularity']);
        $this->assertCount(30, $report['rows']);
        $this->assertSame('2026-11-01', $report['rows'][0]['date']);
        $this->assertSame('2026-11-30', $report['rows'][29]['date']);
        $this->assertCount(28, ReportService::forRange('2027-02-01', '2027-02-28')['rows']);
        $this->assertCount(29, ReportService::forRange('2028-02-01', '2028-02-29')['rows']);
    }

    // ---- forRange: numbers ------------------------------------------------

    public function test_rentals_are_grouped_by_start_month_and_charging_by_created_month(): void
    {
        $november = $this->rental('2026-11-05', 100000);
        $this->rental('2026-11-20', 50000);
        $this->rental('2026-03-02', 70000);
        $this->charging($november, '2026-11-06 10:00:00', 20000);
        $this->charging($november, '2026-12-01 10:00:00', 5000);

        $rows = ReportService::forRange('2026-01-01', '2026-12-31')['rows'];

        $this->assertSame(
            ['month' => '2026-11', 'rentals' => 2, 'rent_income' => 150000.0, 'charging_income' => 20000.0, 'total_income' => 170000.0],
            $rows[10],
        );
        $this->assertSame(1, $rows[2]['rentals']);
        $this->assertSame(5000.0, $rows[11]['charging_income']);
    }

    public function test_rentals_are_grouped_by_start_day_and_charging_by_created_day(): void
    {
        $rental = $this->rental('2026-11-05', 100000);
        $this->rental('2026-11-05', 20000);
        $this->charging($rental, '2026-11-07 08:00:00', 15000);
        $this->rental('2026-10-05', 99999);

        $rows = ReportService::forRange('2026-11-01', '2026-11-30')['rows'];

        $this->assertSame(2, $rows[4]['rentals']);
        $this->assertSame(120000.0, $rows[4]['rent_income']);
        $this->assertSame(15000.0, $rows[6]['charging_income']);
        $this->assertSame(15000.0, $rows[6]['total_income']);
        $this->assertSame(0, $rows[0]['rentals']);
    }

    public function test_only_new_rows_count_as_rentals_but_extensions_add_income(): void
    {
        $this->rental('2026-11-05', 100000);
        $this->rental('2026-11-08', 30000, null, null, ['status' => Const_Status::EXTENSION]);

        $row = ReportService::forRange('2026-01-01', '2026-12-31')['rows'][10];

        $this->assertSame(1, $row['rentals']);
        $this->assertSame(130000.0, $row['rent_income']);
    }

    public function test_a_range_outside_every_rental_is_all_zero(): void
    {
        $this->rental('2027-11-05', 100000);

        $this->assertSame(0, array_sum(array_column(ReportService::forRange('2026-01-01', '2026-12-31')['rows'], 'rentals')));
        $this->assertSame(1, ReportService::forRange('2027-01-01', '2027-12-31')['rows'][10]['rentals']);
    }

    // ---- forRange: filters ------------------------------------------------

    public function test_it_filters_by_garage_type_and_tenant(): void
    {
        $user = User::factory()->create();
        $this->rental('2026-11-05', 100000, Garage::factory()->car()->create(), $user);
        $this->rental('2026-11-05', 40000, Garage::factory()->bicycle()->create(), $user);
        $this->rental('2026-11-05', 9000, Garage::factory()->car()->create());
        $income = fn (?string $type, ?string $userId): float => ReportService::forRange('2026-11-01', '2026-11-30', $type, $userId)['rows'][4]['rent_income'];

        $this->assertSame(109000.0, $income(Const_Type::GARAGE_CAR, null));
        $this->assertSame(140000.0, $income(null, $user->id));
        $this->assertSame(100000.0, $income(Const_Type::GARAGE_CAR, $user->id));
        $this->assertSame(149000.0, $income(null, null));
    }

    public function test_it_filters_charging_by_garage_type_and_tenant(): void
    {
        $electric = Garage::factory()->electricCar()->create();
        $user = User::factory()->create();
        $rental = $this->rental('2026-11-05', 100000, $electric, $user);
        $this->charging($rental, '2026-11-06 10:00:00', 20000);
        $this->charging($this->rental('2026-11-05', 1000), '2026-11-06 10:00:00', 7000);
        $charging = fn (?string $type, ?string $userId): float => ReportService::forRange('2026-11-01', '2026-11-30', $type, $userId)['rows'][5]['charging_income'];

        $this->assertSame(20000.0, $charging(Const_Type::GARAGE_ELECTRIC_CAR, null));
        $this->assertSame(20000.0, $charging(null, $user->id));
        $this->assertSame(27000.0, $charging(null, null));
    }

    // ---- forRange: validation ---------------------------------------------

    public function test_it_rejects_a_missing_or_malformed_date(): void
    {
        $this->assertReportFails(fn () => ReportService::forRange(null, '2026-11-30'), 'Start date is required.');
        $this->assertReportFails(fn () => ReportService::forRange('', '2026-11-30'), 'Start date is required.');
        $this->assertReportFails(fn () => ReportService::forRange('2026-11-01', null), 'End date is required.');
        $this->assertReportFails(fn () => ReportService::forRange('01-11-2026', '2026-11-30'), 'Start date is not valid.');
        $this->assertReportFails(fn () => ReportService::forRange('2026-11-01', 'abc'), 'End date is not valid.');
        $this->assertReportFails(fn () => ReportService::forRange('2026-02-31', '2026-11-30'), 'Start date is not valid.');
    }

    public function test_it_rejects_a_start_before_2026_and_an_end_before_the_start(): void
    {
        $this->assertReportFails(fn () => ReportService::forRange('2025-12-31', '2026-11-30'), 'Start date cannot be before 2026.');
        $this->assertReportFails(fn () => ReportService::forRange('2026-11-10', '2026-11-09'), 'End date cannot be before start date.');
    }

    public function test_it_rejects_an_end_date_that_is_too_far_away(): void
    {
        $this->assertReportFails(fn () => ReportService::forRange('2026-01-01', '2301-01-01'), 'End date is not valid.');
    }

    public function test_it_rejects_an_unknown_garage_type(): void
    {
        $this->assertReportFails(fn () => ReportService::forRange('2026-11-01', '2026-11-30', 'boat'), 'Garage type is not valid.');
    }
}
