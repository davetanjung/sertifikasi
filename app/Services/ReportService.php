<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GeneralException;
use App\Models\ChargingTransaction;
use App\Models\Rental;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Admin reports. A rental row (new or extension) belongs to its start_date, a charging transaction
 * to the date it was created. Rows are loaded with plain date conditions and grouped in PHP, so the
 * result is the same on SQLite and MySQL.
 */
final class ReportService
{
    private const MINIMUM_YEAR = 2026;

    private const MAXIMUM_YEAR = 2300;

    public const BY_DAY = 'day';

    public const BY_MONTH = 'month';

    /**
     * The report for a date range. A range inside one calendar month gives one row per day, any longer
     * range gives one row per month (from the start month to the end month). Periods without activity
     * are included. Rentals are counted on their start date and charging on the day it was created.
     * The garage type and tenant filters narrow both.
     *
     * @return array{granularity: string, rows: list<array<string, mixed>>}
     *
     * @throws GeneralException
     */
    public static function forRange(
        string|CarbonInterface|null $startDate,
        string|CarbonInterface|null $endDate,
        ?string $garageType = null,
        ?string $userId = null,
    ): array {
        [$start, $end] = self::validateRange($startDate, $endDate, $garageType);

        $rentals = self::rentalRows($start, $end, $garageType, $userId);
        $chargings = self::chargingRows($start, $end, $garageType, $userId);

        if ($start->format('Y-m') === $end->format('Y-m')) {
            $days = collect(CarbonPeriod::create($start, $end))->map(fn (Carbon $day): string => $day->toDateString())->all();

            return [
                'granularity' => self::BY_DAY,
                'rows' => self::rowsPerKey(
                    $rentals,
                    $chargings,
                    $days,
                    fn (Rental $rental): string => $rental->start_date->toDateString(),
                    fn (ChargingTransaction $charging): string => $charging->created_at->toDateString(),
                    fn (string $day): array => ['date' => $day],
                ),
            ];
        }

        $months = collect(CarbonPeriod::create($start->copy()->startOfMonth(), '1 month', $end->copy()->startOfMonth()))
            ->map(fn (Carbon $month): string => $month->format('Y-m'))
            ->all();

        return [
            'granularity' => self::BY_MONTH,
            'rows' => self::rowsPerKey(
                $rentals,
                $chargings,
                $months,
                fn (Rental $rental): string => $rental->start_date->format('Y-m'),
                fn (ChargingTransaction $charging): string => $charging->created_at->format('Y-m'),
                fn (string $month): array => ['month' => $month],
            ),
        ];
    }

    /**
     * Reject filter values that make no sense and return the range as whole days.
     *
     * @return array{0: Carbon, 1: Carbon}
     *
     * @throws GeneralException
     */
    private static function validateRange(string|CarbonInterface|null $startDate, string|CarbonInterface|null $endDate, ?string $garageType): array
    {
        $start = self::parseDate($startDate, 'Start date');
        $end = self::parseDate($endDate, 'End date');

        if ($start->year < self::MINIMUM_YEAR) {
            throw new GeneralException('Start date cannot be before '.self::MINIMUM_YEAR.'.');
        }

        if ($end->lt($start)) {
            throw new GeneralException('End date cannot be before start date.');
        }

        if ($end->year > self::MAXIMUM_YEAR) {
            throw new GeneralException('End date is not valid.');
        }

        if ($garageType !== null && ! in_array($garageType, Const_Type::GARAGE_TYPES, true)) {
            throw new GeneralException('Garage type is not valid.');
        }

        return [$start, $end];
    }

    /**
     * @throws GeneralException
     */
    private static function parseDate(string|CarbonInterface|null $value, string $label): Carbon
    {
        if ($value === null || $value === '') {
            throw new GeneralException("{$label} is required.");
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $date = Carbon::canBeCreatedFromFormat($value, 'Y-m-d') ? Carbon::createFromFormat('!Y-m-d', $value) : null;

        // A date such as 2026-02-31 rolls over to March, so compare it back to what was typed.
        if ($date === null || $date->toDateString() !== $value) {
            throw new GeneralException("{$label} is not valid.");
        }

        return $date;
    }

    /**
     * Rental rows (new and extension) starting in the period, with garage and user loaded.
     *
     * @return Collection<int, Rental>
     */
    private static function rentalRows(Carbon $start, Carbon $end, ?string $garageType, ?string $userId): Collection
    {
        return Rental::query()
            ->with(['garage', 'user'])
            ->whereDate('start_date', '>=', $start->toDateString())
            ->whereDate('start_date', '<=', $end->toDateString())
            ->when($garageType !== null, fn ($query) => $query
                ->whereHas('garage', fn ($garage) => $garage->where('type', $garageType)))
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->get();
    }

    /**
     * Charging transactions created in the period, with rental, garage and user loaded.
     *
     * @return Collection<int, ChargingTransaction>
     */
    private static function chargingRows(Carbon $start, Carbon $end, ?string $garageType, ?string $userId): Collection
    {
        return ChargingTransaction::query()
            ->with(['rental.garage', 'rental.user'])
            ->whereDate('created_at', '>=', $start->toDateString())
            ->whereDate('created_at', '<=', $end->toDateString())
            ->when($garageType !== null, fn ($query) => $query
                ->whereHas('rental.garage', fn ($garage) => $garage->where('type', $garageType)))
            ->when($userId !== null, fn ($query) => $query
                ->whereHas('rental', fn ($rental) => $rental->where('user_id', $userId)))
            ->get();
    }

    /**
     * Group both sets by a key and build one row per listed key, empty keys included.
     *
     * @param  Collection<int, Rental>  $rentals
     * @param  Collection<int, ChargingTransaction>  $chargings
     * @param  list<int|string>  $keys
     * @param  Closure(Rental): (int|string)  $rentalKey
     * @param  Closure(ChargingTransaction): (int|string)  $chargingKey
     * @param  Closure(int|string): array<string, mixed>  $label
     * @return list<array<string, mixed>>
     */
    private static function rowsPerKey(
        Collection $rentals,
        Collection $chargings,
        array $keys,
        Closure $rentalKey,
        Closure $chargingKey,
        Closure $label,
    ): array {
        $rentalsByKey = $rentals->groupBy($rentalKey);
        $chargingsByKey = $chargings->groupBy($chargingKey);

        return collect($keys)
            ->map(fn (int|string $key): array => [
                ...$label($key),
                ...self::buildRow($rentalsByKey->get($key, collect()), $chargingsByKey->get($key, collect())),
            ])
            ->values()
            ->all();
    }

    /**
     * The numbers of one row. Only status new rows count as rentals, extensions only add income.
     *
     * @param  SupportCollection<int, Rental>  $rentals
     * @param  SupportCollection<int, ChargingTransaction>  $chargings
     * @return array{rentals: int, rent_income: float, charging_income: float, total_income: float}
     */
    private static function buildRow(SupportCollection $rentals, SupportCollection $chargings): array
    {
        $rentIncome = round((float) $rentals->sum(fn (Rental $rental): float => (float) $rental->rent_amount), 2);
        $chargingIncome = round((float) $chargings->sum(fn (ChargingTransaction $charging): float => (float) $charging->amount), 2);

        return [
            'rentals' => $rentals->where('status', Const_Status::NEW)->count(),
            'rent_income' => $rentIncome,
            'charging_income' => $chargingIncome,
            'total_income' => round($rentIncome + $chargingIncome, 2),
        ];
    }
}
