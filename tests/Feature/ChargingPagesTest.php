<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\ChargingTransaction\Create;
use App\Livewire\ChargingTransaction\Index;
use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ChargingTransactionService;
use App\Services\RentalService;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ChargingPagesTest extends TestCase
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
     * Rent through the service (a daily rental of 3 days starting today).
     */
    private function rent(Garage $garage, Vehicle $vehicle): Rental
    {
        return RentalService::create([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'rental_type' => Const_Type::RENTAL_DAILY,
            'days' => 3,
            'payment_proof_path' => 'payment-proofs/new.jpg',
        ]);
    }

    /**
     * A running rental of an electric car (max 10 kW) in an electric garage with a price of 3000 per kWh.
     */
    private function electricRental(User $tenant): Rental
    {
        $garage = Garage::factory()->electricCar()->create(['price_per_kwh' => 3000]);
        $vehicle = Vehicle::factory()->electricCar()->for($tenant)->create(['maximum_capacity_kw' => 10]);

        return $this->rent($garage, $vehicle);
    }

    private function charge(Rental $rental, float $current = 2): ChargingTransaction
    {
        return ChargingTransactionService::create($rental->fresh(), $current, 'charging-proofs/x.jpg');
    }

    // ---------------------------------------------------------------- Index

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('charging-transaction.index'))->assertRedirect(route('login'));
        $this->get(route('charging-transaction.create'))->assertRedirect(route('login'));
    }

    public function test_a_tenant_sees_only_their_own_transactions(): void
    {
        $tenant = User::factory()->create();
        $mine = $this->charge($this->electricRental($tenant));
        $this->charge($this->electricRental(User::factory()->create()));

        Livewire::actingAs($tenant)
            ->test(Index::class)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$mine->id]);
    }

    public function test_an_admin_sees_all_transactions(): void
    {
        $this->charge($this->electricRental(User::factory()->create()));
        $this->charge($this->electricRental(User::factory()->create()));

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->assertViewHas('data', fn ($data) => $data->total() === 2);
    }

    public function test_keyword_searches_rental_code_and_garage_code(): void
    {
        $tenant = User::factory()->create();
        $first = $this->electricRental($tenant);
        $second = $this->electricRental($tenant);
        $firstTransaction = $this->charge($first);
        $this->charge($second);

        Livewire::actingAs($tenant)
            ->test(Index::class)
            ->set('keyword', $first->code)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$firstTransaction->id]);

        Livewire::actingAs($tenant)
            ->test(Index::class)
            ->set('keyword', $first->garage->code)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$firstTransaction->id]);
    }

    // --------------------------------------------------------------- Create

    public function test_an_admin_cannot_open_the_create_page(): void
    {
        $rental = $this->electricRental(User::factory()->create());

        Livewire::actingAs(User::factory()->admin()->create())
            ->withQueryParams(['rental' => $rental->id])
            ->test(Create::class)
            ->assertForbidden();
    }

    public function test_a_missing_rental_gives_404(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->withQueryParams(['rental' => '00000000-0000-0000-0000-000000000000'])
            ->test(Create::class)
            ->assertNotFound();
    }

    public function test_an_extension_row_gives_404(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->electricRental($tenant);
        RentalService::extend($rental, '2026-11-14', 'payment-proofs/ext.jpg');
        $extension = Rental::query()->where('status', Const_Status::EXTENSION)->firstOrFail();

        Livewire::actingAs($tenant)
            ->withQueryParams(['rental' => $extension->id])
            ->test(Create::class)
            ->assertNotFound();
    }

    public function test_another_tenants_rental_gives_403(): void
    {
        $rental = $this->electricRental(User::factory()->create());

        Livewire::actingAs(User::factory()->create())
            ->withQueryParams(['rental' => $rental->id])
            ->test(Create::class)
            ->assertForbidden();
    }

    private function createPage(User $tenant, Rental $rental)
    {
        return Livewire::actingAs($tenant)
            ->withQueryParams(['rental' => $rental->id])
            ->test(Create::class);
    }

    public function test_the_page_opens_with_the_rental_and_maximum_capacity(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->electricRental($tenant);

        $this->createPage($tenant, $rental)
            ->assertOk()
            ->assertViewHas('rental', fn ($viewRental) => $viewRental->id === $rental->id)
            ->assertViewHas('maximumCapacity', 10.0)
            ->assertViewHas('preview', null)
            ->assertViewHas('previewError', null)
            ->assertViewHas('blockedReason', null);
    }

    public function test_the_rules_reject_bad_input(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->electricRental($tenant);
        $proof = UploadedFile::fake()->image('proof.jpg');

        $this->createPage($tenant, $rental)
            ->call('submitDefault')
            ->assertHasErrors(['current_capacity_kw' => 'required', 'payment_proof' => 'required']);

        $this->createPage($tenant, $rental)
            ->set('current_capacity_kw', 'abc')
            ->set('payment_proof', $proof)
            ->call('submitDefault')
            ->assertHasErrors(['current_capacity_kw' => 'numeric']);

        $this->createPage($tenant, $rental)
            ->set('current_capacity_kw', -1)
            ->set('payment_proof', $proof)
            ->call('submitDefault')
            ->assertHasErrors(['current_capacity_kw' => 'min']);

        $this->createPage($tenant, $rental)
            ->set('current_capacity_kw', 11)
            ->set('payment_proof', $proof)
            ->call('submitDefault')
            ->assertHasErrors(['current_capacity_kw' => 'max']);

        $this->createPage($tenant, $rental)
            ->set('current_capacity_kw', 2)
            ->set('payment_proof', UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'))
            ->call('submitDefault')
            ->assertHasErrors(['payment_proof' => 'image']);

        $this->assertSame(0, ChargingTransaction::query()->count());
    }

    public function test_the_preview_shows_the_charged_kwh_and_amount(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->electricRental($tenant);

        $this->createPage($tenant, $rental)
            ->set('current_capacity_kw', 2)
            ->assertViewHas('preview', fn ($preview) => $preview['charged_kwh'] === 8.0
                && $preview['price_per_kwh'] === 3000.0
                && $preview['amount'] === 24000.0)
            ->assertViewHas('previewError', null);
    }

    public function test_empty_input_gives_no_preview_and_no_error(): void
    {
        $tenant = User::factory()->create();

        $this->createPage($tenant, $this->electricRental($tenant))
            ->set('current_capacity_kw', '')
            ->assertViewHas('preview', null)
            ->assertViewHas('previewError', null);
    }

    public function test_a_full_battery_gives_a_preview_error(): void
    {
        $tenant = User::factory()->create();

        $this->createPage($tenant, $this->electricRental($tenant))
            ->set('current_capacity_kw', 10)
            ->assertViewHas('preview', null)
            ->assertViewHas('previewError', 'Battery is already full.');
    }

    public function test_an_ended_rental_is_blocked(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->electricRental($tenant);
        Carbon::setTestNow('2026-11-20 09:00:00');

        $this->createPage($tenant, $rental)
            ->set('current_capacity_kw', 2)
            ->assertViewHas('blockedReason', 'The rental is not running today.')
            ->assertViewHas('preview', null);
    }

    public function test_a_rental_in_a_car_garage_is_blocked(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->rent(Garage::factory()->car()->create(), Vehicle::factory()->car()->for($tenant)->create());

        $this->createPage($tenant, $rental)
            ->assertOk()
            ->assertViewHas('maximumCapacity', null)
            ->assertViewHas('blockedReason', 'Charging is only available in an electric car garage.');
    }

    public function test_a_valid_submit_saves_the_transaction_and_redirects_to_the_rental(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->electricRental($tenant);

        $this->createPage($tenant, $rental)
            ->set('current_capacity_kw', 2)
            ->set('payment_proof', UploadedFile::fake()->image('proof.jpg'))
            ->call('submitDefault')
            ->assertHasNoErrors()
            ->assertRedirect(route('rental.show', $rental->id));

        $transaction = ChargingTransaction::query()->firstOrFail();
        $this->assertSame($rental->id, $transaction->rental_id);
        $this->assertEquals(2, $transaction->current_capacity_kw);
        $this->assertEquals(8, $transaction->charged_kwh);
        $this->assertEquals(3000, $transaction->price_per_kwh);
        $this->assertEquals(24000, $transaction->amount);
        Storage::disk('public')->assertExists($transaction->payment_proof_path);
        $this->assertStringStartsWith('charging-proofs/', $transaction->payment_proof_path);
    }

    public function test_a_service_failure_shows_an_error_and_leaves_no_file(): void
    {
        $tenant = User::factory()->create();
        $rental = $this->electricRental($tenant);

        $component = $this->createPage($tenant, $rental)
            ->set('current_capacity_kw', 2)
            ->set('payment_proof', UploadedFile::fake()->image('proof.jpg'));

        Carbon::setTestNow('2026-11-20 09:00:00'); // the rental ended meanwhile

        $component->call('submitDefault')->assertHasErrors('flash_danger');

        $this->assertSame(0, ChargingTransaction::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
