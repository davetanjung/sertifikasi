<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Garage;

use App\Livewire\Garage\Create;
use App\Models\Garage;
use App\Models\User;
use App\Utilities\Constants\Const_Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GarageCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function page()
    {
        return Livewire::actingAs(User::factory()->admin()->create())->test(Create::class);
    }

    public function test_mount_is_for_admins_with_a_car_garage_of_one_as_default(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Create::class)->assertForbidden();
        $this->page()->assertOk()->assertSet('type', Const_Type::GARAGE_CAR)->assertSet('quantity', 1);
    }

    public function test_render_gives_the_type_options_and_the_code_hint(): void
    {
        $this->page()
            ->assertViewHas('typeOptions', fn ($options) => array_keys($options) === Const_Type::GARAGE_TYPES)
            ->assertViewHas('codeHint', ['first' => 'C001', 'last' => 'C001', 'remaining' => 999, 'fits' => true]);
    }

    public function test_code_hint_follows_the_type_and_quantity(): void
    {
        $this->page()->set('type', Const_Type::GARAGE_BICYCLE)->set('quantity', 3)
            ->assertViewHas('codeHint', fn ($hint) => $hint['first'] === 'S001' && $hint['last'] === 'S003');
    }

    public function test_code_hint_is_null_for_a_bad_quantity_or_type(): void
    {
        $this->page()->set('quantity', 'abc')->assertViewHas('codeHint', null);
        $this->page()->set('quantity', '2.5')->assertViewHas('codeHint', null);
        $this->page()->set('type', 'boat')->assertViewHas('codeHint', null);
    }

    public function test_code_hint_says_when_the_batch_does_not_fit(): void
    {
        Garage::factory()->car()->create(['code' => 'C998']);

        $this->page()->set('quantity', 5)->assertViewHas('codeHint', fn ($hint) => $hint['fits'] === false && $hint['remaining'] === 1);
    }

    public function test_submit_creates_the_batch_and_goes_to_the_garage_list(): void
    {
        $this->page()
            ->set('quantity', 3)
            ->set('daily_price', 50000)
            ->call('submitDefault')
            ->assertHasNoErrors()
            ->assertRedirect(route('garage.index'));

        $this->assertSame(['C001', 'C002', 'C003'], Garage::orderBy('code')->pluck('code')->all());
        $this->assertSame('Garages have been created.', session('flash_success'));
    }

    public function test_submit_saves_the_kwh_price_for_electric_garages(): void
    {
        $this->page()->set('type', Const_Type::GARAGE_ELECTRIC_CAR)->set('daily_price', 90000)->set('price_per_kwh', 3500)->call('submitDefault');

        $garage = Garage::firstOrFail();
        $this->assertSame('E001', $garage->code);
        $this->assertSame('3500.00', $garage->price_per_kwh);
    }

    public function test_rules_check_every_field(): void
    {
        $this->page()->set('type', 'boat')->set('quantity', 0)->set('daily_price', -1)->call('submitDefault')
            ->assertHasErrors(['type' => 'in', 'quantity' => 'min', 'daily_price' => 'min']);

        $this->page()->set('quantity', 1000)->set('daily_price', 1)->call('submitDefault')->assertHasErrors(['quantity' => 'max']);
        $this->page()->set('quantity', 'x')->call('submitDefault')->assertHasErrors(['quantity' => 'integer']);
        $this->page()->set('daily_price', '')->call('submitDefault')->assertHasErrors(['daily_price' => 'required']);
        $this->page()->set('type', Const_Type::GARAGE_ELECTRIC_CAR)->set('daily_price', 1)->call('submitDefault')->assertHasErrors(['price_per_kwh' => 'required_if']);
        $this->assertDatabaseCount('garages', 0);
    }

    public function test_a_batch_over_999_is_refused_and_nothing_is_created(): void
    {
        Garage::factory()->car()->create(['code' => 'C998']);

        $this->page()->set('quantity', 2)->set('daily_price', 1)->call('submitDefault')->assertHasErrors('flash_danger');

        $this->assertDatabaseCount('garages', 1);
    }
}
