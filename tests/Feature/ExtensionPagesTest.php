<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Extension\Create;
use App\Livewire\Extension\Index;
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

class ExtensionPagesTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Rent through the service (a daily rental of 3 days starting today: 10 to 12 Nov, 100000 per day).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function rent(?Garage $garage = null, ?Vehicle $vehicle = null, array $overrides = []): Rental
    {
        $garage ??= Garage::factory()->car()->create(['daily_price' => 100000]);
        $vehicle ??= Vehicle::factory()->car()->for(User::factory()->create())->create();

        return RentalService::create(array_merge([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 3,
            'payment_proof_path' => 'payment-proofs/new.jpg',
        ], $overrides));
    }

    private function createPage(Rental|string $rental)
    {
        return Livewire::actingAs($this->admin())
            ->withQueryParams(['rental' => $rental instanceof Rental ? $rental->id : $rental])
            ->test(Create::class);
    }

    // ---------------------------------------------------------- Access

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('extension.index'))->assertRedirect(route('login'));
        $this->get(route('extension.create'))->assertRedirect(route('login'));
    }

    public function test_a_tenant_gets_403_on_the_routes(): void
    {
        $tenant = User::factory()->create();

        $this->actingAs($tenant)->get(route('extension.index'))->assertForbidden();
        $this->actingAs($tenant)->get(route('extension.create'))->assertForbidden();
    }

    public function test_a_tenant_gets_403_on_the_components(): void
    {
        $rental = $this->rent();
        $tenant = $rental->user;

        Livewire::actingAs($tenant)->test(Index::class)->assertForbidden();
        Livewire::actingAs($tenant)
            ->withQueryParams(['rental' => $rental->id])
            ->test(Create::class)
            ->assertForbidden();
    }

    public function test_an_admin_can_open_the_pages(): void
    {
        $rental = $this->rent();

        $this->actingAs($this->admin())->get(route('extension.index'))->assertOk()->assertSeeLivewire(Index::class);
        $this->actingAs($this->admin())->get(route('extension.create', ['rental' => $rental->id]))
            ->assertOk()
            ->assertSeeLivewire(Create::class);
    }

    // ----------------------------------------------------------- Index

    public function test_the_index_lists_only_rentals_running_today(): void
    {
        $running = $this->rent();
        $this->rent(null, null, ['start_date' => '2026-11-20', 'days' => 2]); // future
        $this->rent(null, null, ['days' => 1]); // 10 Nov only, ended tomorrow
        Carbon::setTestNow('2026-11-11 09:00:00');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('rentals', fn ($rentals) => $rentals->pluck('id')->all() === [$running->id]);
    }

    public function test_the_index_does_not_list_extension_rows_twice(): void
    {
        $rental = $this->rent();
        RentalService::extend($rental, '2026-11-14', 'extension-proofs/a.jpg');
        Carbon::setTestNow('2026-11-13 09:00:00'); // the new row ended, the extension is running

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('rentals', fn ($rentals) => $rentals->pluck('id')->all() === [$rental->id]
                && $rentals->first()->status === Const_Status::NEW);
    }

    public function test_the_latest_end_date_includes_extensions(): void
    {
        $plain = $this->rent();
        $extended = $this->rent();
        RentalService::extend($extended, '2026-11-15', 'extension-proofs/a.jpg');
        RentalService::extend($extended, '2026-11-18', 'extension-proofs/b.jpg');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('rentals', fn ($rentals) => $rentals->firstWhere('id', $plain->id)->latest_end_date === '2026-11-12'
                && $rentals->firstWhere('id', $extended->id)->latest_end_date === '2026-11-18');
    }

    public function test_the_keyword_filters_by_rental_code_garage_code_tenant_and_plate(): void
    {
        $first = $this->rent(null, Vehicle::factory()->car()->for(User::factory()->create(['name' => 'Alice Wonder']))->create(['plate_number' => 'B 1111 AA']));
        $second = $this->rent(null, Vehicle::factory()->car()->for(User::factory()->create(['name' => 'Bob Builder']))->create(['plate_number' => 'D 2222 BB']));

        $keywords = [
            $first->code => [$first->id],
            $second->garage->code => [$second->id],
            'alice' => [$first->id],
            'd 2222' => [$second->id],
            'no match at all' => [],
        ];

        foreach ($keywords as $keyword => $expected) {
            Livewire::actingAs($this->admin())
                ->test(Index::class)
                ->set('keyword', $keyword)
                ->assertViewHas('rentals', fn ($rentals) => $rentals->pluck('id')->all() === $expected);
        }

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('rentals', fn ($rentals) => $rentals->count() === 2);
    }

    public function test_the_index_uses_a_fixed_number_of_queries(): void
    {
        $admin = $this->admin();
        $this->rent();
        $this->rent();

        DB::enableQueryLog();
        Livewire::actingAs($admin)->test(Index::class);
        $few = count(DB::getQueryLog());

        foreach (range(1, 4) as $unused) {
            $this->rent();
        }

        DB::flushQueryLog();
        Livewire::actingAs($admin)->test(Index::class)
            ->assertViewHas('rentals', fn ($rentals) => $rentals->count() === 6);
        $many = count(DB::getQueryLog());

        $this->assertSame($few, $many);
    }

    // ---------------------------------------------------------- Create

    public function test_a_missing_rental_gives_404(): void
    {
        $this->createPage('00000000-0000-0000-0000-000000000000')->assertNotFound();
        $this->createPage('not-an-id')->assertNotFound();

        Livewire::actingAs($this->admin())->test(Create::class)->assertNotFound();
    }

    public function test_an_extension_row_gives_404(): void
    {
        $rental = $this->rent();
        $extension = RentalService::extend($rental, '2026-11-14', 'extension-proofs/a.jpg');

        $this->createPage($extension)->assertNotFound();
    }

    public function test_the_page_opens_with_the_rental_and_the_latest_end_date(): void
    {
        $rental = $this->rent();
        RentalService::extend($rental, '2026-11-14', 'extension-proofs/a.jpg');

        $this->createPage($rental)
            ->assertOk()
            ->assertViewHas('rental', fn ($viewRental) => $viewRental->id === $rental->id
                && $viewRental->relationLoaded('garage')
                && $viewRental->relationLoaded('vehicle')
                && $viewRental->relationLoaded('user'))
            ->assertViewHas('latestEndDate', '2026-11-14')
            ->assertViewHas('preview', null)
            ->assertViewHas('previewError', null)
            ->assertViewHas('blockedReason', null);
    }

    public function test_an_ended_rental_is_blocked(): void
    {
        $rental = $this->rent();
        Carbon::setTestNow('2026-11-20 09:00:00');

        $this->createPage($rental)
            ->set('new_end_date', '2026-11-25')
            ->assertViewHas('blockedReason', 'The rental has already ended and cannot be extended.')
            ->assertViewHas('preview', null);
    }

    public function test_a_rental_booked_in_the_future_can_be_extended(): void
    {
        $rental = $this->rent(null, null, ['start_date' => '2026-11-20', 'days' => 2]);

        $this->createPage($rental)
            ->set('new_end_date', '2026-11-23')
            ->assertViewHas('blockedReason', null)
            ->assertViewHas('preview', fn ($preview) => $preview['added_days'] === 2);
    }

    public function test_the_rules_reject_bad_input(): void
    {
        $rental = $this->rent(); // ends 12 Nov
        $proof = UploadedFile::fake()->image('proof.jpg');

        $this->createPage($rental)
            ->call('submitDefault')
            ->assertHasErrors(['new_end_date' => 'required', 'payment_proof' => 'required']);

        $this->createPage($rental)
            ->set('new_end_date', '2026-11-12')
            ->set('payment_proof', $proof)
            ->call('submitDefault')
            ->assertHasErrors(['new_end_date' => 'after']);

        $this->createPage($rental)
            ->set('new_end_date', '2026-11-01')
            ->set('payment_proof', $proof)
            ->call('submitDefault')
            ->assertHasErrors(['new_end_date' => 'after']);

        $this->createPage($rental)
            ->set('new_end_date', '15-11-2026')
            ->set('payment_proof', $proof)
            ->call('submitDefault')
            ->assertHasErrors(['new_end_date' => 'date_format']);

        $this->createPage($rental)
            ->set('new_end_date', '2026-11-15')
            ->call('submitDefault')
            ->assertHasErrors(['payment_proof' => 'required']);

        $this->createPage($rental)
            ->set('new_end_date', '2026-11-15')
            ->set('payment_proof', UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'))
            ->call('submitDefault')
            ->assertHasErrors(['payment_proof' => 'image']);

        $this->assertSame(1, Rental::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_after_rule_uses_the_latest_extension_end_date(): void
    {
        $rental = $this->rent();
        RentalService::extend($rental, '2026-11-15', 'extension-proofs/a.jpg');

        $this->createPage($rental)
            ->set('new_end_date', '2026-11-14')
            ->set('payment_proof', UploadedFile::fake()->image('proof.jpg'))
            ->call('submitDefault')
            ->assertHasErrors(['new_end_date' => 'after']);
    }

    public function test_the_preview_equals_what_extend_saves(): void
    {
        $rental = $this->rent();

        $component = $this->createPage($rental)
            ->set('new_end_date', '2026-11-15')
            ->assertViewHas('previewError', null)
            ->assertViewHas('preview', fn ($preview) => $preview['current_end_date'] === '2026-11-12'
                && $preview['start_date'] === '2026-11-13'
                && $preview['end_date'] === '2026-11-15'
                && $preview['added_days'] === 3
                && $preview['daily_price'] === 100000.0
                && $preview['rent_amount'] === 300000.0);

        $component
            ->set('payment_proof', UploadedFile::fake()->image('proof.jpg'))
            ->call('submitDefault')
            ->assertHasNoErrors();

        $extension = Rental::query()->where('status', Const_Status::EXTENSION)->firstOrFail();
        $this->assertSame(3, $extension->days);
        $this->assertEquals(300000, $extension->rent_amount);
    }

    public function test_the_preview_of_a_monthly_rental_is_added_days_times_the_daily_price(): void
    {
        $rental = $this->rent(null, null, ['rental_type' => Const_Type::RENTAL_MONTHLY, 'days' => null]); // 10 Nov to 9 Dec

        $this->createPage($rental)
            ->set('new_end_date', '2026-12-12')
            ->assertViewHas('preview', fn ($preview) => $preview['added_days'] === 3
                && $preview['rent_amount'] === 300000.0);
    }

    public function test_empty_or_badly_formatted_dates_give_no_preview_and_no_error(): void
    {
        $rental = $this->rent();

        foreach (['', 'abc', '15-11-2026', '2026-13-45'] as $value) {
            $this->createPage($rental)
                ->set('new_end_date', $value)
                ->assertViewHas('preview', null)
                ->assertViewHas('previewError', null);
        }
    }

    public function test_a_date_not_after_the_current_end_gives_a_preview_error_not_an_exception(): void
    {
        $this->createPage($this->rent())
            ->set('new_end_date', '2026-11-12')
            ->assertOk()
            ->assertViewHas('preview', null)
            ->assertViewHas('previewError', 'The new end date must be after the current end date.');
    }

    public function test_an_overlap_with_another_booking_gives_a_preview_error(): void
    {
        $garage = Garage::factory()->car()->create(['daily_price' => 100000]);
        $rental = $this->rent($garage);
        $this->rent($garage, null, ['start_date' => '2026-11-16', 'days' => 2]);

        $this->createPage($rental)
            ->set('new_end_date', '2026-11-17')
            ->assertViewHas('preview', null)
            ->assertViewHas('previewError', 'Garage is booked from 2026-11-16.');
    }

    public function test_a_valid_submit_creates_the_extension_stores_the_proof_and_redirects(): void
    {
        $rental = $this->rent();

        $this->createPage($rental)
            ->set('new_end_date', '2026-11-14')
            ->set('payment_proof', UploadedFile::fake()->image('proof.jpg'))
            ->call('submitDefault')
            ->assertHasNoErrors()
            ->assertRedirect(route('rental.show', $rental->id));

        $extension = Rental::query()->where('status', Const_Status::EXTENSION)->firstOrFail();
        $this->assertSame($rental->code, $extension->code);
        $this->assertSame('2026-11-13', $extension->start_date->toDateString());
        $this->assertSame('2026-11-14', $extension->end_date->toDateString());
        $this->assertSame(2, $extension->days);
        $this->assertEquals(200000, $extension->rent_amount);
        $this->assertStringStartsWith('extension-proofs/', $extension->payment_proof_path);
        Storage::disk('public')->assertExists($extension->payment_proof_path);
        $this->assertSame('Extension has been added.', session('flash_success'));
    }

    public function test_a_service_failure_shows_an_error_saves_nothing_and_leaves_no_file(): void
    {
        $garage = Garage::factory()->car()->create(['daily_price' => 100000]);
        $rental = $this->rent($garage);

        $component = $this->createPage($rental)
            ->set('new_end_date', '2026-11-14')
            ->set('payment_proof', UploadedFile::fake()->image('proof.jpg'));

        $this->rent($garage, null, ['start_date' => '2026-11-14', 'days' => 2]); // booked meanwhile

        $component->call('submitDefault')->assertHasErrors('flash_danger');

        $this->assertSame(0, Rental::query()->where('status', Const_Status::EXTENSION)->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
