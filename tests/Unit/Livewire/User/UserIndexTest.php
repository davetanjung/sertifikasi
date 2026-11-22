<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\User;

use App\Livewire\User\Index;
use App\Models\User;
use App\Utilities\Constants\Const_Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function page()
    {
        return Livewire::actingAs(User::factory()->admin()->create())->test(Index::class);
    }

    public function test_mount_is_for_admins_only(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Index::class)->assertForbidden();
        $this->page()->assertOk()->assertSet('sortField', 'created_at');
    }

    public function test_render_lists_tenants_but_never_admins(): void
    {
        $tenant = User::factory()->create(['name' => 'Tenant']);

        $this->page()->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$tenant->id]);
    }

    public function test_render_gives_the_status_options(): void
    {
        $this->page()->assertViewHas('statusOptions', [
            Const_Status::PENDING => 'Pending',
            Const_Status::APPROVED => 'Approved',
            Const_Status::REJECTED => 'Rejected',
        ]);
    }

    public function test_get_query_filters_by_keyword_in_name_email_nik_and_phone(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Wonder', 'email' => 'a@example.com', 'nik' => '1111111111111111', 'phone' => '0811']);
        $bob = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'nik' => '2222222222222222', 'phone' => '0822']);

        foreach (['alice' => $alice, 'bob@example' => $bob, '2222222' => $bob, '0811' => $alice] as $keyword => $expected) {
            $this->page()->set('keyword', (string) $keyword)
                ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$expected->id]);
        }
    }

    public function test_get_query_filters_by_registration_status(): void
    {
        $pending = User::factory()->pending()->create();
        User::factory()->create();
        User::factory()->rejected()->create();

        $this->page()->set('status', Const_Status::PENDING)
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$pending->id]);
    }

    public function test_sort_changes_the_order(): void
    {
        $b = User::factory()->create(['name' => 'Bob']);
        $a = User::factory()->create(['name' => 'Alice']);

        $this->page()->call('sort', 'name')
            ->assertViewHas('data', fn ($data) => $data->pluck('id')->all() === [$a->id, $b->id]);
    }

    public function test_the_list_is_paginated(): void
    {
        User::factory()->count(12)->create();

        $this->page()->assertViewHas('data', fn ($data) => $data->count() === 10 && $data->total() === 12);
    }
}
