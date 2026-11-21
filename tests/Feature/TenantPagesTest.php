<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\User\Index;
use App\Livewire\User\Show;
use App\Models\Garage;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RentalService;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-11-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ------------------------------------------------------- Access

    public function test_guests_are_redirected_to_login(): void
    {
        $tenant = User::factory()->pending()->create();

        $this->get(route('user.index'))->assertRedirect(route('login'));
        $this->get(route('user.show', $tenant))->assertRedirect(route('login'));
    }

    public function test_a_tenant_gets_403_on_the_routes(): void
    {
        $tenant = User::factory()->create();

        $this->actingAs($tenant)->get(route('user.index'))->assertForbidden();
        $this->actingAs($tenant)->get(route('user.show', $tenant))->assertForbidden();
    }

    public function test_a_tenant_gets_403_on_the_components(): void
    {
        $tenant = User::factory()->create();

        Livewire::actingAs($tenant)->test(Index::class)->assertForbidden();
        Livewire::actingAs($tenant)->test(Show::class, ['obj' => $tenant])->assertForbidden();
    }

    public function test_an_admin_can_open_the_pages(): void
    {
        $tenant = User::factory()->pending()->create();

        $this->actingAs($this->admin())->get(route('user.index'))->assertOk()->assertSeeLivewire(Index::class);
        $this->actingAs($this->admin())->get(route('user.show', $tenant))->assertOk()->assertSeeLivewire(Show::class);
    }

    // ------------------------------------------------------- Index

    public function test_index_lists_only_tenants(): void
    {
        $admin = $this->admin();
        $tenant = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$tenant->id]);
    }

    public function test_index_keyword_searches_name_email_nik_and_phone(): void
    {
        $first = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com', 'nik' => '1111111111111111', 'phone' => '081200000001']);
        $second = User::factory()->create(['name' => 'Sari Dewi', 'email' => 'sari@example.com', 'nik' => '2222222222222222', 'phone' => '081200000002']);

        $component = Livewire::actingAs($this->admin())->test(Index::class);

        $component->set('keyword', 'Budi')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$first->id]);
        $component->set('keyword', 'sari@example')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$second->id]);
        $component->set('keyword', '1111111111')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$first->id]);
        $component->set('keyword', '081200000002')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$second->id]);
    }

    public function test_index_status_filter_limits_the_list(): void
    {
        $pending = User::factory()->pending()->create();
        $approved = User::factory()->create();
        $rejected = User::factory()->rejected()->create();

        $component = Livewire::actingAs($this->admin())->test(Index::class);

        $component->set('status', Const_Status::PENDING)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$pending->id]);
        $component->set('status', Const_Status::APPROVED)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$approved->id]);
        $component->set('status', Const_Status::REJECTED)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$rejected->id]);
    }

    public function test_index_passes_the_status_options(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('statusOptions', fn ($options) => array_keys($options) === [
                Const_Status::PENDING,
                Const_Status::APPROVED,
                Const_Status::REJECTED,
            ]);
    }

    // ------------------------------------------------------- Show

    public function test_show_gives_404_for_an_admin_user(): void
    {
        $this->actingAs($this->admin())
            ->get(route('user.show', $this->admin()))
            ->assertNotFound();
    }

    public function test_approve_changes_the_status_and_flashes_success(): void
    {
        $tenant = User::factory()->pending()->create();

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['obj' => $tenant])
            ->assertViewHas('canApprove', true)
            ->assertViewHas('canReject', true)
            ->call('approve')
            ->assertHasNoErrors()
            ->assertViewHas('canApprove', false)
            ->assertViewHas('canReject', true)
            ->assertSee('Registration approved.');

        $this->assertSame(Const_Status::APPROVED, $tenant->fresh()->registration_status);
    }

    public function test_reject_changes_the_status_and_flashes_success(): void
    {
        $tenant = User::factory()->pending()->create();

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['obj' => $tenant])
            ->call('reject')
            ->assertHasNoErrors()
            ->assertViewHas('canApprove', true)
            ->assertViewHas('canReject', false)
            ->assertSee('Registration rejected.');

        $this->assertSame(Const_Status::REJECTED, $tenant->fresh()->registration_status);
    }

    public function test_an_approved_tenant_cannot_be_approved_again_but_can_be_rejected(): void
    {
        $tenant = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['obj' => $tenant])
            ->assertViewHas('canApprove', false)
            ->assertViewHas('canReject', true);
    }

    public function test_a_rejected_tenant_can_be_approved_later(): void
    {
        $tenant = User::factory()->rejected()->create();

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['obj' => $tenant])
            ->assertViewHas('canApprove', true)
            ->assertViewHas('canReject', false)
            ->call('approve');

        $this->assertSame(Const_Status::APPROVED, $tenant->fresh()->registration_status);
    }

    public function test_show_passes_the_vehicles_and_latest_new_rentals_only(): void
    {
        $tenant = User::factory()->create();
        $car = Vehicle::factory()->car()->for($tenant)->create();
        $bicycle = Vehicle::factory()->bicycle()->for($tenant)->create();
        Vehicle::factory()->create();

        $garage = Garage::factory()->car()->create();
        $rental = RentalService::create([
            'user_id' => $tenant->id,
            'vehicle_id' => $car->id,
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 3,
            'payment_proof_path' => 'payment-proofs/new.jpg',
        ]);
        RentalService::extend($rental, '2026-11-14', 'payment-proofs/ext.jpg');

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['obj' => $tenant])
            ->assertViewHas('vehicles', fn ($vehicles) => $vehicles->pluck('id')->sort()->values()->all() === collect([$car->id, $bicycle->id])->sort()->values()->all())
            ->assertViewHas('rentals', fn ($rentals) => $rentals->pluck('id')->all() === [$rental->id]
                && $rentals->first()->relationLoaded('garage'));
    }

    public function test_show_limits_the_rentals_to_ten(): void
    {
        $tenant = User::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            RentalService::create([
                'user_id' => $tenant->id,
                'vehicle_id' => Vehicle::factory()->car()->for($tenant)->create()->id,
                'garage_id' => Garage::factory()->car()->create()->id,
                'rental_type' => Const_Type::RENTAL_DAILY,
                'days' => 2,
                'payment_proof_path' => 'payment-proofs/new.jpg',
            ]);
        }

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['obj' => $tenant])
            ->assertViewHas('rentals', fn ($rentals) => $rentals->count() === 10);
    }
}
