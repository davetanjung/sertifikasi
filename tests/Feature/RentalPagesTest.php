<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Rental\Create;
use App\Livewire\Rental\Index;
use App\Livewire\Rental\Show;
use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RentalService;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RentalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-11-10 09:00:00');
        Storage::fake('public');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Rent through the service (a daily rental of 3 days starting today unless overridden).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function rent(Garage $garage, Vehicle $vehicle, array $overrides = []): Rental
    {
        return RentalService::create(array_merge([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 3,
            'payment_proof_path' => 'payment-proofs/new.jpg',
        ], $overrides));
    }

    private function carOf(User $user, array $attributes = []): Vehicle
    {
        return Vehicle::factory()->car()->for($user)->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillCreate(User $tenant, array $data)
    {
        $component = Livewire::actingAs($tenant)->test(Create::class);

        foreach ($data as $key => $value) {
            $component->set($key, $value);
        }

        return $component;
    }

    /**
     * @return array<string, mixed>
     */
    private function validForm(Vehicle $vehicle, Garage $garage, array $overrides = []): array
    {
        return array_merge([
            'vehicle_id' => $vehicle->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 3,
            'start_date' => '2026-11-10',
            'garage_id' => $garage->id,
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ], $overrides);
    }

    // ---------------------------------------------------------------- Index

    public function test_guests_are_redirected_to_login(): void
    {
        $rental = Rental::factory()->create();

        $this->get(route('rental.index'))->assertRedirect(route('login'));
        $this->get(route('rental.create'))->assertRedirect(route('login'));
        $this->get(route('rental.show', $rental))->assertRedirect(route('login'));
    }

    public function test_a_tenant_sees_only_their_own_new_rows(): void
    {
        $tenant = User::factory()->create();
        $mine = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant));
        $this->rent(Garage::factory()->car()->create(), $this->carOf(User::factory()->create()));

        Livewire::actingAs($tenant)
            ->test(Index::class)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$mine->id]);
    }

    public function test_an_admin_sees_all_rentals(): void
    {
        $this->rent(Garage::factory()->car()->create(), $this->carOf(User::factory()->create()));
        $this->rent(Garage::factory()->car()->create(), $this->carOf(User::factory()->create()));

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->assertViewHas('data', fn ($data) => $data->total() === 2);
    }

    public function test_extension_rows_are_not_listed_and_latest_end_date_includes_them(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant)); // 10 to 12 Nov
        RentalService::extend($rental, '2026-11-14', 'payment-proofs/ext.jpg');

        Livewire::actingAs($tenant)
            ->test(Index::class)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$rental->id]
                && $data->first()->latest_end_date === '2026-11-14');
    }

    public function test_every_row_gets_a_period_status(): void
    {
        $tenant = User::factory()->create();
        $ended = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant), ['days' => 2]); // 10 to 11
        $running = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant), ['start_date' => '2026-11-12', 'days' => 5]); // 12 to 16
        $upcoming = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant), ['start_date' => '2026-11-20', 'days' => 2]);

        Carbon::setTestNow('2026-11-16 09:00:00');

        Livewire::actingAs($tenant)
            ->test(Index::class)
            ->assertViewHas('data', function ($data) use ($ended, $running, $upcoming) {
                $statuses = $data->getCollection()->pluck('period_status', 'id')->all();

                return $statuses == [
                    $ended->id => 'ended',
                    $running->id => 'running',
                    $upcoming->id => 'upcoming',
                ];
            });
    }

    public function test_a_rental_is_running_on_its_last_extension_day_and_ended_after(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant)); // 10 to 12
        RentalService::extend($rental, '2026-11-14', 'payment-proofs/ext.jpg');

        Carbon::setTestNow('2026-11-14 09:00:00');
        Livewire::actingAs($tenant)->test(Index::class)
            ->assertViewHas('data', fn ($data) => $data->first()->period_status === 'running');

        Carbon::setTestNow('2026-11-15 09:00:00');
        Livewire::actingAs($tenant)->test(Index::class)
            ->assertViewHas('data', fn ($data) => $data->first()->period_status === 'ended');
    }

    public function test_the_period_filter_limits_the_list(): void
    {
        $tenant = User::factory()->create();
        $ended = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant), ['days' => 2]);
        $running = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant), ['start_date' => '2026-11-12', 'days' => 5]);
        $upcoming = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant), ['start_date' => '2026-11-20', 'days' => 2]);

        Carbon::setTestNow('2026-11-16 09:00:00');

        $component = Livewire::actingAs($tenant)->test(Index::class);

        $component->set('period', 'ended')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$ended->id]);
        $component->set('period', 'running')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$running->id]);
        $component->set('period', 'upcoming')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$upcoming->id]);
    }

    public function test_the_period_filter_uses_the_extended_end_date(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant)); // 10 to 12
        RentalService::extend($rental, '2026-11-16', 'payment-proofs/ext.jpg');

        Carbon::setTestNow('2026-11-14 09:00:00');

        $component = Livewire::actingAs($tenant)->test(Index::class);

        $component->set('period', 'ended')
            ->assertViewHas('data', fn ($data) => $data->total() === 0);
        $component->set('period', 'running')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$rental->id]);
    }

    public function test_the_rental_type_filter_limits_the_list(): void
    {
        $tenant = User::factory()->create();
        $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant));
        $monthly = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant), [
            'rental_type' => Const_Type::RENTAL_MONTHLY,
            'days' => null,
        ]);

        Livewire::actingAs($tenant)
            ->test(Index::class)
            ->set('rental_type', Const_Type::RENTAL_MONTHLY)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$monthly->id]);
    }

    public function test_keyword_search_matches_code_garage_code_and_plate(): void
    {
        $tenant = User::factory()->create();
        $first = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant, ['plate_number' => 'D 7777 QQ']));
        $second = $this->rent(Garage::factory()->electricCar()->create(), Vehicle::factory()->electricCar()->for($tenant)->create(['plate_number' => 'F 1 ZZ']));

        $component = Livewire::actingAs($tenant)->test(Index::class);

        $component->set('keyword', $first->code)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$first->id]);
        $component->set('keyword', 'E001')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$second->id]);
        $component->set('keyword', 'D 7777')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$first->id]);
    }

    public function test_the_index_passes_the_option_lists(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->assertViewHas('periodOptions', fn ($options) => array_keys($options) === ['upcoming', 'running', 'ended'])
            ->assertViewHas('rentalTypeOptions', fn ($options) => array_keys($options) === [
                Const_Type::RENTAL_DAILY,
                Const_Type::RENTAL_MONTHLY,
            ]);
    }

    // --------------------------------------------------------------- Create

    public function test_the_create_page_is_forbidden_for_an_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('rental.create'))
            ->assertForbidden();
    }

    public function test_the_create_page_renders_for_a_tenant(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('rental.create'))
            ->assertOk()
            ->assertSeeLivewire(Create::class);
    }

    public function test_the_create_form_starts_with_sensible_defaults(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Create::class)
            ->assertSet('rental_type', Const_Type::RENTAL_DAILY)
            ->assertSet('days', 1)
            ->assertSet('start_date', '2026-11-10')
            ->assertViewHas('garages', fn ($garages) => $garages->isEmpty())
            ->assertViewHas('preview', null)
            ->assertViewHas('previewError', null);
    }

    public function test_the_vehicle_list_only_has_the_tenants_vehicles_with_labels(): void
    {
        $tenant = User::factory()->create();
        $car = $this->carOf($tenant, ['plate_number' => 'B 1234 XY', 'brand' => 'Toyota', 'model' => 'Avanza']);
        $bicycle = Vehicle::factory()->bicycle()->for($tenant)->create(['brand' => 'Polygon', 'model' => 'Xtrada']);
        Vehicle::factory()->create();

        Livewire::actingAs($tenant)
            ->test(Create::class)
            ->assertViewHas('vehicles', fn ($vehicles) => $vehicles === [
                $bicycle->id => 'Bicycle - Polygon Xtrada',
                $car->id => 'B 1234 XY - Toyota Avanza',
            ] || $vehicles === [
                $car->id => 'B 1234 XY - Toyota Avanza',
                $bicycle->id => 'Bicycle - Polygon Xtrada',
            ]);
    }

    public function test_the_vehicle_must_belong_to_the_tenant(): void
    {
        $tenant = User::factory()->create();
        $garage = Garage::factory()->car()->create();
        $foreign = $this->carOf(User::factory()->create());

        $this->fillCreate($tenant, $this->validForm($foreign, $garage))
            ->call('submitDefault')
            ->assertHasErrors(['vehicle_id' => 'exists']);

        $this->assertSame(0, Rental::count());
    }

    public function test_the_vehicle_is_required(): void
    {
        $tenant = User::factory()->create();

        $this->fillCreate($tenant, ['payment_proof' => UploadedFile::fake()->image('proof.jpg')])
            ->call('submitDefault')
            ->assertHasErrors(['vehicle_id' => 'required', 'garage_id' => 'required']);
    }

    public function test_a_past_start_date_is_rejected(): void
    {
        $tenant = User::factory()->create();
        $vehicle = $this->carOf($tenant);

        $this->fillCreate($tenant, $this->validForm($vehicle, Garage::factory()->car()->create(), ['start_date' => '2026-11-09']))
            ->call('submitDefault')
            ->assertHasErrors(['start_date' => 'after_or_equal']);
    }

    public function test_days_must_be_between_one_and_thirty_one_for_a_daily_rental(): void
    {
        $tenant = User::factory()->create();
        $vehicle = $this->carOf($tenant);
        $garage = Garage::factory()->car()->create();

        $this->fillCreate($tenant, $this->validForm($vehicle, $garage, ['days' => 0]))
            ->call('submitDefault')
            ->assertHasErrors(['days' => 'min']);

        $this->fillCreate($tenant, $this->validForm($vehicle, $garage, ['days' => 32]))
            ->call('submitDefault')
            ->assertHasErrors(['days' => 'max']);

        $this->fillCreate($tenant, $this->validForm($vehicle, $garage, ['days' => null]))
            ->call('submitDefault')
            ->assertHasErrors(['days' => 'required_if']);
    }

    public function test_days_are_not_needed_for_a_monthly_rental(): void
    {
        $tenant = User::factory()->create();

        $this->fillCreate($tenant, $this->validForm($this->carOf($tenant), Garage::factory()->car()->create(), [
            'rental_type' => Const_Type::RENTAL_MONTHLY,
            'days' => null,
        ]))
            ->call('submitDefault')
            ->assertHasNoErrors();

        $this->assertSame(30, Rental::firstOrFail()->days);
    }

    public function test_the_rental_type_must_be_daily_or_monthly(): void
    {
        $tenant = User::factory()->create();

        $this->fillCreate($tenant, $this->validForm($this->carOf($tenant), Garage::factory()->car()->create(), ['rental_type' => 'weekly']))
            ->call('submitDefault')
            ->assertHasErrors(['rental_type' => 'in']);
    }

    public function test_the_payment_proof_is_required_and_must_be_an_image(): void
    {
        $tenant = User::factory()->create();
        $vehicle = $this->carOf($tenant);
        $garage = Garage::factory()->car()->create();

        $this->fillCreate($tenant, $this->validForm($vehicle, $garage, ['payment_proof' => null]))
            ->call('submitDefault')
            ->assertHasErrors(['payment_proof' => 'required']);

        $this->fillCreate($tenant, $this->validForm($vehicle, $garage, [
            'payment_proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ]))
            ->call('submitDefault')
            ->assertHasErrors(['payment_proof' => 'image']);

        $this->assertSame(0, Rental::count());
    }

    public function test_the_garage_is_required(): void
    {
        $tenant = User::factory()->create();

        $this->fillCreate($tenant, $this->validForm($this->carOf($tenant), Garage::factory()->car()->create(), ['garage_id' => null]))
            ->call('submitDefault')
            ->assertHasErrors(['garage_id' => 'required']);
    }

    public function test_the_garage_must_exist(): void
    {
        $tenant = User::factory()->create();

        $this->fillCreate($tenant, $this->validForm($this->carOf($tenant), Garage::factory()->car()->create(), ['garage_id' => 'missing']))
            ->call('submitDefault')
            ->assertHasErrors(['garage_id' => 'exists']);
    }

    public function test_the_garages_list_has_only_free_garages_that_fit_a_car(): void
    {
        $tenant = User::factory()->create();
        $vehicle = $this->carOf($tenant);
        $booked = Garage::factory()->car()->create(['code' => 'C001']);
        Garage::factory()->car()->create(['code' => 'C002']);
        Garage::factory()->electricCar()->create(['code' => 'E001']);
        Garage::factory()->bicycle()->create(['code' => 'S001']);
        $this->rent($booked, $this->carOf(User::factory()->create())); // 10 to 12 Nov

        $this->fillCreate($tenant, ['vehicle_id' => $vehicle->id])
            ->set('days', 2)
            ->assertViewHas('garages', fn ($garages) => $garages->pluck('code')->all() === ['C002']);
    }

    public function test_an_electric_car_can_pick_car_and_electric_garages_sorted_by_code(): void
    {
        $tenant = User::factory()->create();
        $vehicle = Vehicle::factory()->electricCar()->for($tenant)->create();
        Garage::factory()->electricCar()->create(['code' => 'E001']);
        Garage::factory()->car()->create(['code' => 'C001']);
        Garage::factory()->bicycle()->create(['code' => 'S001']);

        $this->fillCreate($tenant, ['vehicle_id' => $vehicle->id])
            ->assertViewHas('garages', fn ($garages) => $garages->pluck('code')->all() === ['C001', 'E001']);
    }

    public function test_a_garage_booked_in_the_chosen_dates_is_listed_again_for_other_dates(): void
    {
        $tenant = User::factory()->create();
        $vehicle = $this->carOf($tenant);
        $garage = Garage::factory()->car()->create(['code' => 'C001']);
        $this->rent($garage, $this->carOf(User::factory()->create())); // 10 to 12 Nov

        $this->fillCreate($tenant, ['vehicle_id' => $vehicle->id, 'start_date' => '2026-11-13'])
            ->assertViewHas('garages', fn ($garages) => $garages->pluck('code')->all() === ['C001']);
    }

    public function test_the_period_and_preview_match_what_create_saves(): void
    {
        $tenant = User::factory()->create();
        $vehicle = $this->carOf($tenant);
        $garage = Garage::factory()->car()->create(['daily_price' => 100000]);

        $component = $this->fillCreate($tenant, ['vehicle_id' => $vehicle->id, 'days' => 4, 'garage_id' => $garage->id])
            ->assertViewHas('period', ['start_date' => '2026-11-10', 'end_date' => '2026-11-13', 'days' => 4])
            ->assertViewHas('previewError', null);

        $preview = $component->viewData('preview');

        $component->set('payment_proof', UploadedFile::fake()->image('proof.jpg'))
            ->call('submitDefault')
            ->assertHasNoErrors();

        $rental = Rental::firstOrFail();

        $this->assertSame($rental->start_date->toDateString(), $preview['start_date']);
        $this->assertSame($rental->end_date->toDateString(), $preview['end_date']);
        $this->assertSame($rental->days, $preview['days']);
        $this->assertSame((float) $rental->daily_price, $preview['daily_price']);
        $this->assertSame((float) $rental->rent_amount, $preview['rent_amount']);
        $this->assertSame($rental->rental_type, $preview['rental_type']);
        $this->assertSame(400000.0, $preview['rent_amount']);
    }

    public function test_too_many_days_gives_a_preview_error_instead_of_an_exception(): void
    {
        $tenant = User::factory()->create();
        $vehicle = $this->carOf($tenant);
        $garage = Garage::factory()->car()->create();

        $this->fillCreate($tenant, ['vehicle_id' => $vehicle->id, 'days' => 40, 'garage_id' => $garage->id])
            ->assertOk()
            ->assertViewHas('previewError', 'Days must be between 1 and 29 for this month.')
            ->assertViewHas('preview', null)
            ->assertViewHas('period', null)
            ->assertViewHas('garages', fn ($garages) => $garages->isEmpty());
    }

    public function test_changing_the_dates_resets_a_garage_that_is_no_longer_free(): void
    {
        $tenant = User::factory()->create();
        $vehicle = $this->carOf($tenant);
        $garage = Garage::factory()->car()->create(['code' => 'C001']);
        $this->rent($garage, $this->carOf(User::factory()->create())); // 10 to 12 Nov

        $component = $this->fillCreate($tenant, ['vehicle_id' => $vehicle->id, 'start_date' => '2026-11-20', 'garage_id' => $garage->id])
            ->assertSet('garage_id', $garage->id);

        $component->set('days', 2)->assertSet('garage_id', $garage->id);

        $component->set('start_date', '2026-11-11')->assertSet('garage_id', null);
    }

    public function test_changing_the_vehicle_resets_a_garage_that_does_not_fit(): void
    {
        $tenant = User::factory()->create();
        $electric = Vehicle::factory()->electricCar()->for($tenant)->create();
        $bicycle = Vehicle::factory()->bicycle()->for($tenant)->create();
        $garage = Garage::factory()->car()->create();

        $this->fillCreate($tenant, ['vehicle_id' => $electric->id, 'garage_id' => $garage->id])
            ->assertSet('garage_id', $garage->id)
            ->set('vehicle_id', $bicycle->id)
            ->assertSet('garage_id', null);
    }

    /**
     * Create free car garages with codes C001, C002, ...
     *
     * @return list<Garage>
     */
    private function carGarages(int $count): array
    {
        $garages = [];

        for ($i = 1; $i <= $count; $i++) {
            $garages[] = Garage::factory()->car()->create(['code' => 'C'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        }

        return $garages;
    }

    public function test_only_the_first_twelve_free_garages_are_listed_with_the_total(): void
    {
        $tenant = User::factory()->create();
        $this->carGarages(30);

        $this->fillCreate($tenant, ['vehicle_id' => $this->carOf($tenant)->id])
            ->assertSet('garage_limit', 12)
            ->assertViewHas('garages', fn ($garages) => $garages->count() === 12 && $garages->first()->code === 'C001')
            ->assertViewHas('garagesTotal', 30)
            ->assertViewHas('garagesShown', 12)
            ->assertViewHas('hasMoreGarages', true);
    }

    public function test_show_more_garages_adds_twelve(): void
    {
        $tenant = User::factory()->create();
        $this->carGarages(30);

        $component = $this->fillCreate($tenant, ['vehicle_id' => $this->carOf($tenant)->id])
            ->call('showMoreGarages')
            ->assertSet('garage_limit', 24)
            ->assertViewHas('garagesShown', 24)
            ->assertViewHas('hasMoreGarages', true);

        $component->call('showMoreGarages')
            ->assertViewHas('garagesShown', 30)
            ->assertViewHas('hasMoreGarages', false);
    }

    public function test_searching_by_code_narrows_the_list_and_the_total_and_resets_the_limit(): void
    {
        $tenant = User::factory()->create();
        $this->carGarages(30);

        $this->fillCreate($tenant, ['vehicle_id' => $this->carOf($tenant)->id])
            ->call('showMoreGarages')
            ->set('garage_search', 'c02')
            ->assertSet('garage_limit', 12)
            ->assertViewHas('garages', fn ($garages) => $garages->count() === 10 && $garages->first()->code === 'C020')
            ->assertViewHas('garagesTotal', 10)
            ->assertViewHas('hasMoreGarages', false);
    }

    public function test_selecting_a_garage_does_not_change_the_order_of_the_list(): void
    {
        $tenant = User::factory()->create();
        $garages = $this->carGarages(5);

        $this->fillCreate($tenant, ['vehicle_id' => $this->carOf($tenant)->id, 'garage_id' => $garages[2]->id])
            ->assertSet('garage_id', $garages[2]->id)
            ->assertViewHas('garages', fn ($list) => $list->pluck('code')->all() === ['C001', 'C002', 'C003', 'C004', 'C005']);
    }

    public function test_changing_the_vehicle_or_dates_resets_the_garage_limit(): void
    {
        $tenant = User::factory()->create();
        $this->carGarages(30);
        $vehicle = $this->carOf($tenant);
        $other = $this->carOf($tenant);

        $component = $this->fillCreate($tenant, ['vehicle_id' => $vehicle->id]);

        foreach ([['vehicle_id', $other->id], ['start_date', '2026-11-12'], ['days', 2], ['rental_type', Const_Type::RENTAL_MONTHLY]] as [$property, $value]) {
            $component->call('showMoreGarages')->assertSet('garage_limit', 24);
            $component->set($property, $value)->assertSet('garage_limit', 12);
        }
    }

    public function test_a_selected_garage_that_is_no_longer_free_is_still_cleared_with_many_garages(): void
    {
        $tenant = User::factory()->create();
        $garages = $this->carGarages(30);
        $this->rent($garages[24], $this->carOf(User::factory()->create())); // 10 to 12 Nov

        $this->fillCreate($tenant, ['vehicle_id' => $this->carOf($tenant)->id, 'start_date' => '2026-11-20', 'garage_id' => $garages[24]->id])
            ->assertSet('garage_id', $garages[24]->id)
            ->set('start_date', '2026-11-11')
            ->assertSet('garage_id', null)
            ->assertViewHas('garagesTotal', 29);
    }

    public function test_the_number_of_queries_on_render_does_not_grow_with_the_free_garages(): void
    {
        $tenant = User::factory()->create();
        $vehicle = $this->carOf($tenant);
        $garages = $this->carGarages(5);

        $countQueries = function () use ($tenant, $vehicle, $garages): int {
            $component = $this->fillCreate($tenant, ['vehicle_id' => $vehicle->id, 'garage_id' => $garages[4]->id]);

            DB::flushQueryLog();
            DB::enableQueryLog();
            $component->call('$refresh');
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $withFive = $countQueries();

        for ($i = 6; $i <= 30; $i++) {
            Garage::factory()->car()->create(['code' => 'C'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        }

        $this->assertSame($withFive, $countQueries());
    }

    public function test_a_valid_submit_creates_the_rental_stores_the_proof_and_redirects(): void
    {
        $tenant = User::factory()->create();
        $vehicle = $this->carOf($tenant);
        $garage = Garage::factory()->car()->create(['daily_price' => 100000]);

        $this->fillCreate($tenant, $this->validForm($vehicle, $garage))
            ->call('submitDefault')
            ->assertHasNoErrors();

        $rental = Rental::firstOrFail();

        $this->assertSame(Const_Status::NEW, $rental->status);
        $this->assertSame($tenant->id, $rental->user_id);
        $this->assertSame($vehicle->id, $rental->vehicle_id);
        $this->assertSame($garage->id, $rental->garage_id);
        $this->assertSame('2026-11-10', $rental->start_date->toDateString());
        $this->assertSame('2026-11-12', $rental->end_date->toDateString());
        $this->assertStringStartsWith('payment-proofs/', $rental->payment_proof_path);
        Storage::disk('public')->assertExists($rental->payment_proof_path);

        $this->fillCreate($tenant, $this->validForm($vehicle, Garage::factory()->car()->create(), ['start_date' => '2026-11-20']))
            ->call('submitDefault')
            ->assertRedirect(route('rental.show', Rental::query()->where('id', '!=', $rental->id)->firstOrFail()));
    }

    public function test_a_double_booking_shows_an_error_and_saves_nothing(): void
    {
        $tenant = User::factory()->create();
        $vehicle = $this->carOf($tenant);
        $garage = Garage::factory()->car()->create();
        $this->rent($garage, $this->carOf(User::factory()->create())); // 10 to 12 Nov

        $this->fillCreate($tenant, $this->validForm($vehicle, $garage))
            ->call('submitDefault')
            ->assertHasErrors('flash_danger');

        $this->assertSame(1, Rental::count());
        $this->assertSame([], Storage::disk('public')->allFiles('payment-proofs'));
    }

    // ----------------------------------------------------------------- Show

    public function test_the_owner_can_view_their_rental(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant));

        $this->actingAs($tenant)
            ->get(route('rental.show', $rental))
            ->assertOk()
            ->assertSeeLivewire(Show::class);
    }

    public function test_another_tenant_cannot_view_the_rental(): void
    {
        $rental = $this->rent(Garage::factory()->car()->create(), $this->carOf(User::factory()->create()));

        $this->actingAs(User::factory()->create())
            ->get(route('rental.show', $rental))
            ->assertForbidden();
    }

    public function test_an_admin_can_view_any_rental(): void
    {
        $rental = $this->rent(Garage::factory()->car()->create(), $this->carOf(User::factory()->create()));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('rental.show', $rental))
            ->assertOk();
    }

    public function test_an_extension_row_gives_not_found(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant));
        $extension = RentalService::extend($rental, '2026-11-14', 'payment-proofs/ext.jpg');

        $this->actingAs($tenant)
            ->get(route('rental.show', $extension))
            ->assertNotFound();
    }

    public function test_show_passes_extensions_chargings_and_totals(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rent(Garage::factory()->car()->create(['daily_price' => 100000]), $this->carOf($tenant)); // 10 to 12
        $first = RentalService::extend($rental, '2026-11-14', 'payment-proofs/ext1.jpg'); // 200000
        $second = RentalService::extend($rental, '2026-11-15', 'payment-proofs/ext2.jpg'); // 100000
        RentalService::extend($this->rent(Garage::factory()->car()->create(), $this->carOf($tenant)), '2026-11-13', 'payment-proofs/other.jpg');

        $older = ChargingTransaction::factory()->create(['rental_id' => $rental->id, 'amount' => 50000, 'created_at' => '2026-11-10 08:00:00']);
        $newer = ChargingTransaction::factory()->create(['rental_id' => $rental->id, 'amount' => 30000, 'created_at' => '2026-11-10 09:00:00']);

        Livewire::actingAs($tenant)
            ->test(Show::class, ['obj' => $rental])
            ->assertViewHas('extensions', fn ($rows) => $rows->pluck('id')->all() === [$first->id, $second->id])
            ->assertViewHas('chargings', fn ($rows) => $rows->pluck('id')->all() === [$newer->id, $older->id])
            ->assertViewHas('latestEndDate', fn ($date) => $date->toDateString() === '2026-11-15')
            ->assertViewHas('totalRent', 600000.0)
            ->assertViewHas('totalCharging', 80000.0)
            ->assertViewHas('grandTotal', 680000.0)
            ->assertViewHas('isActive', true);
    }

    public function test_the_admin_sees_the_extend_button_beside_back_to_list_on_a_rental_that_has_not_ended(): void
    {
        $rental = $this->rent(Garage::factory()->car()->create(), $this->carOf(User::factory()->create()));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('rental.show', $rental->id))
            ->assertOk()
            ->assertSee('Extend rental')
            ->assertSee(route('extension.create', ['rental' => $rental->id]), false);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Show::class, ['obj' => $rental])
            ->assertViewHas('canExtend', true);
    }

    public function test_the_extend_button_is_hidden_from_the_tenant_and_on_an_ended_rental(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant));

        $this->actingAs($tenant)->get(route('rental.show', $rental->id))->assertOk()->assertDontSee('Extend rental');

        Carbon::setTestNow('2026-11-13 09:00:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('rental.show', $rental->id))
            ->assertOk()
            ->assertDontSee('Extend rental');
    }

    public function test_show_reports_an_ended_rental_as_not_active(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rent(Garage::factory()->car()->create(), $this->carOf($tenant)); // 10 to 12

        Carbon::setTestNow('2026-11-13 09:00:00');

        Livewire::actingAs($tenant)
            ->test(Show::class, ['obj' => $rental])
            ->assertViewHas('isActive', false);
    }

    public function test_the_owner_can_charge_an_active_electric_rental_in_an_electric_garage(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rent(
            Garage::factory()->electricCar()->create(),
            Vehicle::factory()->electricCar()->for($tenant)->create(),
        );

        Livewire::actingAs($tenant)
            ->test(Show::class, ['obj' => $rental])
            ->assertViewHas('canCharge', true);
    }

    public function test_charging_is_not_offered_in_a_car_garage(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rent(
            Garage::factory()->car()->create(),
            Vehicle::factory()->electricCar()->for($tenant)->create(),
        );

        Livewire::actingAs($tenant)
            ->test(Show::class, ['obj' => $rental])
            ->assertViewHas('canCharge', false);
    }

    public function test_charging_is_not_offered_once_the_rental_has_ended(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rent(
            Garage::factory()->electricCar()->create(),
            Vehicle::factory()->electricCar()->for($tenant)->create(),
        );

        Carbon::setTestNow('2026-11-13 09:00:00');

        Livewire::actingAs($tenant)
            ->test(Show::class, ['obj' => $rental])
            ->assertViewHas('canCharge', false);
    }

    public function test_charging_is_not_offered_to_an_admin(): void
    {
        $rental = $this->rent(
            Garage::factory()->electricCar()->create(),
            Vehicle::factory()->electricCar()->for(User::factory()->create())->create(),
        );

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Show::class, ['obj' => $rental])
            ->assertViewHas('canCharge', false);
    }
}
