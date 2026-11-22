<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Models\Rental;
use App\Services\GarageService;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GarageServiceTest extends TestCase
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

    private function book(Garage $garage, string $start, string $end): Rental
    {
        return Rental::factory()->create([
            'garage_id' => $garage->id,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    // ---- createBatch ------------------------------------------------------

    public function test_create_batch_makes_sequential_codes_per_type(): void
    {
        $bicycles = GarageService::createBatch(Const_Type::GARAGE_BICYCLE, 2, 10000);
        $cars = GarageService::createBatch(Const_Type::GARAGE_CAR, 1, 50000);

        $this->assertSame(['S001', 'S002'], $bicycles->pluck('code')->all());
        $this->assertSame(['C001'], $cars->pluck('code')->all());
    }

    public function test_create_batch_continues_after_the_highest_existing_number(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_CAR, 3, 50000);

        $this->assertSame(['C004', 'C005'], GarageService::createBatch(Const_Type::GARAGE_CAR, 2, 50000)->pluck('code')->all());
    }

    public function test_create_batch_stores_the_daily_price_on_every_garage(): void
    {
        $garages = GarageService::createBatch(Const_Type::GARAGE_CAR, 3, 75000);

        $this->assertSame(['75000.00'], $garages->pluck('daily_price')->unique()->values()->all());
        $this->assertNull($garages->first()->price_per_kwh);
    }

    public function test_create_batch_stores_the_kwh_price_only_for_electric_garages(): void
    {
        $electric = GarageService::createBatch(Const_Type::GARAGE_ELECTRIC_CAR, 1, 90000, 3500);
        $car = GarageService::createBatch(Const_Type::GARAGE_CAR, 1, 50000, 3500);

        $this->assertSame('3500.00', $electric->first()->price_per_kwh);
        $this->assertSame('E001', $electric->first()->code);
        $this->assertNull($car->first()->price_per_kwh);
    }

    public function test_create_batch_requires_a_kwh_price_for_electric_garages(): void
    {
        foreach ([null, ''] as $price) {
            try {
                GarageService::createBatch(Const_Type::GARAGE_ELECTRIC_CAR, 1, 90000, $price);
                $this->fail('A missing kWh price should be rejected.');
            } catch (GeneralException $exception) {
                $this->assertSame('Price per kWh is required for electric car garages.', $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('garages', 0);
    }

    public function test_create_batch_rejects_an_unknown_type(): void
    {
        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Garage type is not valid.');

        GarageService::createBatch('boat', 1, 10000);
    }

    public function test_create_batch_rejects_a_quantity_out_of_range(): void
    {
        foreach ([0, -1, 1000] as $quantity) {
            try {
                GarageService::createBatch(Const_Type::GARAGE_CAR, $quantity, 50000);
                $this->fail("Quantity {$quantity} should be rejected.");
            } catch (GeneralException $exception) {
                $this->assertSame('Quantity must be between 1 and 999.', $exception->getMessage());
            }
        }
    }

    public function test_create_batch_rejects_the_whole_batch_when_it_would_pass_999(): void
    {
        Garage::factory()->car()->create(['code' => 'C998']);

        try {
            GarageService::createBatch(Const_Type::GARAGE_CAR, 2, 50000);
            $this->fail('The batch should be rejected.');
        } catch (GeneralException $exception) {
            $this->assertSame('Only 1 more car garages can be created (maximum 999).', $exception->getMessage());
        }

        $this->assertDatabaseCount('garages', 1);
    }

    public function test_create_batch_can_fill_up_to_exactly_999(): void
    {
        Garage::factory()->car()->create(['code' => 'C998']);

        $this->assertSame(['C999'], GarageService::createBatch(Const_Type::GARAGE_CAR, 1, 50000)->pluck('code')->all());
    }

    // ---- nextCodes --------------------------------------------------------

    public function test_next_codes_previews_the_first_and_last_code(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_BICYCLE, 2, 10000);

        $this->assertSame(
            ['first' => 'S003', 'last' => 'S007', 'remaining' => 997, 'fits' => true],
            GarageService::nextCodes(Const_Type::GARAGE_BICYCLE, 5),
        );
    }

    public function test_next_codes_starts_at_001_for_an_empty_type(): void
    {
        $this->assertSame('E001', GarageService::nextCodes(Const_Type::GARAGE_ELECTRIC_CAR, 1)['first']);
    }

    public function test_next_codes_reports_a_batch_that_does_not_fit_without_throwing(): void
    {
        Garage::factory()->car()->create(['code' => 'C998']);

        $this->assertSame(
            ['first' => null, 'last' => null, 'remaining' => 1, 'fits' => false],
            GarageService::nextCodes(Const_Type::GARAGE_CAR, 2),
        );
        $this->assertFalse(GarageService::nextCodes(Const_Type::GARAGE_CAR, 0)['fits']);
    }

    public function test_next_codes_rejects_an_unknown_type(): void
    {
        $this->expectException(GeneralException::class);

        GarageService::nextCodes('boat', 1);
    }

    // ---- typesFor ---------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function fits(): array
    {
        return [
            'bicycle' => [Const_Type::VEHICLE_BICYCLE, [Const_Type::GARAGE_BICYCLE]],
            'motorcycle' => [Const_Type::VEHICLE_MOTORCYCLE, [Const_Type::GARAGE_BICYCLE]],
            'car' => [Const_Type::VEHICLE_CAR, [Const_Type::GARAGE_CAR]],
            'electric car' => [Const_Type::VEHICLE_ELECTRIC_CAR, [Const_Type::GARAGE_CAR, Const_Type::GARAGE_ELECTRIC_CAR]],
            'unknown' => ['truck', []],
            'empty' => ['', []],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('fits')]
    public function test_types_for_returns_the_garage_types_a_vehicle_fits(string $vehicleType, array $expected): void
    {
        $this->assertSame($expected, GarageService::typesFor($vehicleType));
    }

    // ---- availableFor -----------------------------------------------------

    public function test_available_for_lists_free_garages_of_the_type_ordered_by_code(): void
    {
        $second = Garage::factory()->car()->create(['code' => 'C002']);
        $first = Garage::factory()->car()->create(['code' => 'C001']);
        Garage::factory()->bicycle()->create(['code' => 'S001']);

        $result = GarageService::availableFor(Const_Type::GARAGE_CAR, '2026-11-12', '2026-11-14');

        $this->assertSame([$first->id, $second->id], $result->pluck('id')->all());
    }

    public function test_available_for_excludes_garages_booked_in_the_range(): void
    {
        $booked = Garage::factory()->car()->create(['code' => 'C001']);
        $free = Garage::factory()->car()->create(['code' => 'C002']);
        $this->book($booked, '2026-11-13', '2026-11-15');

        $result = GarageService::availableFor(Const_Type::GARAGE_CAR, '2026-11-12', '2026-11-14');

        $this->assertSame([$free->id], $result->pluck('id')->all());
    }

    public function test_available_for_rejects_an_unknown_type(): void
    {
        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Garage type is not valid.');

        GarageService::availableFor('boat', '2026-11-12', '2026-11-14');
    }

    // ---- availableForTypes ------------------------------------------------

    public function test_available_for_types_merges_several_types(): void
    {
        Garage::factory()->car()->create(['code' => 'C001']);
        Garage::factory()->electricCar()->create(['code' => 'E001']);
        Garage::factory()->bicycle()->create(['code' => 'S001']);

        $result = GarageService::availableForTypes(
            [Const_Type::GARAGE_CAR, Const_Type::GARAGE_ELECTRIC_CAR],
            '2026-11-12',
            '2026-11-14',
        );

        $this->assertSame(['C001', 'E001'], $result['garages']->pluck('code')->all());
        $this->assertSame(2, $result['total']);
    }

    public function test_available_for_types_searches_the_code_case_insensitively(): void
    {
        Garage::factory()->car()->create(['code' => 'C001']);
        Garage::factory()->car()->create(['code' => 'C012']);

        $result = GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-12', '2026-11-14', 'C0');

        $this->assertSame(['C001', 'C012'], $result['garages']->pluck('code')->all());
        $this->assertSame(1, GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-12', '2026-11-14', ' c012 ')['total']);
    }

    public function test_available_for_types_matches_percent_and_underscore_literally(): void
    {
        Garage::factory()->car()->create(['code' => 'C001']);

        $this->assertSame(0, GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-12', '2026-11-14', '%')['total']);
        $this->assertSame(0, GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-12', '2026-11-14', '_')['total']);
    }

    public function test_available_for_types_limits_the_list_but_counts_every_match(): void
    {
        foreach (['C001', 'C002', 'C003'] as $code) {
            Garage::factory()->car()->create(['code' => $code]);
        }

        $result = GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-12', '2026-11-14', null, 2);

        $this->assertSame(['C001', 'C002'], $result['garages']->pluck('code')->all());
        $this->assertSame(3, $result['total']);
        $this->assertCount(0, GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-12', '2026-11-14', null, -5)['garages']);
    }

    public function test_available_for_types_rejects_an_unknown_type_and_a_backwards_range(): void
    {
        try {
            GarageService::availableForTypes(['boat'], '2026-11-12', '2026-11-14');
            $this->fail('An unknown type should be rejected.');
        } catch (GeneralException $exception) {
            $this->assertSame('Garage type is not valid.', $exception->getMessage());
        }

        $this->expectExceptionMessage('End date cannot be before start date.');

        GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-14', '2026-11-12');
    }

    // ---- whereFree --------------------------------------------------------

    public function test_where_free_keeps_only_garages_without_an_overlapping_rental(): void
    {
        $free = Garage::factory()->car()->create(['code' => 'C001']);
        $overlap = Garage::factory()->car()->create(['code' => 'C002']);
        $this->book($overlap, '2026-11-10', '2026-11-12');

        $query = Garage::query();
        GarageService::whereFree($query, '2026-11-12', '2026-11-14');

        $this->assertSame([$free->id], $query->pluck('id')->all());
    }

    public function test_where_free_treats_the_day_after_the_end_date_as_free(): void
    {
        $garage = Garage::factory()->car()->create();
        $this->book($garage, '2026-11-10', '2026-11-12');

        $onEndDate = Garage::query();
        GarageService::whereFree($onEndDate, '2026-11-12', '2026-11-12');
        $dayAfter = Garage::query();
        GarageService::whereFree($dayAfter, '2026-11-13', '2026-11-13');
        $dayBefore = Garage::query();
        GarageService::whereFree($dayBefore, '2026-11-09', '2026-11-09');

        $this->assertSame(0, $onEndDate->count());
        $this->assertSame(1, $dayAfter->count());
        $this->assertSame(1, $dayBefore->count());
    }

    public function test_where_free_counts_a_booking_inside_the_range_as_overlap(): void
    {
        $garage = Garage::factory()->car()->create();
        $this->book($garage, '2026-11-15', '2026-11-16');

        $query = Garage::query();
        GarageService::whereFree($query, '2026-11-12', '2026-11-20');

        $this->assertSame(0, $query->count());
    }
}
