<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\ChargingTransaction;

use App\Livewire\ChargingTransaction\Index;
use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChargingIndexTest extends TestCase
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

    public function test_a_tenant_sees_only_their_own_transactions(): void
    {
        $tenant = User::factory()->create();
        $mine = ChargingTransaction::factory()->create(['rental_id' => Rental::factory()->create(['user_id' => $tenant->id])->id]);
        ChargingTransaction::factory()->create();

        $this->page($tenant)->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$mine->id]);
    }

    public function test_an_admin_sees_every_transaction_with_the_rental_loaded(): void
    {
        ChargingTransaction::factory()->count(2)->create();

        $this->page(User::factory()->admin()->create())->assertViewHas('data', fn ($data) => $data->count() === 2
            && $data->first()->relationLoaded('rental')
            && $data->first()->rental->relationLoaded('garage'));
    }

    public function test_get_query_filters_by_rental_code_and_garage_code(): void
    {
        $garage = Garage::factory()->electricCar()->create(['code' => 'E042']);
        $rental = Rental::factory()->create(['garage_id' => $garage->id, 'code' => 'RNT-20261110-0007']);
        $match = ChargingTransaction::factory()->create(['rental_id' => $rental->id]);
        ChargingTransaction::factory()->create();
        $admin = User::factory()->admin()->create();

        foreach (['0007', 'e042'] as $keyword) {
            $this->page($admin)->set('keyword', $keyword)->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$match->id]);
        }

        $this->page($admin)->set('keyword', 'no match')->assertViewHas('data', fn ($data) => $data->isEmpty());
    }

    public function test_sort_changes_the_order(): void
    {
        $admin = User::factory()->admin()->create();
        $big = ChargingTransaction::factory()->create(['amount' => 90000]);
        $small = ChargingTransaction::factory()->create(['amount' => 1000]);

        $this->page($admin)->call('sort', 'amount')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$small->id, $big->id]);
    }
}
