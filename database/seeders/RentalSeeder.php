<?php

namespace Database\Seeders;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ChargingTransactionService;
use App\Services\RentalService;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RentalSeeder extends Seeder
{
    private const PROOF = 'payment-proofs/demo.jpg';

    /**
     * Demo rentals made through the services, with fixed combinations so every call succeeds.
     * Past rentals are made by moving the clock to their start date (the service refuses a start
     * date before today). The clock is always restored.
     */
    public function run(): void
    {
        $today = today();
        $firstOfMonth = $today->copy()->startOfMonth();

        try {
            // Finished rentals in the previous three months, for the reports.
            $threeMonthsAgo = $firstOfMonth->copy()->subMonthsNoOverflow(3);
            $twoMonthsAgo = $firstOfMonth->copy()->subMonthsNoOverflow(2);
            $lastMonth = $firstOfMonth->copy()->subMonthsNoOverflow(1);

            $this->rent($threeMonthsAgo->copy()->addDays(3), 'C001', 5, Const_Type::VEHICLE_CAR, Const_Type::RENTAL_DAILY, 5);
            $this->rent($threeMonthsAgo->copy()->addDays(10), 'S001', 1, Const_Type::VEHICLE_BICYCLE, Const_Type::RENTAL_MONTHLY);
            $this->rent($twoMonthsAgo->copy()->addDays(1), 'E001', 1, Const_Type::VEHICLE_ELECTRIC_CAR, Const_Type::RENTAL_DAILY, 7);
            $this->rent($twoMonthsAgo->copy()->addDays(11), 'C002', 6, Const_Type::VEHICLE_CAR, Const_Type::RENTAL_DAILY, 10);
            $this->rent($lastMonth->copy()->addDays(3), 'E002', 2, Const_Type::VEHICLE_ELECTRIC_CAR, Const_Type::RENTAL_DAILY, 10);
            $this->rent($lastMonth->copy()->addDays(7), 'C003', 7, Const_Type::VEHICLE_CAR, Const_Type::RENTAL_DAILY, 4);
            $this->rent($lastMonth->copy()->addDays(14), 'S002', 3, Const_Type::VEHICLE_MOTORCYCLE, Const_Type::RENTAL_DAILY, 5);

            // Rentals running today.
            $electricRental = $this->rent($today->copy()->subDays(5), 'E003', 3, Const_Type::VEHICLE_ELECTRIC_CAR, Const_Type::RENTAL_DAILY, 10);
            $monthlyRental = $this->rent($today->copy()->subDays(3), 'C004', 5, Const_Type::VEHICLE_CAR, Const_Type::RENTAL_MONTHLY);
            $chargingRental = $this->rent($today->copy()->subDays(2), 'E001', 1, Const_Type::VEHICLE_ELECTRIC_CAR, Const_Type::RENTAL_DAILY, 6);
            $this->rent($today->copy()->subDays(1), 'S003', 2, Const_Type::VEHICLE_BICYCLE, Const_Type::RENTAL_DAILY, 3);

            // Bookings that start in the next days.
            $this->rent($today->copy()->addDays(3), 'C001', 6, Const_Type::VEHICLE_CAR, Const_Type::RENTAL_DAILY, 4);
            $this->rent($today->copy()->addDays(6), 'S001', 1, Const_Type::VEHICLE_BICYCLE, Const_Type::RENTAL_DAILY, 7);
            $this->rent($today->copy()->addDays(2), 'E002', 2, Const_Type::VEHICLE_ELECTRIC_CAR, Const_Type::RENTAL_MONTHLY);
        } finally {
            Carbon::setTestNow();
        }

        // Extensions (admin action) on two running rentals: 4 days and 5 days more.
        RentalService::extend($electricRental, $electricRental->end_date->copy()->addDays(4), self::PROOF);
        RentalService::extend($monthlyRental, $monthlyRental->end_date->copy()->addDays(5), self::PROOF);

        // Charging sessions on running electric rentals (both cars have a 12 kW maximum).
        ChargingTransactionService::create($electricRental, 2, self::PROOF);
        ChargingTransactionService::create($electricRental, 4, self::PROOF);
        ChargingTransactionService::create($chargingRental, 3, self::PROOF);
        ChargingTransactionService::create($chargingRental, 5, self::PROOF);
    }

    /**
     * Rent one garage for the vehicle of a tenant, with the clock moved to the start date.
     */
    private function rent(
        Carbon $startDate,
        string $garageCode,
        int $tenantNumber,
        string $vehicleType,
        string $rentalType,
        ?int $days = null,
    ): Rental {
        $tenant = User::where('email', "tenant{$tenantNumber}@test.com")->firstOrFail();
        $vehicle = Vehicle::where('user_id', $tenant->id)->where('type', $vehicleType)->firstOrFail();
        $garage = Garage::where('code', $garageCode)->firstOrFail();

        Carbon::setTestNow($startDate->copy()->setTime(9, 0));

        try {
            return RentalService::create([
                'user_id' => $tenant->id,
                'vehicle_id' => $vehicle->id,
                'garage_id' => $garage->id,
                'rental_type' => $rentalType,
                'start_date' => $startDate->toDateString(),
                'days' => $days,
                'payment_proof_path' => self::PROOF,
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }
}
