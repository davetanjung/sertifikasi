<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vehicle;
use App\Utilities\Constants\Const_Type;

final class VehicleService
{
    /**
     * Create a vehicle. A bicycle has no plate, and only an electric car keeps its capacity.
     *
     * @param  array<string, mixed>  $data
     */
    public static function create(array $data = []): Vehicle
    {
        $type = $data['type'] ?? null;

        if ($type === Const_Type::VEHICLE_BICYCLE) {
            $data['plate_number'] = null;
        }

        if ($type !== Const_Type::VEHICLE_ELECTRIC_CAR) {
            $data['maximum_capacity_kw'] = null;
        }

        return Vehicle::create($data);
    }
}
