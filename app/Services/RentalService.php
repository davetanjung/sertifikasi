<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class RentalService
{
    /**
     * The monthly price is always this many times the daily price, whatever the month length.
     */
    private const MONTHLY_PRICE_FACTOR = 25;

    /**
     * Create a new rental (status new). Payment is upfront, so the proof is required.
     *
     * Keys: user_id, vehicle_id, garage_id, rental_type, start_date (optional, default today),
     * days (daily only), payment_proof_path.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws GeneralException
     */
    public static function create(array $data): Rental
    {
        return DB::transaction(function () use ($data): Rental {
            $garage = Garage::query()->lockForUpdate()->find($data['garage_id'] ?? null);
            $vehicle = Vehicle::query()->lockForUpdate()->find($data['vehicle_id'] ?? null);

            return self::createRental($data, $vehicle, $garage);
        });
    }

    /**
     * Work out the dates, days and price of a new rental without saving anything. No vehicle, tenant,
     * payment proof or availability is needed, so it does not check them.
     *
     * Keys: garage_id, rental_type, start_date (optional, default today), days (daily only).
     *
     * @param  array<string, mixed>  $data
     * @return array{start_date: string, end_date: string, days: int, daily_price: float, rent_amount: float, rental_type: string}
     *
     * @throws GeneralException
     */
    public static function preview(array $data): array
    {
        $garage = Garage::query()->find($data['garage_id'] ?? null);

        if ($garage === null) {
            throw new GeneralException('Garage was not found.');
        }

        $terms = self::calculateTerms($garage, $data);

        return [
            'start_date' => $terms['start']->toDateString(),
            'end_date' => $terms['end']->toDateString(),
            'days' => $terms['days'],
            'daily_price' => (float) $garage->daily_price,
            'rent_amount' => $terms['rent_amount'],
            'rental_type' => $terms['rental_type'],
        ];
    }

    /**
     * The rentals running today, for the admin extend list: one entry per rental, as the new row,
     * with garage, vehicle and user loaded, ordered by garage code. Extension rows count as running.
     *
     * @return Collection<int, Rental>
     */
    public static function activeRentals(): Collection
    {
        $today = today()->toDateString();

        return Rental::query()
            ->where('status', Const_Status::NEW)
            ->whereExists(function ($query) use ($today): void {
                $query->selectRaw('1')
                    ->from('rentals as same_rental')
                    ->whereColumn('same_rental.code', 'rentals.code')
                    ->whereColumn('same_rental.user_id', 'rentals.user_id')
                    ->whereColumn('same_rental.garage_id', 'rentals.garage_id')
                    ->whereDate('same_rental.start_date', '<=', $today)
                    ->whereDate('same_rental.end_date', '>=', $today);
            })
            ->with(['garage', 'vehicle', 'user'])
            ->get()
            ->sortBy(fn (Rental $rental) => $rental->garage->code)
            ->values();
    }

    /**
     * The date, days and price rules shared by create() and preview(). When a vehicle is given,
     * it must also fit the garage type.
     *
     * @param  array<string, mixed>  $data
     * @return array{start: Carbon, end: Carbon, days: int, rent_amount: float, rental_type: string}
     *
     * @throws GeneralException
     */
    private static function calculateTerms(Garage $garage, array $data, ?Vehicle $vehicle = null): array
    {
        $startDate = isset($data['start_date']) && $data['start_date'] !== ''
            ? Carbon::parse($data['start_date'])->startOfDay()
            : today();

        if ($startDate->lt(today())) {
            throw new GeneralException('Start date cannot be before today.');
        }

        if ($vehicle !== null && ! in_array($garage->type, GarageService::typesFor($vehicle->type), true)) {
            throw new GeneralException('This vehicle does not fit that garage type.');
        }

        $rentalType = $data['rental_type'] ?? null;
        $daysInMonth = $startDate->daysInMonth;
        $dailyPrice = (float) $garage->daily_price;

        if ($rentalType === Const_Type::RENTAL_DAILY) {
            $days = $data['days'] ?? null;

            if ($days === null || $days === '' || ! is_numeric($days)) {
                throw new GeneralException('Number of days is required for a daily rental.');
            }

            $days = (int) $days;
            $maximumDays = $daysInMonth - 1;

            if ($days < 1 || $days > $maximumDays) {
                throw new GeneralException("Days must be between 1 and {$maximumDays} for this month.");
            }

            $rentAmount = round($dailyPrice * $days, 2);
        } elseif ($rentalType === Const_Type::RENTAL_MONTHLY) {
            $days = $daysInMonth;
            $rentAmount = round($dailyPrice * self::MONTHLY_PRICE_FACTOR, 2);
        } else {
            throw new GeneralException('Rental type is not valid.');
        }

        return [
            'start' => $startDate,
            'end' => $startDate->copy()->addDays($days - 1),
            'days' => $days,
            'rent_amount' => $rentAmount,
            'rental_type' => $rentalType,
        ];
    }

    /**
     * The checks and the insert of create(), run while the garage and vehicle rows are locked.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws GeneralException
     */
    private static function createRental(array $data, ?Vehicle $vehicle, ?Garage $garage): Rental
    {
        if ($vehicle === null) {
            throw new GeneralException('Vehicle was not found.');
        }

        if ($garage === null) {
            throw new GeneralException('Garage was not found.');
        }

        if ($vehicle->user_id !== ($data['user_id'] ?? null)) {
            throw new GeneralException('The vehicle does not belong to this tenant.');
        }

        if (empty($data['payment_proof_path'])) {
            throw new GeneralException('Payment proof is required.');
        }

        $terms = self::calculateTerms($garage, $data, $vehicle);
        $startDate = $terms['start'];
        $endDate = $terms['end'];
        $days = $terms['days'];
        $rentAmount = $terms['rent_amount'];
        $rentalType = $terms['rental_type'];

        $conflict = self::overlappingRentals($garage->id, $startDate, $endDate)->first();

        if ($conflict !== null) {
            throw new GeneralException('Garage is not available for those dates.');
        }

        if (self::vehicleIsRented($vehicle->id, $startDate, $endDate)) {
            throw new GeneralException('This vehicle is already rented for those dates.');
        }

        return Rental::create([
            'user_id' => $vehicle->user_id,
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'status' => Const_Status::NEW,
            'rental_type' => $rentalType,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days' => $days,
            'daily_price' => $garage->daily_price,
            'rent_amount' => $rentAmount,
            'payment_proof_path' => $data['payment_proof_path'],
        ]);
    }

    /**
     * Extend a rental to a later end date (admin action). Any row of the rental may be passed.
     * The extension is a new row with status extension and the same code. Its cost is the added days
     * times the original daily price, for daily and monthly rentals alike.
     *
     * @throws GeneralException
     */
    public static function extend(Rental $rental, string|CarbonInterface $newEndDate, string $paymentProofPath): Rental
    {
        return DB::transaction(function () use ($rental, $newEndDate, $paymentProofPath): Rental {
            // Same locks as create(), garage first, so an extension and a new booking cannot collide.
            Garage::query()->lockForUpdate()->find($rental->garage_id);
            Vehicle::query()->lockForUpdate()->find($rental->vehicle_id);

            return self::extendLocked($rental, $newEndDate, $paymentProofPath);
        });
    }

    /**
     * The checks and the insert of extend(), run while the garage and vehicle rows are locked.
     *
     * @throws GeneralException
     */
    private static function extendLocked(Rental $rental, string|CarbonInterface $newEndDate, string $paymentProofPath): Rental
    {
        if ($paymentProofPath === '') {
            throw new GeneralException('Payment proof is required.');
        }

        $rows = self::rowsOf($rental);
        $currentEnd = self::latestEndDate($rental)->copy()->startOfDay();
        $newEnd = Carbon::parse($newEndDate)->startOfDay();

        if ($currentEnd->lt(today())) {
            throw new GeneralException('The rental has already ended and cannot be extended.');
        }

        if (! $newEnd->gt($currentEnd)) {
            throw new GeneralException('The new end date must be after the current end date.');
        }

        $extensionStart = $currentEnd->copy()->addDay();

        $conflict = self::overlappingRentals($rental->garage_id, $extensionStart, $newEnd, $rental->code)->first();

        if ($conflict !== null) {
            throw new GeneralException('Garage is booked from '.$conflict->start_date->toDateString().'.');
        }

        $original = $rows->firstWhere('status', Const_Status::NEW) ?? $rental;

        if (self::vehicleIsRented($original->vehicle_id, $extensionStart, $newEnd, $rental->code)) {
            throw new GeneralException('This vehicle is already rented for those dates.');
        }

        $addedDays = (int) $extensionStart->diffInDays($newEnd) + 1;

        return Rental::create([
            'user_id' => $original->user_id,
            'garage_id' => $original->garage_id,
            'vehicle_id' => $original->vehicle_id,
            'code' => $original->code,
            'status' => Const_Status::EXTENSION,
            'rental_type' => $original->rental_type,
            'start_date' => $extensionStart->toDateString(),
            'end_date' => $newEnd->toDateString(),
            'days' => $addedDays,
            'daily_price' => $original->daily_price,
            'rent_amount' => round((float) $original->daily_price * $addedDays, 2),
            'payment_proof_path' => $paymentProofPath,
        ]);
    }

    /**
     * The latest end date among the rows of this rental (same code, tenant and garage).
     */
    public static function latestEndDate(Rental $rental): CarbonInterface
    {
        /** @var Rental $latest */
        $latest = self::rowsOf($rental)->sortByDesc(fn (Rental $row) => $row->end_date->timestamp)->first();

        return $latest->end_date;
    }

    /**
     * Whether today falls between the first start date and the latest end date of this rental.
     * Extension rows are part of the span, so a rental is still running during its extension.
     */
    public static function isActive(Rental $rental): bool
    {
        /** @var Rental $first */
        $first = self::rowsOf($rental)->sortBy(fn (Rental $row) => $row->start_date->timestamp)->first();

        return $first->start_date->lte(today()) && self::latestEndDate($rental)->gte(today());
    }

    /**
     * All rows of one rental: the new row plus its extensions.
     *
     * @return Collection<int, Rental>
     */
    private static function rowsOf(Rental $rental): Collection
    {
        $rows = Rental::query()
            ->where('code', $rental->code)
            ->where('user_id', $rental->user_id)
            ->where('garage_id', $rental->garage_id)
            ->get();

        return $rows->isEmpty() ? new Collection([$rental]) : $rows;
    }

    /**
     * Rows of a garage that overlap the date range, earliest first. Optionally skips one code.
     *
     * @return Collection<int, Rental>
     */
    private static function overlappingRentals(
        string $garageId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $exceptCode = null,
    ): Collection {
        return Rental::query()
            ->where('garage_id', $garageId)
            ->when($exceptCode !== null, fn ($query) => $query->where('code', '!=', $exceptCode))
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Whether the vehicle has a rental row (new or extension) overlapping the date range, so one
     * vehicle cannot be in two garages at once. Optionally skips one code (the rental being extended).
     */
    private static function vehicleIsRented(
        string $vehicleId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $exceptCode = null,
    ): bool {
        return Rental::query()
            ->where('vehicle_id', $vehicleId)
            ->when($exceptCode !== null, fn ($query) => $query->where('code', '!=', $exceptCode))
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();
    }
}
