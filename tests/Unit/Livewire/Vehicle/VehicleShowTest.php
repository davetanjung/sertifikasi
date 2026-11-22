<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Vehicle;

use App\Livewire\Vehicle\Show;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_mount_allows_the_owner_and_admins_but_not_other_tenants(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();

        Livewire::actingAs($owner)->test(Show::class, ['obj' => $vehicle])->assertOk();
        Livewire::actingAs(User::factory()->admin()->create())->test(Show::class, ['obj' => $vehicle])->assertOk();
        Livewire::actingAs(User::factory()->create())->test(Show::class, ['obj' => $vehicle])->assertForbidden();
    }
}
