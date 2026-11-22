<?php

declare(strict_types=1);

namespace Tests\Unit\Traits\Livewire;

use App\Models\Garage;
use App\Traits\Livewire\WithCreateForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class WithFormSubmitTest extends TestCase
{
    use RefreshDatabase;

    private function fill($component)
    {
        return $component->set('daily_price', 50000);
    }

    public function test_submit_default_saves_and_goes_to_the_show_page(): void
    {
        $component = $this->fill(Livewire::test(GarageCreateStub::class))->call('submitDefault');

        $garage = Garage::firstOrFail();
        $component->assertHasNoErrors()->assertRedirect(route('garage.show', $garage->id));
    }

    public function test_submit_and_show_goes_to_the_show_page(): void
    {
        $this->fill(Livewire::test(GarageCreateStub::class))->call('submitAndShow')
            ->assertRedirect(route('garage.show', Garage::firstOrFail()->id));
    }

    public function test_submit_and_back_to_index_goes_to_the_index_page(): void
    {
        $this->fill(Livewire::test(GarageCreateStub::class))->call('submitAndBackToIndex')
            ->assertRedirect(route('garage.index'));
    }

    public function test_submit_and_create_goes_to_the_create_page(): void
    {
        $this->fill(Livewire::test(GarageCreateStub::class))->call('submitAndCreate')
            ->assertRedirect(route('garage.create'));
    }

    public function test_a_fixed_redirect_url_wins_over_the_destination(): void
    {
        $this->fill(Livewire::test(GarageCreateWithFixedUrlStub::class))->call('submitAndCreate')
            ->assertRedirect('/dashboard');
    }

    public function test_success_is_flashed_with_the_menu_title_and_the_submit_message(): void
    {
        $this->fill(Livewire::test(GarageCreateStub::class))->call('submitDefault');

        $this->assertSame('Garage has been added.', session('flash_success'));
    }

    public function test_the_input_is_validated_and_nothing_is_saved_when_it_is_wrong(): void
    {
        Livewire::test(GarageCreateStub::class)
            ->set('daily_price', 'abc')
            ->call('submitDefault')
            ->assertHasErrors(['daily_price' => 'numeric'])
            ->assertNoRedirect();

        $this->assertDatabaseCount('garages', 0);
    }

    public function test_blank_values_are_dropped_before_submit(): void
    {
        $component = Livewire::test(GarageCreateStub::class)->set('daily_price', 50000)->set('note', '')->call('submitDefault');

        $component->assertHasNoErrors();
        $this->assertDatabaseCount('garages', 1);
    }

    public function test_a_failure_inside_submit_rolls_back_and_shows_an_error(): void
    {
        $this->fill(Livewire::test(GarageCreateFailingStub::class))
            ->call('submitDefault')
            ->assertHasErrors('flash_danger')
            ->assertNoRedirect();

        $this->assertDatabaseCount('garages', 0);
    }

    public function test_the_default_submit_tells_the_developer_to_define_it(): void
    {
        $component = Livewire::test(GarageCreateWithoutSubmitStub::class)->set('daily_price', 1)->call('submitDefault');

        $component->assertHasErrors('flash_danger');
        $this->assertStringContainsString('submit function must be defined in', $component->errors()->first('flash_danger'));
    }
}

class GarageCreateStub extends Component
{
    use WithCreateForm;

    public $model = Garage::class;

    public $menuTitle = 'Garage';

    public $daily_price;

    public $note;

    protected function rules(): array
    {
        return ['daily_price' => ['required', 'numeric'], 'note' => ['nullable', 'string']];
    }

    public function submit(array $validated): Garage
    {
        return Garage::create(['type' => 'car', 'daily_price' => $validated['daily_price']]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

class GarageCreateWithFixedUrlStub extends GarageCreateStub
{
    protected function submitRedirectUrl(): ?string
    {
        return '/dashboard';
    }
}

class GarageCreateFailingStub extends GarageCreateStub
{
    public function submit(array $validated): Garage
    {
        Garage::create(['type' => 'car', 'daily_price' => $validated['daily_price']]);

        throw new RuntimeException('boom');
    }
}

class GarageCreateWithoutSubmitStub extends Component
{
    use WithCreateForm;

    public $menuTitle = 'Garage';

    public $daily_price;

    protected function rules(): array
    {
        return ['daily_price' => ['required']];
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
