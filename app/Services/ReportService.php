<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GeneralException;
use App\Models\ChargingTransaction;
use App\Models\Rental;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
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

    /**
     * Twelve rows, January to December, months without activity included.
     *
     * @return list<array{month: int, rentals: int, rent_income: float, charging_income: float, total_income: float}>
     *
     * @throws GeneralException
     */
    public static function monthly(int $year, ?string $garageType = null, ?string $userId = null): array
    {
        self::validate($year, null, $garageType);

        [$start, $end] = self::period($year, null);

        return self::rowsPerKey(
            self::rentalRows($start, $end, $garageType, $userId),
            self::chargingRows($start, $end, $garageType, $userId),
            range(1, 12),
            fn (Rental $rental): int => $rental->start_date->month,
            fn (ChargingTransaction $charging): int => $charging->created_at->month,
            fn (int $month): array => ['month' => $month],
        );
    }

    /**
     * One row per day of the month, days without activity included.
     *
     * @return list<array{day: int, date: string, rentals: int, rent_income: float, charging_income: float, total_income: float}>
     *
     * @throws GeneralException
     */
    public static function daily(int $year, int $month, ?string $garageType = null, ?string $userId = null): array
    {
        self::validate($year, $month, $garageType);

        [$start, $end] = self::period($year, $month);

        return self::rowsPerKey(
            self::rentalRows($start, $end, $garageType, $userId),
            self::chargingRows($start, $end, $garageType, $userId),
            range(1, $start->daysInMonth),
            fn (Rental $rental): int => $rental->start_date->day,
            fn (ChargingTransaction $charging): int => $charging->created_at->day,
            fn (int $day): array => ['day' => $day, 'date' => $start->copy()->day($day)->toDateString()],
        );
    }

    /**
     * One row per garage type, all three always present. Year and month are optional filters.
     *
     * @return list<array{garage_type: string, rentals: int, rent_income: float, charging_income: float, total_income: float}>
     *
     * @throws GeneralException
     */
    public static function byGarageType(?int $year = null, ?int $month = null, ?string $userId = null): array
    {
        self::validate($year, $month, null);

        [$start, $end] = self::period($year, $month);

        return self::rowsPerKey(
            self::rentalRows($start, $end, null, $userId),
            self::chargingRows($start, $end, null, $userId),
            Const_Type::GARAGE_TYPES,
            fn (Rental $rental): string => $rental->garage->type,
            fn (ChargingTransaction $charging): string => $charging->rental->garage->type,
            fn (string $type): array => ['garage_type' => $type],
        );
    }

    /**
     * One row per tenant with activity, highest total income first, then by name.
     *
     * @return list<array{user_id: string, name: string, email: string, rentals: int, rent_income: float, charging_income: float, total_income: float}>
     *
     * @throws GeneralException
     */
    public static function byTenant(?int $year = null, ?int $month = null, ?string $garageType = null): array
    {
        self::validate($year, $month, $garageType);

        [$start, $end] = self::period($year, $month);

        $rentals = self::rentalRows($start, $end, $garageType, null);
        $chargings = self::chargingRows($start, $end, $garageType, null);

        $tenants = $rentals->map(fn (Rental $rental) => $rental->user)
            ->concat($chargings->map(fn (ChargingTransaction $charging) => $charging->rental->user))
            ->unique('id');

        $rentalsByTenant = $rentals->groupBy('user_id');
        $chargingsByTenant = $chargings->groupBy(fn (ChargingTransaction $charging): string => $charging->rental->user_id);

        return $tenants
            ->map(fn ($tenant): array => [
                'user_id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                ...self::buildRow(
                    $rentalsByTenant->get($tenant->id, collect()),
                    $chargingsByTenant->get($tenant->id, collect()),
                ),
            ])
            ->sort(fn (array $a, array $b): int => [$b['total_income'], $a['name']] <=> [$a['total_income'], $b['name']])
            ->values()
            ->all();
    }

    /**
     * Reject filter values that make no sense.
     *
     * @throws GeneralException
     */
    private static function validate(?int $year, ?int $month, ?string $garageType): void
    {
        if ($month !== null && $year === null) {
            throw new GeneralException('Month needs a year.');
        }

        if ($year !== null && ($year < self::MINIMUM_YEAR || $year > self::MAXIMUM_YEAR)) {
            throw new GeneralException('Year is not valid.');
        }

        if ($month !== null && ($month < 1 || $month > 12)) {
            throw new GeneralException('Month must be between 1 and 12.');
        }

        if ($garageType !== null && ! in_array($garageType, Const_Type::GARAGE_TYPES, true)) {
            throw new GeneralException('Garage type is not valid.');
        }
    }

    /**
     * First and last day of the period, or null bounds when there is no year (no date filter).
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private static function period(?int $year, ?int $month): array
    {
        if ($year === null) {
            return [null, null];
        }

        $start = Carbon::create($year, $month ?? 1, 1)->startOfDay();
        $end = $month === null ? $start->copy()->endOfYear() : $start->copy()->endOfMonth();

        return [$start, $end];
    }

    /**
     * Rental rows (new and extension) starting in the period, with garage and user loaded.
     *
     * @return Collection<int, Rental>
     */
    private static function rentalRows(?Carbon $start, ?Carbon $end, ?string $garageType, ?string $userId): Collection
    {
        return Rental::query()
            ->with(['garage', 'user'])
            ->when($start !== null, fn ($query) => $query
                ->whereDate('start_date', '>=', $start->toDateString())
                ->whereDate('start_date', '<=', $end->toDateString()))
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
    private static function chargingRows(?Carbon $start, ?Carbon $end, ?string $garageType, ?string $userId): Collection
    {
        return ChargingTransaction::query()
            ->with(['rental.garage', 'rental.user'])
            ->when($start !== null, fn ($query) => $query
                ->whereDate('created_at', '>=', $start->toDateString())
                ->whereDate('created_at', '<=', $end->toDateString()))
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
