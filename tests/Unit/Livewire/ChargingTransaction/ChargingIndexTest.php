<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\ChargingTransaction;

use App\Livewire\ChargingTransaction\Index;
use App\Models\ChargingTransaction;
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

    public function test_a_tenant_sees_only_their_own_transactions(): void
    {
        $tenant = User::factory()->create();
        $mine = ChargingTransaction::factory()->create(['rental_id' => Rental::factory()->create(['user_id' => $tenant->id])->id]);
        ChargingTransaction::factory()->create();

        $this->page($tenant)->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$mine->id]);
    }
}
