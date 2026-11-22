<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ChargingTransaction;
use App\Models\Rental;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargingTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rental_returns_the_rental_it_belongs_to(): void
    {
        $rental = Rental::factory()->create();

        $this->assertTrue(ChargingTransaction::factory()->create(['rental_id' => $rental->id])->rental->is($rental));
    }

    public function test_the_numbers_are_cast_to_two_decimals(): void
    {
        $transaction = ChargingTransaction::factory()->create([
            'current_capacity_kw' => 2,
            'charged_kwh' => 8,
            'price_per_kwh' => 2500,
            'amount' => 20000,
        ])->fresh();

        $this->assertSame('2.00', $transaction->current_capacity_kw);
        $this->assertSame('8.00', $transaction->charged_kwh);
        $this->assertSame('2500.00', $transaction->price_per_kwh);
        $this->assertSame('20000.00', $transaction->amount);
    }

    public function test_the_proof_path_can_be_mass_assigned(): void
    {
        $transaction = new ChargingTransaction(['payment_proof_path' => 'charging-proofs/a.jpg']);

        $this->assertSame('charging-proofs/a.jpg', $transaction->payment_proof_path);
    }
}
