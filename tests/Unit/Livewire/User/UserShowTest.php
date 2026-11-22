<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\User;

use App\Livewire\User\Show;
use App\Models\User;
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
