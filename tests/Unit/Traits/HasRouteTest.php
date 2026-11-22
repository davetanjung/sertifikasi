<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HasRouteTest extends TestCase
{
    public function test_route_prefix_comes_from_the_model(): void
    {
        $this->assertSame('garage', Garage::routePrefix());
        $this->assertSame('vehicle', Vehicle::routePrefix());
        $this->assertSame('user', User::routePrefix());
        $this->assertSame('charging-transaction', ChargingTransaction::routePrefix());
        $this->assertSame('garage', (new Garage)->getRoutePrefix());
    }

    public function test_index_and_create_routes_resolve_to_named_routes(): void
    {
        $this->assertSame(route('garage.index'), Garage::routeIndex());
        $this->assertSame(route('garage.create'), Garage::routeCreate());
        $this->assertSame(route('garage.index'), (new Garage)->getRouteIndex());
        $this->assertSame(route('garage.create'), (new Garage)->getRouteCreate());
    }

    public function test_show_route_uses_the_record_id(): void
    {
        $garage = new Garage;
        $garage->id = 'abc-123';

        $this->assertSame(route('garage.show', 'abc-123'), Garage::routeShow($garage));
        $this->assertSame(route('garage.show', 'abc-123'), $garage->getRouteShow());
    }

    public function test_routes_that_do_not_exist_give_a_hash(): void
    {
        $garage = new Garage;
        $garage->id = 'abc-123';

        $this->assertSame('#', Garage::routeEdit($garage));
        $this->assertSame('#', $garage->getRouteEdit());
        $this->assertSame('#', Garage::routeImport());
        $this->assertSame('#', (new Garage)->getRouteImport());
        $this->assertSame('#', User::routeCreate());
    }

    public function test_permission_names_are_the_prefix_plus_the_action(): void
    {
        $this->assertSame('garage', Garage::permissionPrefix());
        $this->assertSame('garage.index', Garage::permissionIndex());
        $this->assertSame('garage.create', Garage::permissionCreate());
        $this->assertSame('garage.show', Garage::permissionShow());
        $this->assertSame('garage.edit', Garage::permissionEdit());
        $this->assertSame('garage.delete', Garage::permissionDelete());
        $this->assertSame('garage.activity-log', Garage::permissionActivityLog());
    }

    public function test_instance_permission_getters_match_the_static_ones(): void
    {
        $garage = new Garage;

        $this->assertSame('garage', $garage->getPermissionPrefix());
        $this->assertSame('garage.index', $garage->getPermissionIndex());
        $this->assertSame('garage.create', $garage->getPermissionCreate());
        $this->assertSame('garage.show', $garage->getPermissionShow());
        $this->assertSame('garage.edit', $garage->getPermissionEdit());
        $this->assertSame('garage.delete', $garage->getPermissionDelete());
        $this->assertSame('garage.activity-log', $garage->getPermissionActivityLog());
    }

    public function test_links_are_hidden_when_permissions_are_on_and_the_user_lacks_them(): void
    {
        config(['app.check_permissions' => true]);

        $this->assertSame('#', Garage::routeIndex());

        $this->actingAs(User::factory()->make());
        $this->assertSame('#', Garage::routeIndex());

        Gate::define('garage.index', fn () => true);

        $this->assertSame(route('garage.index'), Garage::routeIndex());
        $this->assertSame('#', Garage::routeCreate());
    }

    public function test_get_from_current_route_is_null_without_a_route(): void
    {
        $this->assertNull((new Garage)->getFromCurrentRoute());
    }

    public function test_get_from_current_route_returns_the_name_without_the_last_part(): void
    {
        Route::get('/unit-probe', fn () => 'ok')->name('garage.probe');

        $this->get('/unit-probe')->assertOk();

        $this->assertSame('garage', (new Garage)->getFromCurrentRoute());
    }
}
