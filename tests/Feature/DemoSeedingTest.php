<?php

namespace Tests\Feature;

use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ReportService;
use App\Utilities\Constants\Const_Role;
use App\Utilities\Constants\Const_Status;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_it_does_not_leave_the_test_clock_set(): void
    {
        $this->assertFalse(Carbon::hasTestNow());
    }

    public function test_it_creates_the_admin_and_ten_tenants_with_their_statuses(): void
    {
        $this->assertSame(1, User::where('role', Const_Role::ADMIN)->count());
        $this->assertSame(10, User::where('role', Const_Role::TENANT)->count());
        $this->assertSame(7, User::where('registration_status', Const_Status::APPROVED)->where('role', Const_Role::TENANT)->count());
        $this->assertSame(2, User::where('registration_status', Const_Status::PENDING)->count());
        $this->assertSame(1, User::where('registration_status', Const_Status::REJECTED)->count());
    }

    public function test_it_creates_ten_vehicles_and_ten_garages_with_the_expected_codes(): void
    {
        $this->assertSame(10, Vehicle::count());

        $expected = ['S001', 'S002', 'S003', 'C001', 'C002', 'C003', 'C004', 'E001', 'E002', 'E003'];

        $this->assertSame(10, Garage::count());
        $this->assertEqualsCanonicalizing($expected, Garage::pluck('code')->all());
    }

    public function test_it_creates_rentals_extensions_and_charging_transactions(): void
    {
        $this->assertGreaterThanOrEqual(10, Rental::where('status', Const_Status::NEW)->count());
        $this->assertGreaterThanOrEqual(2, Rental::where('status', Const_Status::EXTENSION)->count());
        $this->assertGreaterThanOrEqual(3, ChargingTransaction::count());

        foreach (Rental::pluck('code') as $code) {
            $this->assertMatchesRegularExpression('/^RNT-\d{8}-\d{4}$/', $code);
        }
    }

    public function test_no_garage_or_vehicle_is_booked_twice_at_the_same_time(): void
    {
        $rentals = Rental::all();

        foreach (['garage_id', 'vehicle_id'] as $column) {
            foreach ($rentals->groupBy($column) as $rows) {
                foreach ($rows->values() as $index => $row) {
                    foreach ($rows->values()->slice($index + 1) as $other) {
                        if ($row->code === $other->code) {
                            continue;
                        }

                        $overlaps = $row->start_date->lte($other->end_date) && $other->start_date->lte($row->end_date);

                        $this->assertFalse($overlaps, "{$row->code} and {$other->code} overlap on {$column}.");
                    }
                }
            }
        }
    }

    public function test_the_monthly_report_shows_income_in_at_least_two_months(): void
    {
        $monthsWithIncome = 0;

        foreach (Rental::all()->map(fn (Rental $rental) => $rental->start_date->year)->unique() as $year) {
            foreach (ReportService::forRange("{$year}-01-01", "{$year}-12-31")['rows'] as $row) {
                if ($row['total_income'] > 0) {
                    $monthsWithIncome++;
                }
            }
        }

        $this->assertGreaterThanOrEqual(2, $monthsWithIncome);
    }
}
