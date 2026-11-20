<?php

declare(strict_types=1);

namespace App\Traits;

trait HasCanAction
{
    public static function canPermissionCreate(): bool
    {
        return (new self)->canCreate();
    }

    public static function canPermissionEdit(): bool
    {
        return (new self)->canEdit();
    }

    public static function canPermissionDelete(): bool
    {
        return (new self)->canDelete();
    }

    public function canShow(): bool
    {
        if (! _check_permissions() || ! auth()->user()) {
            return true;
        }

        return auth()->user()->can($this->getPermissionShow());
    }

    public function canCreate(): bool
    {
        if (! _check_permissions() || ! auth()->user()) {
            return true;
        }

        return auth()->user()->can($this->getPermissionCreate());
    }

    public function canEdit(): bool
    {
        if (! _check_permissions() || ! auth()->user()) {
            return true;
        }

        return auth()->user()->can($this->getPermissionEdit());
    }

    public function canDelete(): bool
    {
        if (! _check_permissions() || ! auth()->user()) {
            return true;
        }

        return auth()->user()->can($this->getPermissionDelete());
    }
}
