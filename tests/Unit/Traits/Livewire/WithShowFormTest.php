<?php

declare(strict_types=1);

namespace Tests\Unit\Traits\Livewire;

use App\Models\Garage;
use App\Models\User;
use App\Traits\Livewire\WithShowForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class WithShowFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_removes_the_shown_record_and_goes_to_the_index(): void
    {
        $garage = Garage::factory()->car()->create();

        Livewire::test(GarageShowStub::class, ['obj' => $garage])
            ->call('delete')
            ->assertHasNoErrors()
            ->assertRedirect(route('garage.index'));

        $this->assertDatabaseMissing('garages', ['id' => $garage->id]);
    }

    public function test_delete_ignores_the_ids_the_browser_sends(): void
    {
        $shown = Garage::factory()->car()->create();
        $other = Garage::factory()->car()->create();

        Livewire::test(GarageShowStub::class, ['obj' => $shown])->call('delete', ['id' => $other->id]);

        $this->assertDatabaseMissing('garages', ['id' => $shown->id]);
        $this->assertDatabaseHas('garages', ['id' => $other->id]);
    }

    public function test_delete_is_refused_when_permissions_are_on_and_the_user_may_not_delete(): void
    {
        $garage = Garage::factory()->car()->create();
        config(['app.check_permissions' => true]);
        $this->actingAs(User::factory()->create());

        Livewire::test(GarageShowStub::class, ['obj' => $garage])->call('delete')->assertForbidden();

        $this->assertDatabaseHas('garages', ['id' => $garage->id]);
    }

    public function test_process_delete_must_be_defined_by_the_page(): void
    {
        $garage = Garage::factory()->car()->create();

        $component = Livewire::test(GarageShowWithoutDeleteStub::class, ['obj' => $garage])->call('delete');

        $component->assertHasErrors('flash_danger');
        $this->assertStringContainsString('processDelete function must be defined in', $component->errors()->first('flash_danger'));
        $this->assertDatabaseHas('garages', ['id' => $garage->id]);
    }

    public function test_process_action_runs_the_named_method_in_a_transaction(): void
    {
        Livewire::test(GarageShowStub::class, ['obj' => Garage::factory()->car()->create()])
            ->call('processAction', 'markDone', ['x' => 1])
            ->assertSet('actionResult', ['x' => 1]);
    }

    public function test_process_action_rolls_back_and_shows_an_error_when_the_method_fails(): void
    {
        Livewire::test(GarageShowStub::class, ['obj' => Garage::factory()->car()->create()])
            ->call('processAction', 'failAfterSaving')
            ->assertHasErrors('flash_danger');

        $this->assertDatabaseCount('garages', 1);
    }

    public function test_process_action_passes_validation_errors_through(): void
    {
        Livewire::test(GarageShowStub::class, ['obj' => Garage::factory()->car()->create()])
            ->call('processAction', 'failValidation')
            ->assertHasErrors('field');
    }
}

class GarageShowStub extends Component
{
    use WithShowForm;

    public $model = Garage::class;

    public $menuTitle = 'Garage';

    public $obj;

    public $actionResult;

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

class GarageShowWithoutDeleteStub extends Component
{
    use WithShowForm;

    public $model = Garage::class;

    public $menuTitle = 'Garage';

    public $obj;

    public function render(): string
    {
        return '<div></div>';
    }
}
