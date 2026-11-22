<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\User;

use App\Livewire\User\Show;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Utilities\Constants\Const_Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function page(User $tenant)
    {
        return Livewire::actingAs(User::factory()->admin()->create())->test(Show::class, ['obj' => $tenant]);
    }

    public function test_mount_is_for_admins_and_only_for_tenants(): void
    {
        $tenant = User::factory()->pending()->create();

        Livewire::actingAs(User::factory()->create())->test(Show::class, ['obj' => $tenant])->assertForbidden();
        Livewire::actingAs(User::factory()->admin()->create())->test(Show::class, ['obj' => User::factory()->admin()->create()])->assertNotFound();
        $this->page($tenant)->assertOk();
    }

    public function test_render_gives_the_vehicles_and_new_rentals_of_the_tenant_only(): void
    {
        $tenant = User::factory()->create();
        $vehicle = Vehicle::factory()->for($tenant)->create();
        Vehicle::factory()->create();
        $new = Rental::factory()->create(['user_id' => $tenant->id, 'vehicle_id' => $vehicle->id]);
        Rental::factory()->extension()->create(['user_id' => $tenant->id, 'vehicle_id' => $vehicle->id]);
        Rental::factory()->create();

        $this->page($tenant)
            ->assertViewHas('vehicles', fn ($vehicles) => $vehicles->pluck('id')->all() === [$vehicle->id])
            ->assertViewHas('rentals', fn ($rentals) => $rentals->pluck('id')->all() === [$new->id] && $rentals->first()->relationLoaded('garage'));
    }

    public function test_render_limits_the_rentals_to_ten(): void
    {
        $tenant = User::factory()->create();
        Rental::factory()->count(12)->create(['user_id' => $tenant->id]);

        $this->page($tenant)->assertViewHas('rentals', fn ($rentals) => $rentals->count() === 10);
    }

    public function test_render_says_which_decisions_are_still_possible(): void
    {
        $this->page(User::factory()->pending()->create())->assertViewHas('canApprove', true)->assertViewHas('canReject', true);
        $this->page(User::factory()->create())->assertViewHas('canApprove', false)->assertViewHas('canReject', true);
        $this->page(User::factory()->rejected()->create())->assertViewHas('canApprove', true)->assertViewHas('canReject', false);
    }

    public function test_approve_approves_the_tenant_and_refreshes_the_page(): void
    {
        $tenant = User::factory()->pending()->create();

        $this->page($tenant)->call('approve')->assertHasNoErrors()->assertViewHas('canApprove', false);

        $this->assertSame(Const_Status::APPROVED, $tenant->fresh()->registration_status);
    }

    public function test_reject_rejects_the_tenant_and_refreshes_the_page(): void
    {
        $tenant = User::factory()->pending()->create();

        $this->page($tenant)->call('reject')->assertHasNoErrors()->assertViewHas('canReject', false);

        $this->assertSame(Const_Status::REJECTED, $tenant->fresh()->registration_status);
    }

    public function test_approve_and_reject_are_refused_for_a_tenant(): void
    {
        $tenant = User::factory()->pending()->create();

        Livewire::actingAs($tenant)->test(Show::class, ['obj' => $tenant])->assertForbidden();
        $this->assertSame(Const_Status::PENDING, $tenant->fresh()->registration_status);
    }
}
