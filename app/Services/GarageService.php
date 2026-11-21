<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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

        $prefix = $template->autoNumberPrefix();
        $lastCode = Garage::query()
            ->where('type', $type)
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');
        $highest = $lastCode === null ? 0 : (int) substr((string) $lastCode, -3);

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

        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();

        if ($endDate->lt($startDate)) {
            throw new GeneralException('End date cannot be before start date.');
        }

        return Garage::query()
            ->where('type', $type)
            ->whereDoesntHave('rentals', function ($query) use ($startDate, $endDate): void {
                $query->whereDate('start_date', '<=', $endDate->toDateString())
                    ->whereDate('end_date', '>=', $startDate->toDateString());
            })
            ->orderBy('code')
            ->get();
    }
}
