<?php

namespace Tests\Feature;

use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GarageService;
use App\Services\RentalService;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_batch_of_three_creates_s001_to_s003(): void
    {
        $garages = GarageService::createBatch(Const_Type::GARAGE_BICYCLE, 3, 10000);

        $this->assertSame(['S001', 'S002', 'S003'], $garages->pluck('code')->all());
        $this->assertSame(3, Garage::count());
    }

    public function test_a_following_batch_continues_the_numbering(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_BICYCLE, 3, 10000);

        $garages = GarageService::createBatch(Const_Type::GARAGE_BICYCLE, 2, 10000);

        $this->assertSame(['S004', 'S005'], $garages->pluck('code')->all());
    }

    public function test_numbering_is_separate_per_type(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_BICYCLE, 2, 10000);

        $garages = GarageService::createBatch(Const_Type::GARAGE_CAR, 1, 50000);

        $this->assertSame(['C001'], $garages->pluck('code')->all());
    }

    public function test_electric_garages_keep_price_per_kwh_and_others_get_null(): void
    {
        $electric = GarageService::createBatch(Const_Type::GARAGE_ELECTRIC_CAR, 1, 80000, 2500);
        $car = GarageService::createBatch(Const_Type::GARAGE_CAR, 1, 50000, 2500);

        $this->assertSame('E001', $electric->first()->code);
        $this->assertSame('2500.00', $electric->first()->fresh()->price_per_kwh);
        $this->assertNull($car->first()->fresh()->price_per_kwh);
    }

    public function test_electric_garage_requires_price_per_kwh(): void
    {
        $this->expectException(GeneralException::class);

        try {
            GarageService::createBatch(Const_Type::GARAGE_ELECTRIC_CAR, 1, 80000);
        } finally {
            $this->assertSame(0, Garage::count());
        }
    }

    public function test_a_batch_passing_999_creates_nothing_and_throws(): void
    {
        for ($i = 1; $i <= 998; $i++) {
            Garage::create([
                'code' => 'C'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'type' => Const_Type::GARAGE_CAR,
                'daily_price' => 50000,
            ]);
        }

        try {
            GarageService::createBatch(Const_Type::GARAGE_CAR, 2, 50000);
            $this->fail('Expected GeneralException was not thrown.');
        } catch (GeneralException $exception) {
            $this->assertSame('Only 1 more car garages can be created (maximum 999).', $exception->getMessage());
        }

        $this->assertSame(998, Garage::count());
    }

    public function test_quantity_zero_throws(): void
    {
        $this->expectException(GeneralException::class);

        GarageService::createBatch(Const_Type::GARAGE_CAR, 0, 50000);
    }

    public function test_types_for_a_vehicle_type(): void
    {
        $this->assertSame([Const_Type::GARAGE_BICYCLE], GarageService::typesFor(Const_Type::VEHICLE_BICYCLE));
        $this->assertSame([Const_Type::GARAGE_BICYCLE], GarageService::typesFor(Const_Type::VEHICLE_MOTORCYCLE));
        $this->assertSame([Const_Type::GARAGE_CAR], GarageService::typesFor(Const_Type::VEHICLE_CAR));
        $this->assertSame(
            [Const_Type::GARAGE_CAR, Const_Type::GARAGE_ELECTRIC_CAR],
            GarageService::typesFor(Const_Type::VEHICLE_ELECTRIC_CAR)
        );
        $this->assertSame([], GarageService::typesFor('truck'));
    }

    public function test_available_for_excludes_overlaps_includes_back_to_back_and_orders_by_code(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');

        $garages = GarageService::createBatch(Const_Type::GARAGE_CAR, 5, 100000); // C001 to C005
        $user = User::factory()->create();

        // Asked range: 15 to 18 Nov. C001: 10 to 12 Nov (free). C002: 10 to 12 Nov plus an extension
        // 13 to 16 Nov (overlaps). C003: ends 14 Nov (back to back). C004 and C005: no rentals.
        RentalService::create($this->rentalData($garages[0], $user, 3));
        $withExtension = RentalService::create($this->rentalData($garages[1], $user, 3));
        RentalService::extend($withExtension, '2026-11-16', 'proofs/ext.jpg');
        RentalService::create($this->rentalData($garages[2], $user, 5));

        $available = GarageService::availableFor(Const_Type::GARAGE_CAR, '2026-11-15', '2026-11-18');

        $this->assertSame(['C001', 'C003', 'C004', 'C005'], $available->pluck('code')->all());
        $this->assertInstanceOf(EloquentCollection::class, $available);

        $overlappingNew = GarageService::availableFor(Const_Type::GARAGE_CAR, '2026-11-12', '2026-11-12');
        $this->assertNotContains('C001', $overlappingNew->pluck('code')->all());
        $this->assertNotContains('C002', $overlappingNew->pluck('code')->all());
        $this->assertNotContains('C003', $overlappingNew->pluck('code')->all());

        Carbon::setTestNow();
    }

    public function test_available_for_rejects_a_bad_type_and_an_end_before_the_start(): void
    {
        try {
            GarageService::availableFor('truck', '2026-11-10', '2026-11-12');
            $this->fail('Expected GeneralException was not thrown.');
        } catch (GeneralException $exception) {
            $this->assertSame('Garage type is not valid.', $exception->getMessage());
        }

        $this->expectException(GeneralException::class);

        GarageService::availableFor(Const_Type::GARAGE_CAR, '2026-11-12', '2026-11-10');
    }

    public function test_available_for_types_returns_several_types_in_one_list_ordered_by_code(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_ELECTRIC_CAR, 2, 100000, 2500);
        GarageService::createBatch(Const_Type::GARAGE_CAR, 2, 100000);
        GarageService::createBatch(Const_Type::GARAGE_BICYCLE, 1, 10000);

        $result = GarageService::availableForTypes(
            [Const_Type::GARAGE_ELECTRIC_CAR, Const_Type::GARAGE_CAR],
            '2026-11-15',
            '2026-11-18',
        );

        $this->assertSame(['C001', 'C002', 'E001', 'E002'], $result['garages']->pluck('code')->all());
        $this->assertSame(4, $result['total']);
        $this->assertInstanceOf(EloquentCollection::class, $result['garages']);
    }

    public function test_available_for_types_searches_the_code_case_insensitively(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_CAR, 12, 100000);

        $result = GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-15', '2026-11-18', 'c01');

        $this->assertSame(['C010', 'C011', 'C012'], $result['garages']->pluck('code')->all());
        $this->assertSame(3, $result['total']);
    }

    public function test_available_for_types_treats_percent_and_underscore_literally(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_CAR, 3, 100000);

        $this->assertSame(0, GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-15', '2026-11-18', '%')['total']);
        $this->assertSame(0, GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-15', '2026-11-18', 'C_01')['total']);
    }

    public function test_available_for_types_limits_the_list_but_counts_everything(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_CAR, 7, 100000);

        $result = GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-15', '2026-11-18', null, 3);

        $this->assertSame(['C001', 'C002', 'C003'], $result['garages']->pluck('code')->all());
        $this->assertSame(7, $result['total']);

        $searched = GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-15', '2026-11-18', 'C00', 2);

        $this->assertCount(2, $searched['garages']);
        $this->assertSame(7, $searched['total']);
    }

    public function test_available_for_types_has_the_same_free_logic_as_available_for(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');

        $garages = GarageService::createBatch(Const_Type::GARAGE_CAR, 5, 100000);
        $user = User::factory()->create();

        RentalService::create($this->rentalData($garages[0], $user, 3));
        $withExtension = RentalService::create($this->rentalData($garages[1], $user, 3));
        RentalService::extend($withExtension, '2026-11-16', 'proofs/ext.jpg');
        RentalService::create($this->rentalData($garages[2], $user, 5));

        $result = GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-15', '2026-11-18');

        $this->assertSame(
            GarageService::availableFor(Const_Type::GARAGE_CAR, '2026-11-15', '2026-11-18')->pluck('code')->all(),
            $result['garages']->pluck('code')->all(),
        );
        $this->assertSame(['C001', 'C003', 'C004', 'C005'], $result['garages']->pluck('code')->all());
        $this->assertSame(4, $result['total']);

        Carbon::setTestNow();
    }

    public function test_available_for_types_rejects_a_bad_type_and_an_end_before_the_start(): void
    {
        try {
            GarageService::availableForTypes([Const_Type::GARAGE_CAR, 'truck'], '2026-11-10', '2026-11-12');
            $this->fail('Expected GeneralException was not thrown.');
        } catch (GeneralException $exception) {
            $this->assertSame('Garage type is not valid.', $exception->getMessage());
        }

        $this->expectException(GeneralException::class);

        GarageService::availableForTypes([Const_Type::GARAGE_CAR], '2026-11-12', '2026-11-10');
    }

    /**
     * Data for a daily rental of a fresh car owned by the user.
     *
     * @return array<string, mixed>
     */
    private function rentalData(Garage $garage, User $user, int $days): array
    {
        $vehicle = Vehicle::factory()->car()->for($user)->create();

        return [
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => $days,
            'payment_proof_path' => 'proofs/new.jpg',
        ];
    }

    public function test_a_bad_type_throws(): void
    {
        $this->expectException(GeneralException::class);

        GarageService::createBatch('truck', 1, 50000);
    }

    public function test_next_codes_starts_at_001_when_there_are_no_garages(): void
    {
        $this->assertSame(
            ['first' => 'C001', 'last' => 'C003', 'remaining' => 999, 'fits' => true],
            GarageService::nextCodes(Const_Type::GARAGE_CAR, 3),
        );
    }

    public function test_next_codes_continues_after_the_highest_number(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_CAR, 10, 50000);

        $this->assertSame(
            ['first' => 'C011', 'last' => 'C020', 'remaining' => 989, 'fits' => true],
            GarageService::nextCodes(Const_Type::GARAGE_CAR, 10),
        );
        $this->assertSame(10, Garage::count());
    }

    public function test_next_codes_does_not_throw_when_the_quantity_does_not_fit(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_BICYCLE, 5, 10000);

        $this->assertSame(
            ['first' => null, 'last' => null, 'remaining' => 994, 'fits' => false],
            GarageService::nextCodes(Const_Type::GARAGE_BICYCLE, 995),
        );
    }

    public function test_next_codes_fits_exactly_up_to_999(): void
    {
        $result = GarageService::nextCodes(Const_Type::GARAGE_ELECTRIC_CAR, 999);

        $this->assertSame(['first' => 'E001', 'last' => 'E999', 'remaining' => 999, 'fits' => true], $result);
    }

    public function test_next_codes_throws_for_a_bad_type(): void
    {
        $this->expectException(GeneralException::class);

        GarageService::nextCodes('truck', 1);
    }
}
