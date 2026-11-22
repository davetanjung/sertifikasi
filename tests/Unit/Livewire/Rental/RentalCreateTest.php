<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Rental;

use App\Livewire\Rental\Create;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RentalCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-11-10 09:00:00');
        Storage::fake('public');
        $this->tenant = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function page(?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->tenant)->test(Create::class);
    }

    private function car(?User $owner = null, array $attributes = []): Vehicle
    {
        return Vehicle::factory()->car()->for($owner ?? $this->tenant)->create($attributes);
    }

    private function carGarage(string $code, int $price = 100000): Garage
    {
        return Garage::factory()->car()->create(['code' => $code, 'daily_price' => $price]);
    }

    private function book(Garage $garage, string $start, string $end): void
    {
        Rental::factory()->create(['garage_id' => $garage->id, 'start_date' => $start, 'end_date' => $end]);
    }

    public function test_mount_refuses_admins_and_starts_with_today_daily_one_day(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())->test(Create::class)->assertForbidden();

        $this->page()->assertOk()
            ->assertSet('start_date', '2026-11-10')
            ->assertSet('rental_type', Const_Type::RENTAL_DAILY)
            ->assertSet('days', 1)
            ->assertSet('garage_limit', 12);
    }

    public function test_vehicle_options_are_only_the_tenants_own_with_labels(): void
    {
        $withPlate = $this->car(null, ['plate_number' => 'B 1 AA', 'brand' => 'Toyota', 'model' => 'Avanza']);
        $bicycle = Vehicle::factory()->bicycle()->for($this->tenant)->create(['brand' => 'Polygon', 'model' => 'Heist']);
        $this->car(User::factory()->create());

        $this->page()->assertViewHas('vehicles', [
            $bicycle->id => 'Bicycle - Polygon Heist',
            $withPlate->id => 'B 1 AA - Toyota Avanza',
        ]);
    }

    public function test_render_gives_the_rental_type_options(): void
    {
        $this->page()->assertViewHas('rentalTypeOptions', [Const_Type::RENTAL_DAILY => 'Daily', Const_Type::RENTAL_MONTHLY => 'Monthly']);
    }

    public function test_the_garage_list_is_empty_without_a_vehicle_and_lists_free_fitting_garages_with_one(): void
    {
        $this->carGarage('C002');
        $first = $this->carGarage('C001');
        Garage::factory()->bicycle()->create();
        $booked = $this->carGarage('C003');
        $this->book($booked, '2026-11-10', '2026-11-10');
        $vehicle = $this->car();

        $this->page()->assertViewHas('garages', fn ($garages) => $garages->isEmpty())->assertViewHas('garagesTotal', 0);

        $this->page()->set('vehicle_id', $vehicle->id)
            ->assertViewHas('garages', fn ($garages) => $garages->first()->id === $first->id && $garages->pluck('code')->all() === ['C001', 'C002'])
            ->assertViewHas('garagesTotal', 2)
            ->assertViewHas('garagesShown', 2)
            ->assertViewHas('hasMoreGarages', false);
    }

    public function test_the_garage_list_ignores_another_tenants_vehicle(): void
    {
        $this->carGarage('C001');

        $this->page()->set('vehicle_id', $this->car(User::factory()->create())->id)->assertViewHas('garagesTotal', 0);
    }

    public function test_the_garage_list_is_searchable_and_limited_with_show_more(): void
    {
        foreach (range(1, 14) as $number) {
            $this->carGarage(sprintf('C%03d', $number));
        }
        $vehicle = $this->car();

        $page = $this->page()->set('vehicle_id', $vehicle->id)
            ->assertViewHas('garagesShown', 12)->assertViewHas('garagesTotal', 14)->assertViewHas('hasMoreGarages', true);

        $page->call('showMoreGarages')->assertSet('garage_limit', 24)->assertViewHas('garagesShown', 14)->assertViewHas('hasMoreGarages', false);

        $page->set('garage_search', 'c013')->assertViewHas('garagesTotal', 1)->assertSet('garage_limit', 12);
    }

    public function test_the_garage_list_follows_the_start_date_and_days(): void
    {
        $garage = $this->carGarage('C001');
        $this->book($garage, '2026-11-12', '2026-11-13');
        $vehicle = $this->car();

        $this->page()->set('vehicle_id', $vehicle->id)->set('days', 2)->assertViewHas('garagesTotal', 1);
        $this->page()->set('vehicle_id', $vehicle->id)->set('days', 3)->assertViewHas('garagesTotal', 0);
        $this->page()->set('vehicle_id', $vehicle->id)->set('start_date', '2026-11-14')->set('days', 5)->assertViewHas('garagesTotal', 1);
    }

    public function test_render_previews_the_dates_and_price_of_the_chosen_garage(): void
    {
        $garage = $this->carGarage('C001', 100000);

        $this->page()->set('days', 3)->set('garage_id', $garage->id)
            ->assertViewHas('period', ['start_date' => '2026-11-10', 'end_date' => '2026-11-12', 'days' => 3])
            ->assertViewHas('preview', fn ($preview) => $preview['rent_amount'] === 300000.0 && $preview['daily_price'] === 100000.0)
            ->assertViewHas('previewError', null);
    }

    public function test_render_has_a_period_but_no_preview_without_a_garage(): void
    {
        $this->page()->set('days', 2)->assertViewHas('period', fn ($period) => $period['days'] === 2)->assertViewHas('preview', null);
    }

    public function test_render_shows_a_preview_error_for_a_bad_period(): void
    {
        $this->page()->set('start_date', '2026-11-01')->assertViewHas('previewError', 'Start date cannot be before today.')->assertViewHas('period', null);
        $this->page()->set('days', 30)->assertViewHas('previewError', 'Days must be between 1 and 29 for this month.');
        $this->page()->set('start_date', 'garbage')->assertViewHas('previewError', 'Start date is not a valid date.');
    }

    public function test_render_prices_a_monthly_rental_at_25_times_the_daily_price(): void
    {
        $garage = $this->carGarage('C001', 100000);

        $this->page()->set('rental_type', Const_Type::RENTAL_MONTHLY)->set('garage_id', $garage->id)
            ->assertViewHas('preview', fn ($preview) => $preview['days'] === 30 && $preview['rent_amount'] === 2500000.0);
    }

    public function test_updated_clears_a_garage_that_is_no_longer_free_for_the_new_dates(): void
    {
        $garage = $this->carGarage('C001');
        $this->book($garage, '2026-11-15', '2026-11-16');
        $vehicle = $this->car();

        $this->page()->set('vehicle_id', $vehicle->id)->set('garage_id', $garage->id)->set('days', 3)
            ->assertSet('garage_id', $garage->id)
            ->set('days', 6)
            ->assertSet('garage_id', null);
    }

    public function test_updated_clears_a_garage_that_does_not_fit_the_new_vehicle(): void
    {
        $garage = $this->carGarage('C001');
        $bicycle = Vehicle::factory()->bicycle()->for($this->tenant)->create();

        $this->page()->set('vehicle_id', $this->car()->id)->set('garage_id', $garage->id)->set('vehicle_id', $bicycle->id)->assertSet('garage_id', null);
    }

    public function test_updated_resets_the_garage_limit_when_the_search_or_dates_change(): void
    {
        $page = $this->page()->call('showMoreGarages')->assertSet('garage_limit', 24);

        $page->set('garage_search', 'C')->assertSet('garage_limit', 12);
        $page->call('showMoreGarages')->set('start_date', '2026-11-11')->assertSet('garage_limit', 12);
    }

    public function test_updated_keeps_a_garage_when_an_unrelated_field_changes(): void
    {
        $garage = $this->carGarage('C001');

        $this->page()->set('garage_id', $garage->id)->set('garage_search', 'zzz')->assertSet('garage_id', $garage->id);
    }

    public function test_rules_check_every_field(): void
    {
        $proof = UploadedFile::fake()->image('proof.jpg');
        $garage = $this->carGarage('C001');

        $this->page()->call('submitDefault')->assertHasErrors(['vehicle_id' => 'required', 'garage_id' => 'required', 'payment_proof' => 'required']);
        $this->page()->set('vehicle_id', $this->car(User::factory()->create())->id)->call('submitDefault')->assertHasErrors(['vehicle_id' => 'exists']);
        $this->page()->set('rental_type', 'weekly')->call('submitDefault')->assertHasErrors(['rental_type' => 'in']);
        $this->page()->set('days', 0)->call('submitDefault')->assertHasErrors(['days' => 'min']);
        $this->page()->set('days', 32)->call('submitDefault')->assertHasErrors(['days' => 'max']);
        $this->page()->set('start_date', '2026-11-09')->call('submitDefault')->assertHasErrors(['start_date' => 'after_or_equal']);
        $this->page()->set('start_date', '10-11-2026')->call('submitDefault')->assertHasErrors(['start_date' => 'date_format']);
        $this->page()->set('payment_proof', UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'))->call('submitDefault')->assertHasErrors(['payment_proof' => 'image']);
        $this->page()->set('rental_type', Const_Type::RENTAL_MONTHLY)->set('days', null)->set('vehicle_id', $this->car()->id)->set('garage_id', $garage->id)->set('payment_proof', $proof)
            ->call('submitDefault')->assertHasNoErrors(['days']);
    }

    public function test_submit_saves_the_rental_stores_the_proof_and_goes_to_it(): void
    {
        $garage = $this->carGarage('C001', 100000);
        $vehicle = $this->car();

        $this->page()
            ->set('vehicle_id', $vehicle->id)->set('garage_id', $garage->id)->set('days', 3)
            ->set('payment_proof', UploadedFile::fake()->image('proof.jpg'))
            ->call('submitDefault')
            ->assertHasNoErrors();

        $rental = Rental::firstOrFail();
        $this->assertSame($this->tenant->id, $rental->user_id);
        $this->assertSame('300000.00', $rental->rent_amount);
        $this->assertStringStartsWith('payment-proofs/', $rental->payment_proof_path);
        Storage::disk('public')->assertExists($rental->payment_proof_path);
    }

    public function test_submit_deletes_the_proof_and_shows_an_error_when_the_service_refuses(): void
    {
        $garage = $this->carGarage('C001');
        $vehicle = $this->car();

        $page = $this->page()->set('vehicle_id', $vehicle->id)->set('garage_id', $garage->id)->set('days', 3)
            ->set('payment_proof', UploadedFile::fake()->image('proof.jpg'));

        $this->book($garage, '2026-11-10', '2026-11-12'); // booked meanwhile

        $page->call('submitDefault')->assertHasErrors('flash_danger');

        $this->assertDatabaseCount('rentals', 1);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
