<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Garage\Create;
use App\Livewire\Garage\Index;
use App\Livewire\Garage\Show;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GarageService;
use App\Services\RentalService;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class GaragePagesTest extends TestCase
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

    /**
     * Rent a garage through the service (3 days from today unless overridden).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function rent(Garage $garage, array $overrides = []): Rental
    {
        $vehicle = Vehicle::factory()->car()->for(User::factory()->create())->create();

        return RentalService::create(array_merge([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 3,
            'payment_proof_path' => 'payment-proofs/new.jpg',
        ], $overrides));
    }

    // ------------------------------------------------------- Access

    public function test_guests_are_redirected_to_login(): void
    {
        $garage = Garage::factory()->car()->create();

        $this->get(route('garage.index'))->assertRedirect(route('login'));
        $this->get(route('garage.create'))->assertRedirect(route('login'));
        $this->get(route('garage.show', $garage))->assertRedirect(route('login'));
    }

    public function test_a_tenant_gets_403_on_the_routes(): void
    {
        $tenant = User::factory()->create();
        $garage = Garage::factory()->car()->create();

        $this->actingAs($tenant)->get(route('garage.index'))->assertForbidden();
        $this->actingAs($tenant)->get(route('garage.create'))->assertForbidden();
        $this->actingAs($tenant)->get(route('garage.show', $garage))->assertForbidden();
    }

    public function test_a_tenant_gets_403_on_the_components(): void
    {
        $tenant = User::factory()->create();
        $garage = Garage::factory()->car()->create();

        Livewire::actingAs($tenant)->test(Index::class)->assertForbidden();
        Livewire::actingAs($tenant)->test(Create::class)->assertForbidden();
        Livewire::actingAs($tenant)->test(Show::class, ['obj' => $garage])->assertForbidden();
    }

    public function test_an_admin_can_open_the_pages(): void
    {
        $garage = Garage::factory()->car()->create();

        $this->actingAs($this->admin())->get(route('garage.index'))->assertOk()->assertSeeLivewire(Index::class);
        $this->actingAs($this->admin())->get(route('garage.create'))->assertOk()->assertSeeLivewire(Create::class);
        $this->actingAs($this->admin())->get(route('garage.show', $garage))->assertOk()->assertSeeLivewire(Show::class);
    }

    // ------------------------------------------------------- Index

    public function test_index_sorts_by_code_ascending_by_default(): void
    {
        Garage::factory()->car()->create(['code' => 'C002']);
        Garage::factory()->bicycle()->create(['code' => 'S001']);
        Garage::factory()->car()->create(['code' => 'C001']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSet('sortField', 'code')
            ->assertSet('sortAsc', true)
            ->assertViewHas('data', fn ($data) => $data->pluck('code')->all() === ['C001', 'C002', 'S001']);
    }

    public function test_index_can_sort_by_daily_price(): void
    {
        Garage::factory()->car()->create(['code' => 'C001', 'daily_price' => 300000]);
        Garage::factory()->car()->create(['code' => 'C002', 'daily_price' => 100000]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('sort', 'daily_price')
            ->assertViewHas('data', fn ($data) => $data->pluck('code')->all() === ['C002', 'C001']);
    }

    public function test_index_filters_by_type_and_keyword(): void
    {
        Garage::factory()->car()->create(['code' => 'C001']);
        Garage::factory()->electricCar()->create(['code' => 'E001']);
        Garage::factory()->bicycle()->create(['code' => 'S001']);

        $component = Livewire::actingAs($this->admin())->test(Index::class);

        $component->set('type', Const_Type::GARAGE_ELECTRIC_CAR)
            ->assertViewHas('data', fn ($data) => $data->pluck('code')->all() === ['E001']);

        $component->set('type', '')->set('keyword', 'S00')
            ->assertViewHas('data', fn ($data) => $data->pluck('code')->all() === ['S001']);
    }

    public function test_used_today_is_true_only_for_a_garage_rented_today(): void
    {
        $used = Garage::factory()->car()->create(['code' => 'C001']);
        $free = Garage::factory()->car()->create(['code' => 'C002']);
        $future = Garage::factory()->car()->create(['code' => 'C003']);
        $this->rent($used); // 10 to 12 Nov
        $this->rent($future, ['start_date' => '2026-11-20']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('data', function ($data) use ($used, $free, $future) {
                $flags = $data->getCollection()->mapWithKeys(fn ($garage) => [$garage->id => (bool) $garage->used_today])->all();

                return $flags === [$used->id => true, $free->id => false, $future->id => false];
            });
    }

    public function test_a_garage_with_an_extension_covering_today_is_used(): void
    {
        $garage = Garage::factory()->car()->create(['code' => 'C001']);
        $rental = $this->rent($garage); // 10 to 12 Nov
        RentalService::extend($rental, '2026-11-16', 'payment-proofs/ext.jpg');

        Carbon::setTestNow('2026-11-15 09:00:00');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('data', fn ($data) => (bool) $data->first()->used_today === true);
    }

    public function test_the_availability_filter_limits_to_free_or_used(): void
    {
        $used = Garage::factory()->car()->create(['code' => 'C001']);
        $free = Garage::factory()->car()->create(['code' => 'C002']);
        $this->rent($used);

        $component = Livewire::actingAs($this->admin())->test(Index::class);

        $component->set('availability', Const_Status::USED)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$used->id]);
        $component->set('availability', Const_Status::AVAILABLE)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$free->id]);
        $component->set('availability', '')
            ->assertViewHas('data', fn ($data) => $data->total() === 2);
    }

    public function test_index_passes_the_option_lists(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('typeOptions', fn ($options) => array_keys($options) === Const_Type::GARAGE_TYPES)
            ->assertViewHas('availabilityOptions', fn ($options) => $options === [
                Const_Status::AVAILABLE => 'Available',
                Const_Status::USED => 'Used',
            ]);
    }

    public function test_the_status_accessor_is_computed_from_the_rental_dates(): void
    {
        $new = Garage::factory()->car()->create();
        $extended = Garage::factory()->car()->create();
        $ended = Garage::factory()->car()->create();
        $future = Garage::factory()->car()->create();
        $none = Garage::factory()->car()->create();

        // Book in the past (7 to 9 Nov), the service refuses past start dates.
        Carbon::setTestNow('2026-11-07 09:00:00');
        $this->rent($ended);
        $rental = $this->rent($extended);
        RentalService::extend($rental, '2026-11-12', 'payment-proofs/ext.jpg'); // extension row 10 to 12 Nov

        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->rent($new); // 10 to 12 Nov
        $this->rent($future, ['start_date' => '2026-11-11']);

        $this->assertSame(Const_Status::USED, $new->fresh()->status);
        $this->assertSame(Const_Status::USED, $extended->fresh()->status);
        $this->assertSame(Const_Status::AVAILABLE, $ended->fresh()->status);
        $this->assertSame(Const_Status::AVAILABLE, $future->fresh()->status);
        $this->assertSame(Const_Status::AVAILABLE, $none->fresh()->status);
    }

    public function test_the_status_accessor_does_not_add_queries_per_row_on_the_index(): void
    {
        Garage::factory()->car()->count(3)->create();
        $this->rent(Garage::first());

        $countQueries = function (): int {
            $count = 0;

            Livewire::actingAs($this->admin())
                ->test(Index::class)
                ->assertViewHas('data', function ($data) use (&$count) {
                    DB::flushQueryLog();
                    DB::enableQueryLog();
                    $data->getCollection()->each(fn ($garage) => $garage->status);
                    $count = count(DB::getQueryLog());
                    DB::disableQueryLog();

                    return true;
                });

            return $count;
        };

        $withThree = $countQueries();

        Garage::factory()->car()->count(17)->create();

        $this->assertSame(20, Garage::count());
        $this->assertSame($withThree, $countQueries());
        $this->assertSame(0, $withThree);
    }

    // ------------------------------------------------------- Create

    public function test_create_has_sensible_defaults(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Create::class)
            ->assertSet('type', Const_Type::GARAGE_CAR)
            ->assertSet('quantity', 1)
            ->assertViewHas('typeOptions', fn ($options) => array_keys($options) === Const_Type::GARAGE_TYPES);
    }

    public function test_create_requires_the_fields(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Create::class)
            ->set('type', '')
            ->set('quantity', '')
            ->call('submitDefault')
            ->assertHasErrors(['type' => 'required', 'quantity' => 'required', 'daily_price' => 'required']);

        $this->assertSame(0, Garage::count());
    }

    public function test_create_rejects_a_bad_type(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Create::class)
            ->set('type', 'truck')
            ->set('daily_price', 1000)
            ->call('submitDefault')
            ->assertHasErrors(['type' => 'in']);
    }

    public function test_create_quantity_must_be_between_1_and_999(): void
    {
        $component = Livewire::actingAs($this->admin())
            ->test(Create::class)
            ->set('daily_price', 1000);

        $component->set('quantity', 0)->call('submitDefault')->assertHasErrors(['quantity' => 'min']);
        $component->set('quantity', 1000)->call('submitDefault')->assertHasErrors(['quantity' => 'max']);
        $component->set('quantity', 'abc')->call('submitDefault')->assertHasErrors(['quantity' => 'integer']);

        $this->assertSame(0, Garage::count());
    }

    public function test_create_rejects_negative_prices(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Create::class)
            ->set('type', Const_Type::GARAGE_ELECTRIC_CAR)
            ->set('daily_price', -1)
            ->set('price_per_kwh', -1)
            ->call('submitDefault')
            ->assertHasErrors(['daily_price' => 'min', 'price_per_kwh' => 'min']);
    }

    public function test_price_per_kwh_is_required_only_for_electric(): void
    {
        $component = Livewire::actingAs($this->admin())
            ->test(Create::class)
            ->set('daily_price', 1000);

        $component->set('type', Const_Type::GARAGE_ELECTRIC_CAR)
            ->call('submitDefault')
            ->assertHasErrors(['price_per_kwh' => 'required_if']);

        $component->set('type', Const_Type::GARAGE_CAR)
            ->call('submitDefault')
            ->assertHasNoErrors();
    }

    public function test_a_valid_batch_creates_garages_and_redirects_to_the_index(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Create::class)
            ->set('type', Const_Type::GARAGE_CAR)
            ->set('quantity', 3)
            ->set('daily_price', 150000)
            ->set('price_per_kwh', 9999)
            ->call('submitDefault')
            ->assertHasNoErrors()
            ->assertRedirect(route('garage.index'));

        $garages = Garage::orderBy('code')->get();

        $this->assertSame(['C001', 'C002', 'C003'], $garages->pluck('code')->all());
        $this->assertSame(['150000.00'], $garages->pluck('daily_price')->unique()->values()->all());
        $this->assertSame([null], $garages->pluck('price_per_kwh')->unique()->values()->all());
        $this->assertSame('Garages have been created.', session('flash_success'));
    }

    public function test_an_electric_batch_keeps_the_price_per_kwh(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Create::class)
            ->set('type', Const_Type::GARAGE_ELECTRIC_CAR)
            ->set('quantity', 2)
            ->set('daily_price', 200000)
            ->set('price_per_kwh', 2500)
            ->call('submitDefault')
            ->assertHasNoErrors();

        $garages = Garage::orderBy('code')->get();

        $this->assertSame(['E001', 'E002'], $garages->pluck('code')->all());
        $this->assertSame(['2500.00'], $garages->pluck('price_per_kwh')->unique()->values()->all());
    }

    public function test_a_batch_that_would_pass_999_shows_an_error_and_creates_nothing(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_BICYCLE, 998, 10000);

        Livewire::actingAs($this->admin())
            ->test(Create::class)
            ->set('type', Const_Type::GARAGE_BICYCLE)
            ->set('quantity', 5)
            ->set('daily_price', 10000)
            ->call('submitDefault')
            ->assertHasErrors('flash_danger')
            ->assertNoRedirect();

        $this->assertSame(998, Garage::count());
    }

    public function test_the_code_hint_shows_the_next_codes(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_CAR, 10, 50000);

        Livewire::actingAs($this->admin())
            ->test(Create::class)
            ->set('quantity', 10)
            ->assertViewHas('codeHint', ['first' => 'C011', 'last' => 'C020', 'remaining' => 989, 'fits' => true])
            ->set('type', Const_Type::GARAGE_BICYCLE)
            ->assertViewHas('codeHint', ['first' => 'S001', 'last' => 'S010', 'remaining' => 999, 'fits' => true]);
    }

    public function test_the_code_hint_says_when_the_quantity_does_not_fit(): void
    {
        GarageService::createBatch(Const_Type::GARAGE_CAR, 990, 50000);

        Livewire::actingAs($this->admin())
            ->test(Create::class)
            ->set('quantity', 20)
            ->assertViewHas('codeHint', ['first' => null, 'last' => null, 'remaining' => 9, 'fits' => false]);
    }

    public function test_the_code_hint_is_null_without_a_valid_type_or_quantity(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Create::class);

        $component->set('quantity', '')->assertViewHas('codeHint', null);
        $component->set('quantity', 'abc')->assertViewHas('codeHint', null);
        $component->set('quantity', 2)->set('type', 'truck')->assertViewHas('codeHint', null);
    }

    // ------------------------------------------------------- Show

    public function test_show_reports_used_today(): void
    {
        $used = Garage::factory()->car()->create();
        $free = Garage::factory()->car()->create();
        $this->rent($used); // 10 to 12 Nov

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['obj' => $used])
            ->assertViewHas('usedToday', true)
            ->assertViewHas('status', Const_Status::USED);
        Livewire::actingAs($this->admin())
            ->test(Show::class, ['obj' => $free])
            ->assertViewHas('usedToday', false)
            ->assertViewHas('status', Const_Status::AVAILABLE);

        Carbon::setTestNow('2026-11-13 09:00:00');

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['obj' => $used])
            ->assertViewHas('usedToday', false);
    }

    public function test_show_lists_new_and_extension_rows_with_user_and_vehicle(): void
    {
        $garage = Garage::factory()->car()->create();
        $rental = $this->rent($garage);
        $extension = RentalService::extend($rental, '2026-11-14', 'payment-proofs/ext.jpg');
        $this->rent(Garage::factory()->car()->create());

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['obj' => $garage])
            ->assertViewHas('rentals', function ($rentals) use ($rental, $extension) {
                return $rentals->pluck('id')->sort()->values()->all() === collect([$rental->id, $extension->id])->sort()->values()->all()
                    && $rentals->pluck('status')->sort()->values()->all() === [Const_Status::EXTENSION, Const_Status::NEW]
                    && $rentals->every(fn ($row) => $row->relationLoaded('user') && $row->relationLoaded('vehicle'));
            });
    }

    public function test_show_limits_the_rentals_to_ten(): void
    {
        $garage = Garage::factory()->car()->create();
        $rental = $this->rent($garage, ['days' => 1]); // 10 Nov

        for ($i = 1; $i <= 11; $i++) {
            RentalService::extend($rental, Carbon::parse('2026-11-10')->addDays($i)->toDateString(), 'payment-proofs/ext.jpg');
        }

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['obj' => $garage])
            ->assertViewHas('rentals', fn ($rentals) => $rentals->count() === 10);
    }
}
