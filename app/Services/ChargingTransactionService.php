<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GeneralException;
use App\Models\ChargingTransaction;
use App\Models\Rental;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;

final class ChargingTransactionService
{
    /**
     * Record one charging session for a running rental of an electric car in an electric garage.
     * charged_kwh = maximum capacity - current capacity, amount = charged_kwh x price per kWh.
     * The money math uses round(..., 2) because the bcmath extension is not a declared requirement.
     *
     * @throws GeneralException
     */
    public static function create(Rental $rental, string|int|float $currentCapacityKw, string $paymentProofPath): ChargingTransaction
    {
        if ($rental->status !== Const_Status::NEW) {
            throw new GeneralException('Charging can only be added to the original rental, not an extension.');
        }

        if (! RentalService::isRunningToday($rental)) {
            throw new GeneralException('The rental is not running today.');
        }

        $garage = $rental->garage;
        $vehicle = $rental->vehicle;

        if (! $garage->isElectric()) {
            throw new GeneralException('Charging is only available in an electric car garage.');
        }

        if ($vehicle->type !== Const_Type::VEHICLE_ELECTRIC_CAR || $vehicle->maximum_capacity_kw === null) {
            throw new GeneralException('Charging is only available for an electric car with a maximum capacity.');
        }

        if ($garage->price_per_kwh === null) {
            throw new GeneralException('The garage has no price per kWh.');
        }

        if ($paymentProofPath === '') {
            throw new GeneralException('Payment proof is required.');
        }

        if (! is_numeric($currentCapacityKw)) {
            throw new GeneralException('Current capacity must be a number.');
        }

        $maximum = (float) $vehicle->maximum_capacity_kw;
        $current = (float) $currentCapacityKw;

        if ($current < 0 || $current > $maximum) {
            throw new GeneralException("Current capacity must be between 0 and {$maximum} kW.");
        }

        $chargedKwh = round($maximum - $current, 2);

        if ($chargedKwh <= 0) {
            throw new GeneralException('Battery is already full.');
        }

        return ChargingTransaction::create([
            'rental_id' => $rental->id,
            'current_capacity_kw' => $current,
            'charged_kwh' => $chargedKwh,
            'price_per_kwh' => $garage->price_per_kwh,
            'amount' => round($chargedKwh * (float) $garage->price_per_kwh, 2),
            'payment_proof_path' => $paymentProofPath,
        ]);
    }
}
