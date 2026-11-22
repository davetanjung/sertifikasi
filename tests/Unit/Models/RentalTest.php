<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Rental;
use App\Utilities\Constants\Const_Status;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_code_is_generated_on_create_with_a_four_digit_number_per_day(): void
    {
        $first = Rental::factory()->create(['code' => null]);
        $second = Rental::factory()->create(['code' => null]);

        $this->assertSame('RNT-20261110-0001', $first->code);
        $this->assertSame('RNT-20261110-0002', $second->code);

        Carbon::setTestNow('2026-11-11 09:00:00');

        $this->assertSame('RNT-20261111-0001', Rental::factory()->create(['code' => null])->code);
    }

    public function test_an_extension_row_keeps_the_code_it_was_given(): void
    {
        $original = Rental::factory()->create();

        $extension = Rental::factory()->extension()->create(['code' => $original->code]);

        $this->assertSame($original->code, $extension->code);
        $this->assertSame(Const_Status::EXTENSION, $extension->status);
    }
}
