<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Vehicle;

use App\Livewire\Vehicle\Create;
use App\Models\User;
use App\Models\Vehicle;
use App\Utilities\Constants\Const_Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function page(?User $user = null)
    {
        return Livewire::actingAs($user ?? User::factory()->create())->test(Create::class);
    }

    public function test_mount_refuses_admins(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())->test(Create::class)->assertForbidden();
        $this->page()->assertOk()->assertSet('type', Const_Type::VEHICLE_CAR);
    }

    public function test_submit_creates_a_vehicle_for_the_logged_in_tenant_and_goes_to_it(): void
    {
        $tenant = User::factory()->create();

        $this->page($tenant)
            ->set('plate_number', 'B 1234 XYZ')
            ->set('brand', 'Toyota')
            ->set('vehicle_model', 'Avanza')
            ->call('submitDefault')
            ->assertHasNoErrors();

        $vehicle = Vehicle::firstOrFail();
        $this->assertSame($tenant->id, $vehicle->user_id);
        $this->assertSame('Avanza', $vehicle->model);
        $this->assertSame('B 1234 XYZ', $vehicle->plate_number);
    }

    public function test_rules_require_a_plate_except_for_bicycles(): void
    {
        $this->page()->set('brand', 'X')->set('vehicle_model', 'Y')->call('submitDefault')->assertHasErrors(['plate_number' => 'required_unless']);

        $this->page()->set('type', Const_Type::VEHICLE_BICYCLE)->set('brand', 'Polygon')->set('vehicle_model', 'Heist')
            ->call('submitDefault')->assertHasNoErrors();

        $this->assertNull(Vehicle::firstOrFail()->plate_number);
    }

    public function test_rules_require_a_capacity_only_for_electric_cars(): void
    {
        $this->page()->set('type', Const_Type::VEHICLE_ELECTRIC_CAR)->set('plate_number', 'B 1 A')->set('brand', 'Tesla')->set('vehicle_model', 'S')
            ->call('submitDefault')->assertHasErrors(['maximum_capacity_kw' => 'required_if']);

        $this->page()->set('type', Const_Type::VEHICLE_ELECTRIC_CAR)->set('plate_number', 'B 2 A')->set('brand', 'Tesla')->set('vehicle_model', 'S')
            ->set('maximum_capacity_kw', '75.5')->call('submitDefault')->assertHasNoErrors();

        $this->assertSame('75.50', Vehicle::firstOrFail()->maximum_capacity_kw);
    }

    public function test_rules_check_type_plate_and_capacity_values(): void
    {
        $this->page()->set('type', 'boat')->call('submitDefault')->assertHasErrors(['type' => 'in']);

        Vehicle::factory()->create(['plate_number' => 'B 9 ZZ']);
        $this->page()->set('plate_number', 'B 9 ZZ')->set('brand', 'X')->set('vehicle_model', 'Y')->call('submitDefault')->assertHasErrors(['plate_number' => 'unique']);

        foreach (['abc' => 'numeric', '0' => 'min', '10000' => 'max'] as $value => $rule) {
            $this->page()->set('type', Const_Type::VEHICLE_ELECTRIC_CAR)->set('maximum_capacity_kw', $value)->call('submitDefault')->assertHasErrors(['maximum_capacity_kw' => $rule]);
        }
    }
}
