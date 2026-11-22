<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Garage;
use App\Models\Rental;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarageTest extends TestCase
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
     * @return array<string, array{0: string, 1: string}>
     */
    public static function prefixes(): array
    {
        return [
            'bicycle' => [Const_Type::GARAGE_BICYCLE, 'S'],
            'car' => [Const_Type::GARAGE_CAR, 'C'],
            'electric car' => [Const_Type::GARAGE_ELECTRIC_CAR, 'E'],
        ];
    }

    public function test_the_code_is_generated_on_create_with_a_three_digit_number(): void
    {
        $first = Garage::factory()->car()->create(['code' => null]);
        $second = Garage::factory()->car()->create(['code' => null]);
        $electric = Garage::factory()->electricCar()->create(['code' => null]);

        $this->assertSame('C001', $first->code);
        $this->assertSame('C002', $second->code);
        $this->assertSame('E001', $electric->code);
    }

    public function test_status_is_available_when_no_rental_covers_today(): void
    {
        $garage = Garage::factory()->car()->create();
        Rental::factory()->create(['garage_id' => $garage->id, 'start_date' => '2026-11-11', 'end_date' => '2026-11-12']);
        Rental::factory()->create(['garage_id' => $garage->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-09']);

        $this->assertSame(Const_Status::AVAILABLE, $garage->status);
    }

    public function test_status_is_used_when_a_rental_covers_today_including_its_first_and_last_day(): void
    {
        $garage = Garage::factory()->car()->create();
        Rental::factory()->create(['garage_id' => $garage->id, 'start_date' => '2026-11-10', 'end_date' => '2026-11-10']);

        $this->assertSame(Const_Status::USED, $garage->status);
    }

    public function test_status_counts_an_extension_row_as_used(): void
    {
        $garage = Garage::factory()->car()->create();
        Rental::factory()->extension()->create([
            'garage_id' => $garage->id,
            'start_date' => '2026-11-09',
            'end_date' => '2026-11-11',
        ]);

        $this->assertSame(Const_Status::USED, $garage->status);
    }
}
