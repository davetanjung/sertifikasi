<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\Garage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasKeywordSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_keyword_or_no_columns_leaves_the_query_alone(): void
    {
        Garage::factory()->car()->count(2)->create();

        $this->assertSame(2, Garage::query()->keywordSearch('', ['code'])->count());
        $this->assertSame(2, Garage::query()->keywordSearch(null, ['code'])->count());
        $this->assertSame(2, Garage::query()->keywordSearch('C001', [])->count());
    }

    public function test_it_matches_part_of_a_value_without_regard_to_case(): void
    {
        Garage::factory()->car()->create(['code' => 'C001']);
        Garage::factory()->bicycle()->create(['code' => 'S001']);

        $this->assertSame(['C001'], Garage::query()->keywordSearch('c00', ['code'])->pluck('code')->all());
    }

    public function test_it_searches_every_given_column(): void
    {
        $byName = User::factory()->create(['name' => 'Dewi Lestari', 'email' => 'a@example.com']);
        $byEmail = User::factory()->create(['name' => 'Other', 'email' => 'dewi@example.com']);
        User::factory()->create(['name' => 'Budi', 'email' => 'b@example.com']);

        $ids = User::query()->keywordSearch('dewi', ['name', 'email'])->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$byName->id, $byEmail->id], $ids);
    }

    public function test_every_word_must_match_in_some_column(): void
    {
        $both = User::factory()->create(['name' => 'Dewi Lestari', 'email' => 'x@example.com']);
        User::factory()->create(['name' => 'Dewi Putri', 'email' => 'y@example.com']);

        $this->assertSame([$both->id], User::query()->keywordSearch('dewi lestari', ['name'])->pluck('id')->all());
    }

    public function test_reserved_symbols_become_spaces_and_extra_spaces_are_ignored(): void
    {
        $user = User::factory()->create(['name' => 'Dewi Lestari']);

        $this->assertSame([$user->id], User::query()->keywordSearch('  +dewi   -lestari~ ', ['name'])->pluck('id')->all());
        $this->assertSame([$user->id], User::query()->keywordSearch('(dewi)@<lestari>', ['name'])->pluck('id')->all());
    }

    public function test_a_keyword_made_only_of_symbols_adds_no_condition(): void
    {
        User::factory()->count(2)->create();

        $this->assertSame(2, User::query()->keywordSearch('+ - ~', ['name'])->count());
    }

    public function test_it_returns_the_query_for_chaining(): void
    {
        $query = User::query();

        $this->assertSame($query, $query->keywordSearch('x', ['name']));
    }
}
