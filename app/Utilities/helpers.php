<?php

declare(strict_types=1);

use App\Utilities\Constants\Const_Common;
use App\Utilities\Constants\Const_Date;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// ----------------------------------------------------------------------------
// General
// ----------------------------------------------------------------------------

if (! function_exists('_check_permissions')) {
    /**
     * Single switch for the permission layer (HasRoute, HasCanAction,
     * HasCheckPermissionGate). Off by default; turn it on later by setting
     * `check_permissions` => true in config/app.php.
     */
    function _check_permissions(): bool
    {
        return (bool) config('app.check_permissions', false);
    }
}

if (! function_exists('_get_class_name')) {
    function _get_class_name($model): string
    {
        $classNameWithNamespace = get_class($model);

        return mb_substr($classNameWithNamespace, mb_strrpos($classNameWithNamespace, '\\') + 1);
    }
}

if (! function_exists('_sanitize_data')) {
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    function _sanitize_data(array $data): array
    {
        return array_filter($data, static fn ($value): bool => filled($value));
    }
}

if (! function_exists('_get_exception_message')) {
    function _get_exception_message(Throwable $exception, bool $withCode = true): string
    {
        if ($exception->getCode() === 23000 && config('app.debug') === false) {
            return 'Code has been used / duplicate.';
        }

        if ($exception->getCode() === 1062 && config('app.debug') === false) {
            return 'Code have duplicate.';
        }

        $random = Str::random(6);

        $email = optional(auth()->user())->email ?? 'NO_USER';
        $message = sprintf('[%s] [%s] - ', $random, $email);
        $message .= $exception->getMessage()."\n\n";
        $message .= "Stack Trace: \n";
        $message .= $exception->getTraceAsString();
        Log::debug($message);

        if ($withCode) {
            return $exception->getMessage().sprintf(' [%s]', $random);
        }

        return $exception->getMessage();
    }
}

if (! function_exists('_markdown')) {
    /**
     * Render markdown as safe HTML: embedded HTML is stripped and unsafe links
     * are dropped, so user written content can be shown as is.
     */
    function _markdown(?string $markdown): string
    {
        if (blank($markdown)) {
            return '';
        }

        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}

// ----------------------------------------------------------------------------
// Number parsers
// ----------------------------------------------------------------------------

if (! function_exists('_get_formatted_number')) {
    function _get_formatted_number(
        $value,
        $decimals = 2,
        $decimal_sep = ',',
        $thousands_sep = '.',
        $trimDecimalZeroes = true,
    ): string {
        if ($value === null) {
            $value = 0;
        }

        $result = number_format((float) $value, $decimals, $decimal_sep, $thousands_sep);

        if ($trimDecimalZeroes && mb_strpos($result, $decimal_sep) !== false) {
            $result = mb_rtrim(mb_rtrim($result, '0'), $decimal_sep);
        }

        return $result;
    }
}

if (! function_exists('_number')) {
    function _number($amount, $file_type = Const_Common::FILETYPE_WEB)
    {
        if ($file_type === Const_Common::FILETYPE_XLSX) {
            return $amount;
        }

        return _get_formatted_number($amount);
    }
}

if (! function_exists('_money')) {
    function _money($amount, ?string $currency = null): ?string
    {
        if ($amount === null) {
            return null;
        }

        return mb_trim($currency.' '._number($amount));
    }
}

if (! function_exists('_numberReport')) {
    function _numberReport($amount, $file_type = Const_Common::FILETYPE_WEB): float|int|string
    {
        if ($file_type === Const_Common::FILETYPE_XLSX) {
            return _round($amount, 2);
        }

        return _get_formatted_number($amount, 2);
    }
}

if (! function_exists('_number_to_words')) {
    function _number_to_words($amount, $locale = 'en'): string|false
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::SPELLOUT);

        return $formatter->format((float) $amount);
    }
}

if (! function_exists('_round')) {
    function _round($value, $decimal = 4): float|int
    {
        return $value ? round((float) $value, $decimal) : 0;
    }
}

if (! function_exists('_is_zero')) {
    function _is_zero($value): bool
    {
        return (float) $value === 0.0;
    }
}

// ----------------------------------------------------------------------------
// Date parsers
// ----------------------------------------------------------------------------

if (! function_exists('_date_format_output')) {
    function _date_format_output($date)
    {
        if ($date === null) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date->format(Const_Date::DATE_FORMAT_OUTPUT);
        }

        foreach ([Const_Date::DATE_FORMAT_DB, Const_Date::DATETIME_FORMAT_DB, Const_Date::DATE_FORMAT_OUTPUT, Const_Date::DATETIME_FORMAT_OUTPUT] as $format) {
            try {
                return Carbon::createFromFormat('!'.$format, $date)->format(Const_Date::DATE_FORMAT_OUTPUT);
            } catch (Exception) {
            }
        }

        return $date;
    }
}

if (! function_exists('_date_format_db')) {
    function _date_format_db($date)
    {
        if ($date === null) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date->format(Const_Date::DATE_FORMAT_DB);
        }

        foreach ([Const_Date::DATETIME_FORMAT_OUTPUT, Const_Date::DATE_FORMAT_OUTPUT] as $format) {
            try {
                return Carbon::createFromFormat('!'.$format, $date)->format(Const_Date::DATE_FORMAT_DB);
            } catch (Exception) {
            }
        }

        return $date;
    }
}

if (! function_exists('_datetime_format_db')) {
    function _datetime_format_db($date)
    {
        if ($date === null) {
            return null;
        }

        if ($date instanceof DateTimeInterface) {
            return Carbon::instance($date)->format(Const_Date::DATETIME_FORMAT_DB);
        }

        foreach ([Const_Date::DATETIME_FORMAT_OUTPUT, Const_Date::DATE_FORMAT_OUTPUT] as $format) {
            try {
                return Carbon::createFromFormat('!'.$format, $date)->format(Const_Date::DATETIME_FORMAT_DB);
            } catch (Exception) {
            }
        }

        return $date;
    }
}

if (! function_exists('_datetime_carbon_db')) {
    function _datetime_carbon_db($date)
    {
        if ($date === null) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date;
        }

        if ($date instanceof DateTimeInterface) {
            return Carbon::instance($date);
        }

        foreach ([Const_Date::DATETIME_FORMAT_OUTPUT, Const_Date::DATE_FORMAT_OUTPUT] as $format) {
            try {
                return Carbon::createFromFormat('!'.$format, $date);
            } catch (Exception) {
            }
        }

        return $date;
    }
}

if (! function_exists('_get_default_date')) {
    function _get_default_date(): string
    {
        return Carbon::now()->format(Const_Date::DATE_FORMAT_OUTPUT);
    }
}

if (! function_exists('_get_default_datetime')) {
    function _get_default_datetime(): string
    {
        return Carbon::now()->format(Const_Date::DATETIME_FORMAT_OUTPUT);
    }
}

if (! function_exists('_get_default_date_range')) {
    function _get_default_date_range($is_from_begining = false, $end_date = null, $start_date = null): string
    {
        if ($is_from_begining) {
            $start_date = Carbon::create(Const_Common::BEGINNING_YEAR)->startOfYear();
        }

        $start_date ??= Carbon::now()->startOfMonth();
        $end_date ??= Carbon::now()->endOfMonth();

        return _date_format_output($start_date).Const_Date::DATERANGE_SEPARATOR._date_format_output($end_date);
    }
}

if (! function_exists('_get_default_datetime_range')) {
    function _get_default_datetime_range($is_from_begining = false): string
    {
        $start_date = $is_from_begining
            ? Carbon::create(Const_Common::BEGINNING_YEAR)->startOfYear()
            : Carbon::now()->startOfMonth();

        return $start_date->format(Const_Date::DATETIME_FORMAT_OUTPUT)
            .Const_Date::DATERANGE_SEPARATOR
            .Carbon::now()->endOfMonth()->format(Const_Date::DATETIME_FORMAT_OUTPUT);
    }
}

if (! function_exists('_datetime_carbon_split_filter_date')) {
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    function _datetime_carbon_split_filter_date($date_range): array
    {
        $dates = explode(Const_Date::DATERANGE_SEPARATOR, $date_range);
        $start_date = _datetime_carbon_db($dates[0])->startOfDay();
        $end_date = $start_date->clone()->endOfDay();

        if (count($dates) > 1) {
            $end_date = _datetime_carbon_db($dates[1])->endOfDay();
        }

        return [$start_date, $end_date];
    }
}

if (! function_exists('_convertTimezoneCarbon')) {
    function _convertTimezoneCarbon(Carbon $datetime, $from_timezone, $to_timezone): Carbon
    {
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $datetime->toDateTimeString(), $from_timezone);

        return $date->setTimezone($to_timezone);
    }
}

// ----------------------------------------------------------------------------
// File parsers
// ----------------------------------------------------------------------------

if (! function_exists('_insert_timestamp_before_extension')) {
    function _insert_timestamp_before_extension($string)
    {
        $timestamp = Carbon::now()->format('Ymd_His');
        $extension_pos = mb_strrpos($string, '.');

        if ($extension_pos) {
            return mb_substr($string, 0, $extension_pos).'_'.$timestamp.mb_substr($string, $extension_pos);
        }

        return $string;
    }
}

if (! function_exists('_convert_pdf_output_to_base64')) {
    function _convert_pdf_output_to_base64(string $string): string
    {
        return chunk_split(base64_encode($string));
    }
}

if (! function_exists('_convert_image_to_base64')) {
    function _convert_image_to_base64($filepath): ?string
    {
        if (! $filepath || ! file_exists($filepath)) {
            return null;
        }

        return 'data:image/'.pathinfo($filepath, PATHINFO_EXTENSION).';base64,'.base64_encode(file_get_contents($filepath));
    }
}

if (! function_exists('_convert_svg_to_base64')) {
    function _convert_svg_to_base64($filepath): ?string
    {
        if (! $filepath || ! file_exists($filepath)) {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode(file_get_contents($filepath));
    }
}

if (! function_exists('_convert_base64_to_image')) {
    function _convert_base64_to_image(string $base64): string|false
    {
        $data = explode(',', $base64);

        return base64_decode($data[1] ?? '');
    }
}
