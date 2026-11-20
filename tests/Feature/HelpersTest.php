<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_it_drops_blank_values_but_keeps_zero_and_false(): void
    {
        $this->assertSame(
            ['a' => 'x', 'd' => 0, 'e' => false],
            _sanitize_data(['a' => 'x', 'b' => '', 'c' => null, 'd' => 0, 'e' => false]),
        );
    }

    public function test_it_parses_dates_between_output_and_db_formats(): void
    {
        $this->assertSame('2026-11-20', _date_format_db('2026-11-20'));
        $this->assertSame('2026-11-20', _date_format_output('2026-11-20 10:30:00'));
        $this->assertSame('2026-11-20 00:00:00', _datetime_format_db('2026-11-20'));
        $this->assertNull(_date_format_db(null));
        $this->assertSame('not a date', _date_format_output('not a date'));
    }

    public function test_it_splits_a_date_range_into_start_and_end_of_day(): void
    {
        [$start, $end] = _datetime_carbon_split_filter_date('2026-11-01 - 2026-11-30');

        $this->assertSame('2026-11-01 00:00:00', $start->toDateTimeString());
        $this->assertSame('2026-11-30 23:59:59', $end->toDateTimeString());
    }

    public function test_it_formats_numbers_and_money(): void
    {
        $this->assertSame('1.234,5', _get_formatted_number(1234.5));
        $this->assertSame('Rp 1.000', _money(1000, 'Rp'));
        $this->assertNull(_money(null));
        $this->assertSame(0, _round(null));
    }

    public function test_it_adds_a_timestamp_before_the_file_extension(): void
    {
        $this->assertMatchesRegularExpression(
            '/^report_\d{8}_\d{6}\.pdf$/',
            _insert_timestamp_before_extension('report.pdf'),
        );
    }

    public function test_it_keeps_permissions_off_by_default(): void
    {
        $this->assertFalse(_check_permissions());
    }
}
