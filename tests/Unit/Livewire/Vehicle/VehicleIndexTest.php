<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Vehicle;

use App\Livewire\Vehicle\Index;
use App\Models\User;
use App\Models\Vehicle;
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

    public function test_a_tenant_sees_only_their_own_vehicles(): void
    {
        $tenant = User::factory()->create();
        $mine = Vehicle::factory()->for($tenant)->create();
        Vehicle::factory()->create();

        $this->page($tenant)->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$mine->id]);
    }
}
