<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\Garage;
use App\Models\Rental;
use App\Traits\HasAutoNumber;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasAutoNumberTest extends TestCase
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

    /**
     * A model that only uses the trait defaults, stored in the garages table.
     */
    private function plainModel(): Model
    {
        return new class extends Model
        {
            use HasAutoNumber;

            protected $table = 'garages';
        };
    }

    public function test_boot_fills_an_empty_code_on_creating(): void
    {
        $garage = Garage::factory()->car()->create(['code' => null]);

        $this->assertSame('C001', $garage->code);
    }

    public function test_boot_keeps_a_code_that_was_given(): void
    {
        $this->assertSame('C500', Garage::factory()->car()->create(['code' => 'C500'])->code);
    }

    public function test_get_auto_number_column_is_code(): void
    {
        $this->assertSame('code', $this->plainModel()->getAutoNumberColumn());
    }

    public function test_auto_number_length_defaults_to_three_and_reads_the_model_property(): void
    {
        $this->assertSame(3, $this->plainModel()->autoNumberLength());
        $this->assertSame(3, (new Garage)->autoNumberLength());
        $this->assertSame(4, (new Rental)->autoNumberLength());
    }

    public function test_auto_number_prefix_defaults_to_empty_and_reads_the_model_property(): void
    {
        $withPrefix = new class extends Model
        {
            use HasAutoNumber;

            protected string $auto_number_prefix = 'X-';
        };

        $this->assertSame('', $this->plainModel()->autoNumberPrefix());
        $this->assertSame('X-', $withPrefix->autoNumberPrefix());
    }

    public function test_get_auto_number_starts_at_one_and_pads_with_zeros(): void
    {
        $this->assertSame('C001', (new Garage(['type' => 'car']))->getAutoNumber());
        $this->assertSame('RNT-20261110-0001', (new Rental)->getAutoNumber());
    }

    public function test_get_auto_number_adds_one_to_the_highest_existing_number(): void
    {
        Garage::factory()->car()->create(['code' => 'C007']);
        Garage::factory()->car()->create(['code' => 'C003']);

        $this->assertSame('C008', (new Garage(['type' => 'car']))->getAutoNumber());
    }

    public function test_get_auto_number_counts_each_prefix_on_its_own(): void
    {
        Garage::factory()->car()->create(['code' => 'C009']);

        $this->assertSame('S001', (new Garage(['type' => 'bicycle']))->getAutoNumber());
    }

    public function test_get_auto_number_ignores_codes_of_a_different_length(): void
    {
        Garage::factory()->car()->create(['code' => 'C0099']);

        $this->assertSame('C001', (new Garage(['type' => 'car']))->getAutoNumber());
    }

    public function test_get_auto_number_does_not_go_past_its_digits_but_grows_longer(): void
    {
        Garage::factory()->car()->create(['code' => 'C999']);

        $this->assertSame('C1000', (new Garage(['type' => 'car']))->getAutoNumber());
    }

    public function test_get_auto_number_restarts_each_day_for_rentals(): void
    {
        Rental::factory()->create(['code' => 'RNT-20261109-0042']);

        $this->assertSame('RNT-20261110-0001', (new Rental)->getAutoNumber());
    }
}
