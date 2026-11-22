<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Rental;

use App\Livewire\Rental\Index;
use App\Models\Rental;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RentalIndexTest extends TestCase
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

    private function page(User $user)
    {
        return Livewire::actingAs($user)->test(Index::class);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rental(string $start, string $end, array $attributes = []): Rental
    {
        return Rental::factory()->create(array_merge(['start_date' => $start, 'end_date' => $end], $attributes));
    }

    public function test_a_tenant_sees_only_their_own_rentals_and_no_extension_rows(): void
    {
        $tenant = User::factory()->create();
        $mine = $this->rental('2026-11-10', '2026-11-12', ['user_id' => $tenant->id]);
        Rental::factory()->extension()->create(['user_id' => $tenant->id]);
        $this->rental('2026-11-10', '2026-11-12');

        $this->page($tenant)->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$mine->id]);
    }
}
