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
}
