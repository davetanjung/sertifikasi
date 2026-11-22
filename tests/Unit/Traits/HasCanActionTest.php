<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\Garage;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HasCanActionTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function actions(): array
    {
        return ['show', 'create', 'edit', 'delete'];
    }

    private function can(Garage $garage, string $action): bool
    {
        return match ($action) {
            'show' => $garage->canShow(),
            'create' => $garage->canCreate(),
            'edit' => $garage->canEdit(),
            'delete' => $garage->canDelete(),
        };
    }

    public function test_every_action_is_allowed_while_permissions_are_off(): void
    {
        $this->actingAs(User::factory()->make());

        foreach ($this->actions() as $action) {
            $this->assertTrue($this->can(new Garage, $action), $action);
        }
    }

    public function test_every_action_is_allowed_without_a_user_even_with_permissions_on(): void
    {
        config(['app.check_permissions' => true]);

        foreach ($this->actions() as $action) {
            $this->assertTrue($this->can(new Garage, $action), $action);
        }
    }

    public function test_with_permissions_on_the_user_needs_the_matching_gate(): void
    {
        config(['app.check_permissions' => true]);
        $this->actingAs(User::factory()->make());

        foreach ($this->actions() as $action) {
            $this->assertFalse($this->can(new Garage, $action), $action.' before granting');
        }

        Gate::define('garage.show', fn () => true);
        Gate::define('garage.edit', fn () => true);

        $this->assertTrue((new Garage)->canShow());
        $this->assertTrue((new Garage)->canEdit());
        $this->assertFalse((new Garage)->canCreate());
        $this->assertFalse((new Garage)->canDelete());
    }

    public function test_the_static_helpers_follow_the_same_rule(): void
    {
        $this->assertTrue(Garage::canPermissionCreate());
        $this->assertTrue(Garage::canPermissionEdit());
        $this->assertTrue(Garage::canPermissionDelete());

        config(['app.check_permissions' => true]);
        $this->actingAs(User::factory()->make());
        Gate::define('garage.create', fn () => true);

        $this->assertTrue(Garage::canPermissionCreate());
        $this->assertFalse(Garage::canPermissionEdit());
        $this->assertFalse(Garage::canPermissionDelete());
    }
}
