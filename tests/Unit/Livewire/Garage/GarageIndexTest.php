<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Garage;

use App\Livewire\Garage\Index;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GarageIndexTest extends TestCase
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

    private function page()
    {
        return Livewire::actingAs(User::factory()->admin()->create())->test(Index::class);
    }

    private function rented(string $start, string $end): Garage
    {
        $garage = Garage::factory()->car()->create();
        Rental::factory()->create(['garage_id' => $garage->id, 'start_date' => $start, 'end_date' => $end]);

        return $garage;
    }

    public function test_mount_is_for_admins_and_sorts_by_code_ascending(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Index::class)->assertForbidden();
        $this->page()->assertOk()->assertSet('sortField', 'code')->assertSet('sortAsc', true);
    }

    public function test_render_gives_the_type_and_availability_options(): void
    {
        $this->page()
            ->assertViewHas('typeOptions', fn ($options) => array_keys($options) === Const_Type::GARAGE_TYPES)
            ->assertViewHas('availabilityOptions', [Const_Status::AVAILABLE => 'Available', Const_Status::USED => 'Used']);
    }

    public function test_render_lists_garages_ordered_by_code(): void
    {
        $second = Garage::factory()->car()->create(['code' => 'C002']);
        $first = Garage::factory()->car()->create(['code' => 'C001']);

        $this->page()->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$first->id, $second->id]);
    }

    public function test_get_query_loads_the_used_today_flag(): void
    {
        $used = $this->rented('2026-11-10', '2026-11-12');
        $free = Garage::factory()->car()->create();

        $this->page()->assertViewHas('data', fn ($data) => $data->firstWhere('id', $used->id)->status === Const_Status::USED
            && $data->firstWhere('id', $free->id)->status === Const_Status::AVAILABLE
            && array_key_exists('used_today', $data->first()->getAttributes()));
    }

    public function test_get_query_filters_by_keyword_in_the_code(): void
    {
        Garage::factory()->car()->create(['code' => 'C001']);
        $match = Garage::factory()->bicycle()->create(['code' => 'S007']);

        $this->page()->set('keyword', 's007')->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$match->id]);
    }

    public function test_get_query_filters_by_type(): void
    {
        Garage::factory()->car()->create();
        $electric = Garage::factory()->electricCar()->create();

        $this->page()->set('type', Const_Type::GARAGE_ELECTRIC_CAR)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$electric->id]);
    }

    public function test_get_query_filters_by_availability_today(): void
    {
        $used = $this->rented('2026-11-10', '2026-11-12');
        $endsToday = $this->rented('2026-11-08', '2026-11-10');
        $future = $this->rented('2026-11-11', '2026-11-12');
        $ended = $this->rented('2026-11-01', '2026-11-09');
        $never = Garage::factory()->car()->create();

        $this->page()->set('availability', Const_Status::USED)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->sort()->values()->all() === collect([$used->id, $endsToday->id])->sort()->values()->all());

        $this->page()->set('availability', Const_Status::AVAILABLE)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->sort()->values()->all() === collect([$future->id, $ended->id, $never->id])->sort()->values()->all());

        $this->page()->set('availability', '')->assertViewHas('data', fn ($data) => $data->total() === 5);
    }
}
