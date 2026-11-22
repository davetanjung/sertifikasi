<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Utilities\Constants\Const_Common;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NumberHelpersTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function formattedNumbers(): array
    {
        return [
            'thousands separator' => [1234567, '1.234.567'],
            'decimals are kept' => [1234.5, '1.234,5'],
            'trailing zeroes are trimmed' => [10.0, '10'],
            'two decimals' => [0.25, '0,25'],
            'null is zero' => [null, '0'],
            'numeric string' => ['2500.75', '2.500,75'],
        ];
    }

    #[DataProvider('formattedNumbers')]
    public function test_it_formats_numbers(mixed $value, string $expected): void
    {
        $this->assertSame($expected, _get_formatted_number($value));
    }

    public function test_it_can_keep_decimal_zeroes(): void
    {
        $this->assertSame('10,00', _get_formatted_number(10, 2, ',', '.', false));
    }

    public function test_money_prefixes_the_currency_and_trims_when_there_is_none(): void
    {
        $this->assertSame('Rp 100.000', _money(100000, 'Rp'));
        $this->assertSame('100.000', _money(100000));
        $this->assertNull(_money(null));
        $this->assertSame('0', _money(0));
    }

    public function test_number_returns_the_raw_amount_for_xlsx_and_text_for_web(): void
    {
        $this->assertSame(1500.5, _number(1500.5, Const_Common::FILETYPE_XLSX));
        $this->assertSame('1.500,5', _number(1500.5));
    }

    public function test_number_report_rounds_for_xlsx_and_formats_for_web(): void
    {
        $this->assertSame(12.35, _numberReport(12.345, Const_Common::FILETYPE_XLSX));
        $this->assertSame('12,35', _numberReport(12.345));
    }

    public function test_round_treats_empty_values_as_zero(): void
    {
        $this->assertSame(0, _round(null));
        $this->assertSame(0, _round(0));
        $this->assertSame(0, _round(''));
        $this->assertSame(1.2346, _round(1.23456));
        $this->assertSame(1.23, _round('1.2345', 2));
    }

    public function test_is_zero_only_accepts_zero_values(): void
    {
        $this->assertTrue(_is_zero(0));
        $this->assertTrue(_is_zero('0.0'));
        $this->assertTrue(_is_zero(null));
        $this->assertFalse(_is_zero(0.0001));
        $this->assertFalse(_is_zero(-1));
    }

    public function test_sanitize_data_drops_blank_values_but_keeps_zero_and_false(): void
    {
        $this->assertSame(
            ['a' => 'x', 'd' => 0, 'e' => false],
            _sanitize_data(['a' => 'x', 'b' => '', 'c' => null, 'd' => 0, 'e' => false]),
        );
    }

    public function test_get_class_name_drops_the_namespace(): void
    {
        $this->assertSame('NumberHelpersTest', _get_class_name($this));
    }
}
