<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class GarageService
{
    private const MAX_NUMBER = 999;

    /**
     * Create a batch of garages of one type. All or nothing: a batch that would pass 999 is rejected.
     *
     * @return Collection<int, Garage>
     *
     * @throws GeneralException
     */
    public static function createBatch(
        string $type,
        int $quantity,
        string|int|float $dailyPrice,
        string|int|float|null $pricePerKwh = null,
    ): Collection {
        if (! in_array($type, Const_Type::GARAGE_TYPES, true)) {
            throw new GeneralException('Garage type is not valid.');
        }

        if ($quantity < 1 || $quantity > self::MAX_NUMBER) {
            throw new GeneralException('Quantity must be between 1 and '.self::MAX_NUMBER.'.');
        }

        // An unsaved garage of this type, only used to ask the model about its type.
        $template = new Garage(['type' => $type]);

        if ($template->isElectric() && ($pricePerKwh === null || $pricePerKwh === '')) {
            throw new GeneralException('Price per kWh is required for electric car garages.');
        }

        $highest = self::highestNumber($type);

        if ($highest + $quantity > self::MAX_NUMBER) {
            $remaining = self::MAX_NUMBER - $highest;
            $label = str_replace('_', ' ', $type);

            throw new GeneralException(
                "Only {$remaining} more {$label} garages can be created (maximum ".self::MAX_NUMBER.').'
            );
        }

        $garages = new Collection;

        for ($i = 0; $i < $quantity; $i++) {
            $garages->push(Garage::create([
                'type' => $type,
                'daily_price' => $dailyPrice,
                'price_per_kwh' => $template->isElectric() ? $pricePerKwh : null,
            ]));
        }

        return $garages;
    }

    /**
     * Preview the codes a batch would get. Never throws for a quantity that does not fit
     * (then `fits` is false and first/last are null).
     *
     * @return array{first: string|null, last: string|null, remaining: int, fits: bool}
     *
     * @throws GeneralException
     */
    public static function nextCodes(string $type, int $quantity): array
    {
        if (! in_array($type, Const_Type::GARAGE_TYPES, true)) {
            throw new GeneralException('Garage type is not valid.');
        }

        $highest = self::highestNumber($type);
        $remaining = self::MAX_NUMBER - $highest;
        $fits = $quantity >= 1 && $quantity <= $remaining;

        if (! $fits) {
            return ['first' => null, 'last' => null, 'remaining' => $remaining, 'fits' => false];
        }

        $prefix = (new Garage(['type' => $type]))->autoNumberPrefix();

        return [
            'first' => $prefix.str_pad((string) ($highest + 1), 3, '0', STR_PAD_LEFT),
            'last' => $prefix.str_pad((string) ($highest + $quantity), 3, '0', STR_PAD_LEFT),
            'remaining' => $remaining,
            'fits' => true,
        ];
    }

    /**
     * The highest running number used so far for a garage type (0 when there is none).
     */
    private static function highestNumber(string $type): int
    {
        $prefix = (new Garage(['type' => $type]))->autoNumberPrefix();
        $lastCode = Garage::query()
            ->where('type', $type)
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        return $lastCode === null ? 0 : (int) substr((string) $lastCode, -3);
    }

    /**
     * The garage types a vehicle type fits.
     *
     * @return list<string>
     */
    public static function typesFor(string $vehicleType): array
    {
        return match ($vehicleType) {
            Const_Type::VEHICLE_BICYCLE, Const_Type::VEHICLE_MOTORCYCLE => [Const_Type::GARAGE_BICYCLE],
            Const_Type::VEHICLE_CAR => [Const_Type::GARAGE_CAR],
            Const_Type::VEHICLE_ELECTRIC_CAR => [Const_Type::GARAGE_CAR, Const_Type::GARAGE_ELECTRIC_CAR],
            default => [],
        };
    }

    /**
     * Garages of one type with no rental row (new or extension) overlapping the date range,
     * ordered by code. Uses the same overlap rule as RentalService::create().
     *
     * @return EloquentCollection<int, Garage>
     *
     * @throws GeneralException
     */
    public static function availableFor(string $type, string|CarbonInterface $start, string|CarbonInterface $end): EloquentCollection
    {
        if (! in_array($type, Const_Type::GARAGE_TYPES, true)) {
            throw new GeneralException('Garage type is not valid.');
        }

        return self::availableForTypes([$type], $start, $end)['garages'];
    }

    /**
     * Free garages of several types in one query, ordered by code, with an optional code search
     * and limit. `total` counts every free garage matching the types and search, ignoring the limit.
     *
     * @param  list<string>  $types
     * @return array{garages: EloquentCollection<int, Garage>, total: int}
     *
     * @throws GeneralException
     */
    public static function availableForTypes(
        array $types,
        string|CarbonInterface $start,
        string|CarbonInterface $end,
        ?string $search = null,
        ?int $limit = null,
    ): array {
        foreach ($types as $type) {
            if (! in_array($type, Const_Type::GARAGE_TYPES, true)) {
                throw new GeneralException('Garage type is not valid.');
            }
        }

        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();

        if ($endDate->lt($startDate)) {
            throw new GeneralException('End date cannot be before start date.');
        }

        $query = Garage::query()
            ->whereIn('type', $types)
            ->tap(fn ($query) => self::whereFree($query, $startDate, $endDate));

        $search = trim((string) $search);

        if ($search !== '') {
            // "!" is the escape character, so "%" and "_" typed by the user match literally.
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search));
            $query->whereRaw("lower(code) like ? escape '!'", ['%'.$escaped.'%']);
        }

        $total = (clone $query)->count();

        $garages = $query->orderBy('code')
            ->when($limit !== null, fn ($query) => $query->limit(max($limit, 0)))
            ->get();

        return ['garages' => $garages, 'total' => $total];
    }

    /**
     * The one rule for "free in this period": no rental row (new or extension) overlaps the dates.
     * Same overlap rule as RentalService::create().
     *
     * @param  Builder<Garage>  $query
     */
    public static function whereFree(Builder $query, string|CarbonInterface $start, string|CarbonInterface $end): void
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();

        $query->whereDoesntHave('rentals', function ($query) use ($startDate, $endDate): void {
            $query->whereDate('start_date', '<=', $endDate->toDateString())
                ->whereDate('end_date', '>=', $startDate->toDateString());
        });
    }
}
