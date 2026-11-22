<?php

declare(strict_types=1);

namespace Tests\Unit\Traits\Livewire;

use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Models\User;
use App\Traits\Livewire\HasCheckPermissionGate;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HasCheckPermissionGateTest extends TestCase
{
    /**
     * A bare object that uses the trait, like a Livewire page would.
     */
    private function page(?string $model = Garage::class, ?Garage $obj = null, ?array $pagePermissions = null): object
    {
        return new class($model, $obj, $pagePermissions)
        {
            use HasCheckPermissionGate;

            public function __construct(public ?string $model, public ?Garage $obj, public ?array $page_permissions) {}
        };
    }

    private function assertForbidden(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected a 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_every_gate_does_nothing_while_permissions_are_off(): void
    {
        $page = $this->page(Garage::class, new Garage);

        $page->checkPermissionIndexGate();
        $page->checkPermissionShowGate();
        $page->checkPermissionCreateGate();
        $page->checkPermissionEditGate();
        $page->checkPermissionDeleteGate();

        $this->assertTrue(true);
    }

    public function test_the_index_gate_needs_the_index_permission(): void
    {
        config(['app.check_permissions' => true]);
        $this->actingAs(User::factory()->make());
        $page = $this->page();

        $this->assertForbidden(fn () => $page->checkPermissionIndexGate());

        Gate::define('garage.index', fn () => true);
        $page->checkPermissionIndexGate();
    }

    public function test_the_create_gate_needs_the_create_permission(): void
    {
        config(['app.check_permissions' => true]);
        $this->actingAs(User::factory()->make());
        $page = $this->page();

        $this->assertForbidden(fn () => $page->checkPermissionCreateGate());

        Gate::define('garage.create', fn () => true);
        $page->checkPermissionCreateGate();
    }

    public function test_the_show_edit_and_delete_gates_need_their_permission_and_the_record_to_allow_it(): void
    {
        config(['app.check_permissions' => true]);
        $this->actingAs(User::factory()->make());
        $page = $this->page(Garage::class, new Garage);

        $this->assertForbidden(fn () => $page->checkPermissionShowGate());
        $this->assertForbidden(fn () => $page->checkPermissionEditGate());
        $this->assertForbidden(fn () => $page->checkPermissionDeleteGate());

        Gate::define('garage.show', fn () => true);
        Gate::define('garage.edit', fn () => true);
        Gate::define('garage.delete', fn () => true);

        $page->checkPermissionShowGate();
        $page->checkPermissionEditGate();
        $page->checkPermissionDeleteGate();
        $this->assertTrue(true);
    }

    public function test_page_permissions_replace_the_default_permission(): void
    {
        config(['app.check_permissions' => true]);
        $this->actingAs(User::factory()->make());
        $page = $this->page(Garage::class, null, ['garage.special']);

        $this->assertForbidden(fn () => $page->checkPermissionIndexGate());

        Gate::define('garage.special', fn () => true);
        $page->checkPermissionIndexGate();

        $this->assertTrue(true);
    }

    public function test_the_index_gate_needs_a_model_when_permissions_are_on(): void
    {
        config(['app.check_permissions' => true]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('$model must be defined');

        $this->page(null)->checkPermissionIndexGate();
    }
}
