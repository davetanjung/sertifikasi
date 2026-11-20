<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

use App\Exceptions\GeneralException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Permission gates for Livewire pages. Opt-in per component and a no-op until
 * permissions are switched on (see _check_permissions() in helpers.php).
 *
 * @property mixed $obj
 * @property mixed $model
 * @property mixed $page_permissions
 */
trait HasCheckPermissionGate
{
    public function checkPermissionIndexGate(): void
    {
        $this->authorizeAny(fn (): array => [$this->getModel()::permissionIndex()]);
    }

    public function checkPermissionShowGate(): void
    {
        $this->authorizeAny(fn (): array => [$this->obj->getPermissionShow()]);

        if (_check_permissions() && isset($this->obj)) {
            abort_if(! $this->obj->canShow(), Response::HTTP_FORBIDDEN, 'The requested object is not available [Cannot Show].');
        }
    }

    public function checkPermissionCreateGate(): void
    {
        $this->authorizeAny(fn (): array => [$this->getModel()::permissionCreate()]);
    }

    public function checkPermissionEditGate(): void
    {
        $this->authorizeAny(fn (): array => [$this->obj->getPermissionEdit()]);

        if (_check_permissions() && isset($this->obj)) {
            abort_if(! $this->obj->canEdit(), Response::HTTP_FORBIDDEN, 'The requested object is not available [Cannot Edit].');
        }
    }

    public function checkPermissionDeleteGate(): void
    {
        $this->authorizeAny(fn (): array => [$this->obj->getPermissionDelete()]);

        if (_check_permissions() && isset($this->obj)) {
            abort_if(! $this->obj->canDelete(), Response::HTTP_FORBIDDEN, 'The requested object is not available [Cannot Delete].');
        }
    }

    /**
     * @param  callable(): array<int, string>  $defaultPermissions
     */
    private function authorizeAny(callable $defaultPermissions): void
    {
        if (! _check_permissions()) {
            return;
        }

        $permissions = $this->page_permissions ?? $defaultPermissions();
        abort_if(Gate::none($permissions), Response::HTTP_FORBIDDEN);
    }

    private function getModel()
    {
        if (empty($this->model)) {
            throw new GeneralException('$model must be defined in '.__CLASS__);
        }

        return $this->model;
    }
}
