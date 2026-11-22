<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HasCoreFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_table_name_returns_the_table_without_keeping_an_instance(): void
    {
        $this->assertSame('garages', Garage::getTableName());
        $this->assertSame('users', User::getTableName());
        $this->assertSame('vehicles', Vehicle::getTableName());
        $this->assertSame('rentals', Rental::getTableName());
        $this->assertSame('charging_transactions', ChargingTransaction::getTableName());
    }

    public function test_every_model_gets_a_uuid_id_and_a_working_factory(): void
    {
        foreach ([Garage::class, User::class, Vehicle::class, Rental::class, ChargingTransaction::class] as $model) {
            $this->assertTrue(Str::isUuid($model::factory()->create()->id), $model);
        }
    }

    public function test_every_model_has_the_keyword_search_scope(): void
    {
        $garage = Garage::factory()->car()->create(['code' => 'C001']);

        $this->assertSame([$garage->id], Garage::query()->keywordSearch('C001', ['code'])->pluck('id')->all());
    }

    public function test_every_model_has_route_helpers(): void
    {
        $this->assertSame(route('garage.index'), Garage::routeIndex());
    }
}
