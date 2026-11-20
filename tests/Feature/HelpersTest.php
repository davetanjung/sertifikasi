<?php

declare(strict_types=1);

it('drops blank values but keeps zero and false', function () {
    expect(_sanitize_data(['a' => 'x', 'b' => '', 'c' => null, 'd' => 0, 'e' => false]))
        ->toBe(['a' => 'x', 'd' => 0, 'e' => false]);
});

it('parses dates between output and db formats', function () {
    expect(_date_format_db('2026-11-20'))->toBe('2026-11-20')
        ->and(_date_format_output('2026-11-20 10:30:00'))->toBe('2026-11-20')
        ->and(_datetime_format_db('2026-11-20'))->toBe('2026-11-20 00:00:00')
        ->and(_date_format_db(null))->toBeNull()
        ->and(_date_format_output('not a date'))->toBe('not a date');
});

it('splits a date range into start and end of day', function () {
    [$start, $end] = _datetime_carbon_split_filter_date('2026-11-01 - 2026-11-30');

    expect($start->toDateTimeString())->toBe('2026-11-01 00:00:00')
        ->and($end->toDateTimeString())->toBe('2026-11-30 23:59:59');
});

it('formats numbers and money', function () {
    expect(_get_formatted_number(1234.5))->toBe('1.234,5')
        ->and(_money(1000, 'Rp'))->toBe('Rp 1.000')
        ->and(_money(null))->toBeNull()
        ->and(_round(null))->toBe(0);
});

it('adds a timestamp before the file extension', function () {
    expect(_insert_timestamp_before_extension('report.pdf'))->toMatch('/^report_\d{8}_\d{6}\.pdf$/');
});

it('keeps permissions off by default', function () {
    expect(_check_permissions())->toBeFalse();
});
