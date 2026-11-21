<?php

declare(strict_types=1);

namespace App\Utilities\Constants;

final class Const_Type
{
    // vehicle
    public const VEHICLE_BICYCLE = 'bicycle';

    public const VEHICLE_MOTORCYCLE = 'motorcycle';

    public const VEHICLE_CAR = 'car';

    public const VEHICLE_ELECTRIC_CAR = 'electric_car';

    // garage
    public const GARAGE_BICYCLE = 'bicycle';

    public const GARAGE_CAR = 'car';

    public const GARAGE_ELECTRIC_CAR = 'electric_car';

    /**
     * All valid garage types.
     *
     * @var list<string>
     */
    public const GARAGE_TYPES = [
        self::GARAGE_BICYCLE,
        self::GARAGE_CAR,
        self::GARAGE_ELECTRIC_CAR,
    ];

    // rental type
    public const RENTAL_DAILY = 'daily';

    public const RENTAL_MONTHLY = 'monthly';
}
