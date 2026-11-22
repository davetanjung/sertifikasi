<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\ChargingTransaction;

use App\Livewire\ChargingTransaction\Create;
use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ChargingCreateTest extends TestCase
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

    /**
     * A running rental of an electric car (10 kW) in an electric garage (2.500 per kWh).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function rental(array $overrides = []): Rental
    {
        return Rental::factory()->create(array_merge([
            'user_id' => $this->tenant->id,
            'garage_id' => Garage::factory()->electricCar()->create(['price_per_kwh' => 2500])->id,
            'vehicle_id' => Vehicle::factory()->electricCar()->for($this->tenant)->create(['maximum_capacity_kw' => 10])->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
            'days' => 3,
        ], $overrides));
    }

    private function page(Rental|string $rental, ?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->tenant)
            ->withQueryParams(['rental' => $rental instanceof Rental ? $rental->id : $rental])
            ->test(Create::class);
    }

    public function test_mount_refuses_admins_unknown_rentals_extensions_and_other_tenants(): void
    {
        $rental = $this->rental();

        $this->page($rental, User::factory()->admin()->create())->assertForbidden();
        $this->page('00000000-0000-0000-0000-000000000000')->assertNotFound();
        $this->page(Rental::factory()->extension()->create(['user_id' => $this->tenant->id]))->assertNotFound();
        $this->page($rental, User::factory()->create())->assertForbidden();
        $this->page($rental)->assertOk();
    }

    public function test_render_gives_the_rental_maximum_and_no_block_for_a_running_electric_rental(): void
    {
        $rental = $this->rental();

        $this->page($rental)
            ->assertViewHas('rental', fn ($viewRental) => $viewRental->id === $rental->id)
            ->assertViewHas('maximumCapacity', 10.0)
            ->assertViewHas('blockedReason', null)
            ->assertViewHas('preview', null)
            ->assertViewHas('previewError', null);
    }

    public function test_blocked_reason_names_the_first_problem(): void
    {
        $notRunning = $this->rental(['start_date' => '2026-11-20', 'end_date' => '2026-11-22']);
        $this->page($notRunning)->assertViewHas('blockedReason', 'The rental is not running today.');

        $carGarage = $this->rental(['garage_id' => Garage::factory()->car()->create()->id]);
        $this->page($carGarage)->assertViewHas('blockedReason', 'Charging is only available in an electric car garage.');

        $plainCar = $this->rental(['vehicle_id' => Vehicle::factory()->car()->for($this->tenant)->create()->id]);
        $this->page($plainCar)->assertViewHas('blockedReason', 'Charging is only available for an electric car with a maximum capacity.');

        $noPrice = $this->rental(['garage_id' => Garage::factory()->electricCar()->create(['price_per_kwh' => null])->id]);
        $this->page($noPrice)->assertViewHas('blockedReason', 'The garage has no price per kWh.');
    }

    public function test_maximum_capacity_is_null_for_a_vehicle_without_one(): void
    {
        $rental = $this->rental(['vehicle_id' => Vehicle::factory()->car()->for($this->tenant)->create()->id]);

        $this->page($rental)->assertViewHas('maximumCapacity', null);
    }

    public function test_render_previews_the_amount_for_a_valid_battery_level(): void
    {
        $this->page($this->rental())->set('current_capacity_kw', '2')
            ->assertViewHas('preview', ['charged_kwh' => 8.0, 'price_per_kwh' => 2500.0, 'amount' => 20000.0])
            ->assertViewHas('previewError', null);
    }

    public function test_render_has_no_preview_for_empty_negative_text_or_too_high_values(): void
    {
        foreach (['', 'abc', '-1', '10.5'] as $value) {
            $this->page($this->rental())->set('current_capacity_kw', $value)->assertViewHas('preview', null);
        }
    }

    public function test_render_shows_a_preview_error_for_a_full_battery(): void
    {
        $this->page($this->rental())->set('current_capacity_kw', '10')
            ->assertViewHas('preview', null)
            ->assertViewHas('previewError', 'Battery is already full.');
    }

    public function test_no_preview_is_made_for_a_blocked_rental(): void
    {
        $rental = $this->rental(['garage_id' => Garage::factory()->car()->create()->id]);

        $this->page($rental)->set('current_capacity_kw', '2')->assertViewHas('preview', null)->assertViewHas('previewError', null);
    }

    public function test_rules_require_the_fields_and_check_the_range_and_the_proof(): void
    {
        $rental = $this->rental();
        $proof = UploadedFile::fake()->image('proof.jpg');

        $this->page($rental)->call('submitDefault')->assertHasErrors(['current_capacity_kw' => 'required', 'payment_proof' => 'required']);
        $this->page($rental)->set('current_capacity_kw', 'abc')->set('payment_proof', $proof)->call('submitDefault')->assertHasErrors(['current_capacity_kw' => 'numeric']);
        $this->page($rental)->set('current_capacity_kw', '-1')->set('payment_proof', $proof)->call('submitDefault')->assertHasErrors(['current_capacity_kw' => 'min']);
        $this->page($rental)->set('current_capacity_kw', '11')->set('payment_proof', $proof)->call('submitDefault')->assertHasErrors(['current_capacity_kw' => 'max']);
        $this->page($rental)->set('current_capacity_kw', '2')->set('payment_proof', UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'))->call('submitDefault')->assertHasErrors(['payment_proof' => 'image']);
        $this->assertDatabaseCount('charging_transactions', 0);
    }

    public function test_submit_saves_the_transaction_stores_the_proof_and_goes_to_the_rental(): void
    {
        $rental = $this->rental();

        $this->page($rental)
            ->set('current_capacity_kw', '2')
            ->set('payment_proof', UploadedFile::fake()->image('proof.jpg'))
            ->call('submitDefault')
            ->assertHasNoErrors()
            ->assertRedirect(route('rental.show', $rental->id));

        $transaction = ChargingTransaction::firstOrFail();
        $this->assertSame($rental->id, $transaction->rental_id);
        $this->assertSame('20000.00', $transaction->amount);
        $this->assertStringStartsWith('charging-proofs/', $transaction->payment_proof_path);
        Storage::disk('public')->assertExists($transaction->payment_proof_path);
    }

    public function test_submit_deletes_the_proof_when_the_service_refuses(): void
    {
        $rental = $this->rental();

        $component = $this->page($rental)
            ->set('current_capacity_kw', '2')
            ->set('payment_proof', UploadedFile::fake()->image('proof.jpg'));

        $rental->garage->update(['price_per_kwh' => null]);

        $component->call('submitDefault')->assertHasErrors('flash_danger');

        $this->assertDatabaseCount('charging_transactions', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
