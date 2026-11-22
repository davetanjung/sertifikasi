<?php

declare(strict_types=1);

namespace Tests\Unit\Traits\Livewire;

use App\Models\Garage;
use App\Models\User;
use App\Traits\Livewire\WithIndexForm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class WithIndexFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_sort_on_a_new_column_sorts_ascending(): void
    {
        Livewire::test(GarageIndexStub::class)
            ->assertSet('sortField', 'created_at')
            ->assertSet('sortAsc', false)
            ->call('sort', 'code')
            ->assertSet('sortField', 'code')
            ->assertSet('sortAsc', true);
    }

    public function test_sort_on_the_same_column_toggles_the_direction(): void
    {
        Livewire::test(GarageIndexStub::class)
            ->call('sort', 'code')
            ->call('sort', 'code')
            ->assertSet('sortAsc', false)
            ->call('sort', 'code')
            ->assertSet('sortAsc', true);
    }

    public function test_sort_can_keep_the_direction_when_toggle_is_off(): void
    {
        Livewire::test(GarageIndexStub::class)
            ->call('sort', 'created_at', false)
            ->assertSet('sortAsc', false);
    }

    public function test_process_filter_pages_the_data_and_sets_the_first_item_number(): void
    {
        Garage::factory()->car()->count(5)->create();

        $component = Livewire::test(GarageIndexStub::class)->set('perPage', 2)->call('processFilter');

        $component->assertSet('no_item', 1);

        $component->set('paginators.page', 3)->call('processFilter')->assertSet('no_item', 5);
    }

    public function test_process_filter_returns_to_page_one_when_the_page_is_past_the_end(): void
    {
        Garage::factory()->car()->count(2)->create();

        $component = Livewire::test(GarageIndexStub::class)->set('perPage', 2)->set('paginators.page', 9)->call('processFilter');

        $component->assertSet('paginators.page', 1);
    }

    public function test_delete_removes_the_record_and_flashes_success(): void
    {
        $garage = Garage::factory()->car()->create();

        Livewire::test(GarageIndexStub::class)->call('delete', ['id' => $garage->id])->assertHasNoErrors();

        $this->assertDatabaseMissing('garages', ['id' => $garage->id]);
        $this->assertContains('flash_success', session('_flash.new'));
    }

    public function test_delete_of_an_unknown_id_shows_an_error_and_deletes_nothing(): void
    {
        $garage = Garage::factory()->car()->create();

        Livewire::test(GarageIndexStub::class)->call('delete', ['id' => 'does-not-exist'])->assertHasErrors('flash_danger');

        $this->assertDatabaseHas('garages', ['id' => $garage->id]);
    }

    public function test_delete_refuses_when_permissions_are_on_and_the_user_may_not_delete(): void
    {
        $garage = Garage::factory()->car()->create();
        config(['app.check_permissions' => true]);
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(GarageIndexStub::class)->call('delete', ['id' => $garage->id]);

        $component->assertHasErrors('flash_danger');
        $this->assertStringContainsString('You are not allowed to delete this record.', $component->errors()->first('flash_danger'));
        $this->assertDatabaseHas('garages', ['id' => $garage->id]);
    }

    public function test_process_delete_must_be_defined_by_the_page(): void
    {
        $garage = Garage::factory()->car()->create();

        $component = Livewire::test(GarageIndexWithoutDeleteStub::class)->call('delete', ['id' => $garage->id]);

        $component->assertHasErrors('flash_danger');
        $this->assertStringContainsString('processDelete function must be defined in', $component->errors()->first('flash_danger'));
        $this->assertDatabaseHas('garages', ['id' => $garage->id]);
    }

    public function test_process_action_runs_the_named_method_in_a_transaction(): void
    {
        Livewire::test(GarageIndexStub::class)->call('processAction', 'markDone', ['x' => 1])->assertSet('actionResult', ['x' => 1]);
    }

    public function test_process_action_rolls_back_and_shows_an_error_when_the_method_fails(): void
    {
        Livewire::test(GarageIndexStub::class)
            ->call('processAction', 'failAfterSaving')
            ->assertHasErrors('flash_danger');

        $this->assertDatabaseCount('garages', 0);
    }

    public function test_process_action_passes_validation_errors_through(): void
    {
        Livewire::test(GarageIndexStub::class)
            ->call('processAction', 'failValidation')
            ->assertHasErrors('field');
    }
}

class GarageIndexStub extends Component
{
    use WithIndexForm;

    public $model = Garage::class;

    public $menuTitle = 'Garage';

    public $actionResult;

    private function getQuery(): Builder
    {
        return Garage::query();
    }

    public function processDelete($id): void
    {
        Garage::query()->findOrFail($id)->delete();
    }

    public function markDone($parameters): void
    {
        $this->actionResult = $parameters;
    }

    public function failAfterSaving($parameters): void
    {
        Garage::create(['type' => 'car', 'daily_price' => 1]);

        throw new \RuntimeException('boom');
    }

    public function failValidation($parameters): void
    {
        throw ValidationException::withMessages(['field' => 'Bad field.']);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

class GarageIndexWithoutDeleteStub extends Component
{
    use WithIndexForm;

    public $model = Garage::class;

    public $menuTitle = 'Garage';

    private function getQuery(): Builder
    {
        return Garage::query();
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
