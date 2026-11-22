<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Models\ChargingTransaction;
use App\Models\Rental;
use App\Utilities\QueryHelpers\QH_DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueryDateTimeHelperTest extends TestCase
{
    use RefreshDatabase;

    private function rentalStarting(string $date): Rental
    {
        return Rental::factory()->create(['start_date' => $date, 'end_date' => $date, 'days' => 1]);
    }

    public function test_scope_date_range_matches_both_ends_inclusive(): void
    {
        $this->rentalStarting('2026-11-04');
        $first = $this->rentalStarting('2026-11-05');
        $last = $this->rentalStarting('2026-11-06');
        $this->rentalStarting('2026-11-07');

        $ids = QH_DateTime::scopeDateRange(Rental::query(), '2026-11-05 - 2026-11-06', 'start_date')->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$first->id, $last->id], $ids);
    }

    public function test_scope_date_range_with_one_date_matches_that_day_only(): void
    {
        $match = $this->rentalStarting('2026-11-05');
        $this->rentalStarting('2026-11-06');

        $this->assertSame([$match->id], QH_DateTime::scopeDateRange(Rental::query(), '2026-11-05', 'start_date')->pluck('id')->all());
    }

    public function test_scope_date_range_relation_filters_by_a_related_date(): void
    {
        $inside = ChargingTransaction::factory()->create(['created_at' => '2026-11-05 15:00:00']);
        ChargingTransaction::factory()->create(['created_at' => '2026-11-07 15:00:00']);

        $ranged = QH_DateTime::scopeDateRangeRelation(Rental::query(), '2026-11-05 - 2026-11-06', 'chargingTransactions', 'created_at')->pluck('id')->all();
        $single = QH_DateTime::scopeDateRangeRelation(Rental::query(), '2026-11-05', 'chargingTransactions', 'created_at')->pluck('id')->all();

        $this->assertSame([$inside->rental_id], $ranged);
        $this->assertSame([$inside->rental_id], $single);
    }

    public function test_scope_date_time_range_compares_the_full_timestamp(): void
    {
        $inside = ChargingTransaction::factory()->create(['created_at' => '2026-11-05 10:00:00']);
        ChargingTransaction::factory()->create(['created_at' => '2026-11-05 18:00:00']);

        $ranged = QH_DateTime::scopeDateTimeRange(ChargingTransaction::query(), '2026-11-05 09:00:00 - 2026-11-05 12:00:00', 'created_at')->pluck('id')->all();
        $exact = QH_DateTime::scopeDateTimeRange(ChargingTransaction::query(), '2026-11-05 10:00:00', 'created_at')->pluck('id')->all();

        $this->assertSame([$inside->id], $ranged);
        $this->assertSame([$inside->id], $exact);
    }

    public function test_scope_date_range_time_relation_filters_by_a_related_timestamp(): void
    {
        $inside = ChargingTransaction::factory()->create(['created_at' => '2026-11-05 10:00:00']);
        ChargingTransaction::factory()->create(['created_at' => '2026-11-05 18:00:00']);

        $ranged = QH_DateTime::scopeDateRangeTimeRelation(Rental::query(), '2026-11-05 09:00:00 - 2026-11-05 12:00:00', 'chargingTransactions', 'created_at')->pluck('id')->all();
        $exact = QH_DateTime::scopeDateRangeTimeRelation(Rental::query(), '2026-11-05 10:00:00', 'chargingTransactions', 'created_at')->pluck('id')->all();

        $this->assertSame([$inside->rental_id], $ranged);
        $this->assertSame([$inside->rental_id], $exact);
    }
}
