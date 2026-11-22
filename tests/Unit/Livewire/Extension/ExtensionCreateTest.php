<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Extension;

use App\Livewire\Extension\Create;
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
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ExtensionCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-11-10 09:00:00');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * A daily rental of 3 days from today (10 to 12 Nov, 100000 per day).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function rent(?Garage $garage = null, array $overrides = []): Rental
    {
        $vehicle = Vehicle::factory()->car()->create();

        return RentalService::create(array_merge([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => ($garage ?? Garage::factory()->car()->create(['daily_price' => 100000]))->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 3,
            'payment_proof_path' => 'p.jpg',
        ], $overrides));
    }

    private function page(Rental|string $rental, ?User $user = null)
    {
        return Livewire::actingAs($user ?? User::factory()->admin()->create())
            ->withQueryParams(['rental' => $rental instanceof Rental ? $rental->id : $rental])
            ->test(Create::class);
    }

    public function test_mount_is_for_admins_and_needs_an_existing_new_rental(): void
    {
        $rental = $this->rent();
        $extension = RentalService::extend($rental, '2026-11-14', 'e.jpg');

        $this->page($rental, $rental->user)->assertForbidden();
        $this->page('00000000-0000-0000-0000-000000000000')->assertNotFound();
        $this->page($extension)->assertNotFound();
        Livewire::actingAs(User::factory()->admin()->create())->test(Create::class)->assertNotFound();
        $this->page($rental)->assertOk();
    }

    public function test_render_gives_the_rental_and_the_latest_end_date(): void
    {
        $rental = $this->rent();
        RentalService::extend($rental, '2026-11-14', 'e.jpg');

        $this->page($rental)
            ->assertViewHas('rental', fn ($view) => $view->id === $rental->id && $view->relationLoaded('garage') && $view->relationLoaded('vehicle') && $view->relationLoaded('user'))
            ->assertViewHas('latestEndDate', '2026-11-14')
            ->assertViewHas('blockedReason', null)
            ->assertViewHas('preview', null)
            ->assertViewHas('previewError', null)
            ->assertSeeHtml('min="2026-11-15"');
    }

    public function test_render_blocks_a_rental_that_has_ended(): void
    {
        $rental = $this->rent();
        Carbon::setTestNow('2026-11-20 09:00:00');

        $this->page($rental)->set('new_end_date', '2026-11-25')
            ->assertViewHas('blockedReason', 'The rental has already ended and cannot be extended.')
            ->assertViewHas('preview', null);
    }

    public function test_render_allows_extending_a_rental_booked_for_later(): void
    {
        $rental = $this->rent(null, ['start_date' => '2026-11-20', 'days' => 2]);

        $this->page($rental)->set('new_end_date', '2026-11-23')
            ->assertViewHas('blockedReason', null)
            ->assertViewHas('preview', fn ($preview) => $preview['added_days'] === 2);
    }

    public function test_render_previews_what_extend_would_save(): void
    {
        $this->page($this->rent())->set('new_end_date', '2026-11-15')
            ->assertViewHas('preview', [
                'current_end_date' => '2026-11-12',
                'start_date' => '2026-11-13',
                'end_date' => '2026-11-15',
                'added_days' => 3,
                'daily_price' => 100000.0,
                'rent_amount' => 300000.0,
            ])
            ->assertViewHas('previewError', null);
    }

    public function test_has_valid_date_gives_no_preview_for_empty_or_badly_formatted_dates(): void
    {
        $rental = $this->rent();

        foreach (['', 'abc', '15-11-2026', '2026-13-45'] as $value) {
            $this->page($rental)->set('new_end_date', $value)->assertViewHas('preview', null)->assertViewHas('previewError', null);
        }
    }

    public function test_render_shows_a_preview_error_instead_of_an_exception(): void
    {
        $garage = Garage::factory()->car()->create(['daily_price' => 100000]);
        $rental = $this->rent($garage);
        $this->rent($garage, ['start_date' => '2026-11-16', 'days' => 2]);

        $this->page($rental)->set('new_end_date', '2026-11-12')
            ->assertOk()->assertViewHas('previewError', 'The new end date must be after the current end date.');
        $this->page($rental)->set('new_end_date', '2026-11-17')
            ->assertViewHas('previewError', 'Garage is booked from 2026-11-16.');
    }

    public function test_rules_check_the_date_and_the_proof(): void
    {
        $rental = $this->rent(); // ends 12 Nov
        $proof = UploadedFile::fake()->image('proof.jpg');

        $this->page($rental)->call('submitDefault')->assertHasErrors(['new_end_date' => 'required', 'payment_proof' => 'required']);
        $this->page($rental)->set('new_end_date', '2026-11-12')->set('payment_proof', $proof)->call('submitDefault')->assertHasErrors(['new_end_date' => 'after']);
        $this->page($rental)->set('new_end_date', '15-11-2026')->set('payment_proof', $proof)->call('submitDefault')->assertHasErrors(['new_end_date' => 'date_format']);
        $this->page($rental)->set('new_end_date', '2026-11-15')->set('payment_proof', UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'))->call('submitDefault')->assertHasErrors(['payment_proof' => 'image']);
        $this->assertSame(1, Rental::count());
    }

    public function test_the_after_rule_counts_from_the_latest_extension(): void
    {
        $rental = $this->rent();
        RentalService::extend($rental, '2026-11-15', 'e.jpg');

        $this->page($rental)->set('new_end_date', '2026-11-14')->set('payment_proof', UploadedFile::fake()->image('p.jpg'))
            ->call('submitDefault')->assertHasErrors(['new_end_date' => 'after']);
    }

    public function test_submit_saves_the_extension_stores_the_proof_and_goes_back_to_the_rental(): void
    {
        $rental = $this->rent();

        $this->page($rental)->set('new_end_date', '2026-11-14')->set('payment_proof', UploadedFile::fake()->image('proof.jpg'))
            ->call('submitDefault')->assertHasNoErrors()->assertRedirect(route('rental.show', $rental->id));

        $extension = Rental::where('status', Const_Status::EXTENSION)->firstOrFail();
        $this->assertSame($rental->code, $extension->code);
        $this->assertSame('2026-11-14', $extension->end_date->toDateString());
        $this->assertSame('200000.00', $extension->rent_amount);
        $this->assertStringStartsWith('extension-proofs/', $extension->payment_proof_path);
        Storage::disk('public')->assertExists($extension->payment_proof_path);
        $this->assertSame('Extension has been added.', session('flash_success'));
    }

    public function test_submit_deletes_the_proof_and_shows_an_error_when_the_service_refuses(): void
    {
        $garage = Garage::factory()->car()->create(['daily_price' => 100000]);
        $rental = $this->rent($garage);
        $page = $this->page($rental)->set('new_end_date', '2026-11-14')->set('payment_proof', UploadedFile::fake()->image('proof.jpg'));

        $this->rent($garage, ['start_date' => '2026-11-14', 'days' => 2]);

        $page->call('submitDefault')->assertHasErrors('flash_danger');

        $this->assertSame(0, Rental::where('status', Const_Status::EXTENSION)->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
