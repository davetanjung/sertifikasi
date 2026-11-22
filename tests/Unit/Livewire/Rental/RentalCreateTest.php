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

    public function test_render_prices_a_monthly_rental_at_25_times_the_daily_price(): void
    {
        $garage = $this->carGarage('C001', 100000);

        $this->page()->set('rental_type', Const_Type::RENTAL_MONTHLY)->set('garage_id', $garage->id)
            ->assertViewHas('preview', fn ($preview) => $preview['days'] === 30 && $preview['rent_amount'] === 2500000.0);
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
}
