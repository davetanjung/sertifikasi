<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\Constants\Const_Role;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use PHPUnit\Framework\TestCase;

class ConstantsTest extends TestCase
{
    public function test_the_stored_values_match_the_database_contract(): void
    {
        $this->assertSame('admin', Const_Role::ADMIN);
        $this->assertSame('tenant', Const_Role::TENANT);
        $this->assertSame(['pending', 'approved', 'rejected'], [Const_Status::PENDING, Const_Status::APPROVED, Const_Status::REJECTED]);
        $this->assertSame(['new', 'extension'], [Const_Status::NEW, Const_Status::EXTENSION]);
        $this->assertSame(['used', 'available'], [Const_Status::USED, Const_Status::AVAILABLE]);
        $this->assertSame(['daily', 'monthly'], [Const_Type::RENTAL_DAILY, Const_Type::RENTAL_MONTHLY]);
    }

    public function test_the_garage_types_list_holds_each_type_once(): void
    {
        $this->assertSame(
            [Const_Type::GARAGE_BICYCLE, Const_Type::GARAGE_CAR, Const_Type::GARAGE_ELECTRIC_CAR],
            Const_Type::GARAGE_TYPES,
        );
        $this->assertSame(Const_Type::GARAGE_TYPES, array_values(array_unique(Const_Type::GARAGE_TYPES)));
    }

    public function test_a_vehicle_type_has_the_same_value_as_the_garage_type_it_matches(): void
    {
        $this->assertSame(Const_Type::VEHICLE_BICYCLE, Const_Type::GARAGE_BICYCLE);
        $this->assertSame(Const_Type::VEHICLE_CAR, Const_Type::GARAGE_CAR);
        $this->assertSame(Const_Type::VEHICLE_ELECTRIC_CAR, Const_Type::GARAGE_ELECTRIC_CAR);
    }
}
