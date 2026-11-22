<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DateAndFileHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 09:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_date_format_output_accepts_carbon_strings_and_null(): void
    {
        $this->assertSame('2026-11-20', _date_format_output(Carbon::parse('2026-11-20 15:00:00')));
        $this->assertSame('2026-11-20', _date_format_output('2026-11-20'));
        $this->assertSame('2026-11-20', _date_format_output('2026-11-20 10:30:00'));
        $this->assertNull(_date_format_output(null));
        $this->assertSame('not a date', _date_format_output('not a date'));
    }

    public function test_date_format_db_accepts_carbon_strings_and_null(): void
    {
        $this->assertSame('2026-02-01', _date_format_db(Carbon::parse('2026-02-01 23:59:59')));
        $this->assertSame('2026-02-01', _date_format_db('2026-02-01'));
        $this->assertNull(_date_format_db(null));
        $this->assertSame('garbage', _date_format_db('garbage'));
    }

    public function test_datetime_format_db_accepts_dates_datetimes_and_null(): void
    {
        $this->assertSame('2026-11-20 00:00:00', _datetime_format_db('2026-11-20'));
        $this->assertSame('2026-11-20 08:15:00', _datetime_format_db('2026-11-20 08:15:00'));
        $this->assertSame('2026-11-20 09:30:00', _datetime_format_db(new \DateTimeImmutable('2026-11-20 09:30:00')));
        $this->assertNull(_datetime_format_db(null));
    }

    public function test_datetime_carbon_db_returns_carbon_instances(): void
    {
        $this->assertSame('2026-11-20 00:00:00', _datetime_carbon_db('2026-11-20')->toDateTimeString());
        $this->assertSame('2026-11-20 10:00:00', _datetime_carbon_db('2026-11-20 10:00:00')->toDateTimeString());
        $this->assertNull(_datetime_carbon_db(null));

        $carbon = Carbon::parse('2026-01-01');
        $this->assertSame($carbon, _datetime_carbon_db($carbon));
    }

    public function test_default_date_and_datetime_use_the_current_time(): void
    {
        $this->assertSame('2026-11-20', _get_default_date());
        $this->assertSame('2026-11-20 09:30:00', _get_default_datetime());
    }

    public function test_default_date_range_is_the_current_month(): void
    {
        $this->assertSame('2026-11-01 - 2026-11-30', _get_default_date_range());
    }

    public function test_default_date_range_can_start_from_the_beginning_year(): void
    {
        $this->assertSame('2025-01-01 - 2026-11-30', _get_default_date_range(true));
    }

    public function test_default_date_range_accepts_explicit_dates(): void
    {
        $this->assertSame(
            '2026-03-05 - 2026-03-09',
            _get_default_date_range(false, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-05')),
        );
    }

    public function test_default_datetime_range_covers_the_whole_month(): void
    {
        $this->assertSame('2026-11-01 00:00:00 - 2026-11-30 23:59:59', _get_default_datetime_range());
        $this->assertSame('2025-01-01 00:00:00 - 2026-11-30 23:59:59', _get_default_datetime_range(true));
    }

    public function test_a_single_date_splits_into_one_whole_day(): void
    {
        [$start, $end] = _datetime_carbon_split_filter_date('2026-11-20');

        $this->assertSame('2026-11-20 00:00:00', $start->toDateTimeString());
        $this->assertSame('2026-11-20 23:59:59', $end->toDateTimeString());
    }

    public function test_a_date_range_splits_into_start_and_end_of_day(): void
    {
        [$start, $end] = _datetime_carbon_split_filter_date('2026-11-01 - 2026-11-30');

        $this->assertSame('2026-11-01 00:00:00', $start->toDateTimeString());
        $this->assertSame('2026-11-30 23:59:59', $end->toDateTimeString());
    }

    public function test_it_converts_a_datetime_between_timezones(): void
    {
        $converted = _convertTimezoneCarbon(Carbon::parse('2026-11-20 10:00:00'), 'UTC', 'Asia/Jakarta');

        $this->assertSame('2026-11-20 17:00:00', $converted->format('Y-m-d H:i:s'));
    }

    public function test_it_adds_a_timestamp_before_the_file_extension(): void
    {
        $this->assertSame('report_20261120_093000.pdf', _insert_timestamp_before_extension('report.pdf'));
        $this->assertSame('a.b_20261120_093000.xlsx', _insert_timestamp_before_extension('a.b.xlsx'));
        $this->assertSame('noextension', _insert_timestamp_before_extension('noextension'));
    }

    public function test_it_converts_a_base64_data_url_back_to_bytes(): void
    {
        $this->assertSame('hello', _convert_base64_to_image('data:text/plain;base64,'.base64_encode('hello')));
        $this->assertSame('', _convert_base64_to_image('no-comma-here'));
    }

    public function test_it_converts_files_to_data_urls_and_ignores_missing_files(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'img').'.png';
        file_put_contents($path, 'abc');

        try {
            $this->assertSame('data:image/png;base64,'.base64_encode('abc'), _convert_image_to_base64($path));
            $this->assertSame('data:image/svg+xml;base64,'.base64_encode('abc'), _convert_svg_to_base64($path));
        } finally {
            unlink($path);
        }

        $this->assertNull(_convert_image_to_base64('/no/such/file.png'));
        $this->assertNull(_convert_image_to_base64(null));
        $this->assertNull(_convert_svg_to_base64(''));
    }

    public function test_pdf_output_is_base64_chunked(): void
    {
        $this->assertSame(chunk_split(base64_encode('pdf')), _convert_pdf_output_to_base64('pdf'));
    }
}
