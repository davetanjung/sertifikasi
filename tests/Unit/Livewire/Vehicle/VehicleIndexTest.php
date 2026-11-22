<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Vehicle;

use App\Livewire\Vehicle\Index;
use App\Models\User;
use App\Models\Vehicle;
use App\Utilities\Constants\Const_Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function page(User $user)
    {
        return Livewire::actingAs($user)->test(Index::class);
    }

    public function test_mount_sets_the_default_sort(): void
    {
        $this->page(User::factory()->create())->assertSet('sortField', 'created_at');
    }

    public function test_render_gives_the_type_options(): void
    {
        $this->page(User::factory()->create())->assertViewHas('typeOptions', fn ($options) => array_keys($options) === [
            Const_Type::VEHICLE_BICYCLE,
            Const_Type::VEHICLE_MOTORCYCLE,
            Const_Type::VEHICLE_CAR,
            Const_Type::VEHICLE_ELECTRIC_CAR,
        ]);
    }

    public function test_a_tenant_sees_only_their_own_vehicles(): void
    {
        $tenant = User::factory()->create();
        $mine = Vehicle::factory()->for($tenant)->create();
        Vehicle::factory()->create();

        $this->page($tenant)->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$mine->id]);
    }

    public function test_an_admin_sees_every_vehicle_with_its_owner_loaded(): void
    {
        Vehicle::factory()->count(2)->create();

        $this->page(User::factory()->admin()->create())
            ->assertViewHas('data', fn ($data) => $data->count() === 2 && $data->first()->relationLoaded('user'));
    }

    public function test_get_query_filters_by_keyword_in_plate_brand_and_model(): void
    {
        $tenant = User::factory()->create();
        $toyota = Vehicle::factory()->for($tenant)->create(['plate_number' => 'B 1111 AA', 'brand' => 'Toyota', 'model' => 'Avanza']);
        $honda = Vehicle::factory()->for($tenant)->create(['plate_number' => 'D 2222 BB', 'brand' => 'Honda', 'model' => 'Jazz']);

        foreach (['1111' => $toyota, 'honda' => $honda, 'avanza' => $toyota] as $keyword => $expected) {
            $this->page($tenant)->set('keyword', (string) $keyword)
                ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$expected->id]);
        }
    }

    public function test_get_query_filters_by_type(): void
    {
        $tenant = User::factory()->create();
        $bicycle = Vehicle::factory()->bicycle()->for($tenant)->create();
        Vehicle::factory()->car()->for($tenant)->create();

        $this->page($tenant)->set('type', Const_Type::VEHICLE_BICYCLE)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$bicycle->id]);
    }

    public function test_sort_changes_the_order(): void
    {
        $tenant = User::factory()->create();
        $b = Vehicle::factory()->for($tenant)->create(['brand' => 'B-brand']);
        $a = Vehicle::factory()->for($tenant)->create(['brand' => 'A-brand']);

        $this->page($tenant)->call('sort', 'brand')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$a->id, $b->id]);
    }
}
