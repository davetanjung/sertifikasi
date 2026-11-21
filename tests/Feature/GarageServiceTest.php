<?php

namespace Tests\Feature;

use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Services\GarageService;
use App\Utilities\Constants\Const_Type;
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

    public function test_a_bad_type_throws(): void
    {
        $this->expectException(GeneralException::class);

        GarageService::createBatch('truck', 1, 50000);
    }
}
