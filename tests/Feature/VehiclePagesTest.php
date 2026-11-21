<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Vehicle\Create;
use App\Livewire\Vehicle\Index;
use App\Livewire\Vehicle\Show;
use App\Models\User;
use App\Models\Vehicle;
use App\Utilities\Constants\Const_Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehiclePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, mixed>
     */
    private function validCar(array $overrides = []): array
    {
        return array_merge([
            'type' => Const_Type::VEHICLE_CAR,
            'plate_number' => 'B 1234 XYZ',
            'brand' => 'Toyota',
            'vehicle_model' => 'Avanza',
            'maximum_capacity_kw' => null,
        ], $overrides);
    }

    private function fillCreate(array $data)
    {
        $component = Livewire::test(Create::class);

        foreach ($data as $key => $value) {
            $component->set($key, $value);
        }

        return $component;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->get(route('vehicle.index'))->assertRedirect(route('login'));
        $this->get(route('vehicle.create'))->assertRedirect(route('login'));
        $this->get(route('vehicle.show', $vehicle))->assertRedirect(route('login'));
    }

    public function test_a_tenant_sees_only_their_own_vehicles(): void
    {
        $tenant = User::factory()->create();
        $mine = Vehicle::factory()->for($tenant)->create();
        Vehicle::factory()->create();

        Livewire::actingAs($tenant)
            ->test(Index::class)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$mine->id]);
    }

    public function test_an_admin_sees_all_vehicles(): void
    {
        Vehicle::factory()->count(3)->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->assertViewHas('data', fn ($data) => $data->total() === 3);
    }

    public function test_the_index_passes_the_type_options_to_the_view(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->assertViewHas('typeOptions', fn ($options) => array_keys($options) === [
                Const_Type::VEHICLE_BICYCLE,
                Const_Type::VEHICLE_MOTORCYCLE,
                Const_Type::VEHICLE_CAR,
                Const_Type::VEHICLE_ELECTRIC_CAR,
            ]);
    }

    public function test_keyword_search_matches_plate_brand_and_model(): void
    {
        $tenant = User::factory()->create();
        $byPlate = Vehicle::factory()->for($tenant)->create(['plate_number' => 'D 9999 ZZZ']);
        $byBrand = Vehicle::factory()->for($tenant)->create(['brand' => 'Zorbrand']);
        $byModel = Vehicle::factory()->for($tenant)->create(['model' => 'Zormodel']);
        Vehicle::factory()->for($tenant)->create(['plate_number' => 'A 1 AAA', 'brand' => 'Other', 'model' => 'Else']);

        $component = Livewire::actingAs($tenant)->test(Index::class);

        $component->set('keyword', 'D 9999')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$byPlate->id]);
        $component->set('keyword', 'Zorbrand')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$byBrand->id]);
        $component->set('keyword', 'Zormodel')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$byModel->id]);
    }

    public function test_type_filter_limits_the_list(): void
    {
        $tenant = User::factory()->create();
        $bicycle = Vehicle::factory()->for($tenant)->bicycle()->create();
        Vehicle::factory()->for($tenant)->car()->create();

        Livewire::actingAs($tenant)
            ->test(Index::class)
            ->set('type', Const_Type::VEHICLE_BICYCLE)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$bicycle->id]);
    }

    public function test_the_create_page_is_forbidden_for_an_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('vehicle.create'))
            ->assertForbidden();
    }

    public function test_the_create_page_renders_for_a_tenant(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('vehicle.create'))
            ->assertOk()
            ->assertSeeLivewire(Create::class);
    }

    public function test_create_requires_the_basic_fields(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Create::class)
            ->set('type', '')
            ->call('submitDefault')
            ->assertHasErrors([
                'type' => 'required',
                'plate_number',
                'brand' => 'required',
                'vehicle_model' => 'required',
            ]);
    }

    public function test_plate_is_required_for_a_car_but_not_for_a_bicycle(): void
    {
        $this->actingAs(User::factory()->create());

        $this->fillCreate($this->validCar(['plate_number' => null]))
            ->call('submitDefault')
            ->assertHasErrors(['plate_number' => 'required_unless']);

    }

    public function test_plate_is_not_required_for_a_bicycle(): void
    {
        $this->actingAs(User::factory()->create());

        $this->fillCreate($this->validCar(['type' => Const_Type::VEHICLE_BICYCLE, 'plate_number' => null]))
            ->call('submitDefault')
            ->assertHasNoErrors();
    }

    public function test_capacity_is_required_for_an_electric_car(): void
    {
        $this->actingAs(User::factory()->create());

        $this->fillCreate($this->validCar(['type' => Const_Type::VEHICLE_ELECTRIC_CAR]))
            ->call('submitDefault')
            ->assertHasErrors(['maximum_capacity_kw' => 'required_if']);
    }

    public function test_capacity_is_not_required_for_a_car(): void
    {
        $this->actingAs(User::factory()->create());

        $this->fillCreate($this->validCar())
            ->call('submitDefault')
            ->assertHasNoErrors();
    }

    public function test_create_rejects_a_duplicate_plate(): void
    {
        Vehicle::factory()->create(['plate_number' => 'B 1234 XYZ']);
        $this->actingAs(User::factory()->create());

        $this->fillCreate($this->validCar())
            ->call('submitDefault')
            ->assertHasErrors(['plate_number' => 'unique']);
    }

    public function test_create_rejects_an_invalid_type(): void
    {
        $this->actingAs(User::factory()->create());

        $this->fillCreate($this->validCar(['type' => 'truck']))
            ->call('submitDefault')
            ->assertHasErrors(['type' => 'in']);
    }

    public function test_a_valid_create_saves_the_vehicle_for_the_logged_in_tenant(): void
    {
        $tenant = User::factory()->create();
        $this->actingAs($tenant);

        $this->fillCreate($this->validCar())
            ->call('submitDefault')
            ->assertHasNoErrors();

        $vehicle = Vehicle::where('plate_number', 'B 1234 XYZ')->firstOrFail();
        $this->assertSame($tenant->id, $vehicle->user_id);
        $this->assertSame('Avanza', $vehicle->model);
        $this->assertSame('Toyota', $vehicle->brand);
    }

    public function test_a_valid_create_redirects_to_the_show_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->fillCreate($this->validCar())->call('submitDefault');

        $this->assertSame(1, Vehicle::count());
        $this->assertTrue(session()->has('flash_success'));
        $this->fillCreate($this->validCar(['plate_number' => 'B 7777 AAA']))
            ->call('submitDefault')
            ->assertRedirect(route('vehicle.show', Vehicle::where('plate_number', 'B 7777 AAA')->firstOrFail()));
    }

    public function test_a_bicycle_is_saved_without_a_plate(): void
    {
        $this->actingAs(User::factory()->create());

        $this->fillCreate($this->validCar([
            'type' => Const_Type::VEHICLE_BICYCLE,
            'plate_number' => 'B 5555 AAA',
        ]))->call('submitDefault')->assertHasNoErrors();

        $this->assertNull(Vehicle::firstOrFail()->plate_number);
    }

    public function test_a_non_electric_car_is_saved_without_capacity(): void
    {
        $this->actingAs(User::factory()->create());

        $this->fillCreate($this->validCar(['maximum_capacity_kw' => '12.5']))
            ->call('submitDefault')
            ->assertHasNoErrors();

        $this->assertNull(Vehicle::firstOrFail()->maximum_capacity_kw);
    }

    public function test_an_electric_car_keeps_its_capacity(): void
    {
        $this->actingAs(User::factory()->create());

        $this->fillCreate($this->validCar([
            'type' => Const_Type::VEHICLE_ELECTRIC_CAR,
            'maximum_capacity_kw' => '12.5',
        ]))->call('submitDefault')->assertHasNoErrors();

        $this->assertSame('12.50', Vehicle::firstOrFail()->maximum_capacity_kw);
    }

    public function test_the_owner_can_view_their_vehicle(): void
    {
        $tenant = User::factory()->create();
        $vehicle = Vehicle::factory()->for($tenant)->create();

        $this->actingAs($tenant)
            ->get(route('vehicle.show', $vehicle))
            ->assertOk()
            ->assertSeeLivewire(Show::class);
    }

    public function test_another_tenant_cannot_view_the_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('vehicle.show', $vehicle))
            ->assertForbidden();
    }

    public function test_an_admin_can_view_any_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Show::class, ['obj' => $vehicle])
            ->assertOk()
            ->assertViewHas('rentals', fn ($rentals) => $rentals->isEmpty());
    }
}
