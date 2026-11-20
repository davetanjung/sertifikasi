<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Route;

trait HasRoute
{
    public static function routePrefix()
    {
        return (new self)->getRoutePrefix();
    }

    public static function routeIndex()
    {
        return (new self)->getRouteIndex();
    }

    public static function routeCreate()
    {
        return (new self)->getRouteCreate();
    }

    public static function routeShow($obj)
    {
        return $obj->getRouteShow();
    }

    public static function routeEdit($obj)
    {
        return $obj->getRouteEdit();
    }

    public static function routeImport()
    {
        return (new self)->getRouteImport();
    }

    // ------------------------------------------------------------------------

    public static function permissionPrefix()
    {
        return (new self)->getPermissionPrefix();
    }

    public static function permissionIndex(): string
    {
        return (new self)->getPermissionIndex();
    }

    public static function permissionCreate(): string
    {
        return (new self)->getPermissionCreate();
    }

    public static function permissionShow(): string
    {
        return (new self)->getPermissionShow();
    }

    public static function permissionEdit(): string
    {
        return (new self)->getPermissionEdit();
    }

    public static function permissionDelete(): string
    {
        return (new self)->getPermissionDelete();
    }

    public static function permissionActivityLog(): string
    {
        return (new self)->getPermissionActivityLog();
    }

    // ------------------------------------------------------------------------

    public function getRoutePrefix()
    {
        $this->validatePrefix();

        return $this->route_prefix ?? null;
    }

    public function getRouteIndex(): string
    {
        if ($this->routeAllowed('index')) {
            if (Route::has($this->getRoutePrefix().'.index')) {
                return route($this->getRoutePrefix().'.index');
            }
        }

        return '#';
    }

    public function getRouteCreate(): string
    {
        if ($this->routeAllowed('create')) {
            if (Route::has($this->getRoutePrefix().'.create')) {
                return route($this->getRoutePrefix().'.create');
            }
        }

        return '#';
    }

    public function getRouteShow(): string
    {
        if ($this->routeAllowed('show')) {
            if (Route::has($this->getRoutePrefix().'.show')) {
                return route($this->getRoutePrefix().'.show', $this->id);
            }
        }

        return '#';
    }

    public function getRouteEdit(): string
    {
        if ($this->routeAllowed('edit')) {
            if (Route::has($this->getRoutePrefix().'.edit')) {
                return route($this->getRoutePrefix().'.edit', $this->id);
            }
        }

        return '#';
    }

    public function getRouteImport(): string
    {
        if ($this->routeAllowed('create')) {
            if (Route::has($this->getRoutePrefix().'.import')) {
                return route($this->getRoutePrefix().'.import');
            }
        }

        return '#';
    }

    // ------------------------------------------------------------------------

    public function getPermissionPrefix()
    {
        $this->validatePrefix();

        return $this->permission_prefix ?? null;
    }

    public function getPermissionIndex(): string
    {
        return $this->getPermissionPrefix().'.index';
    }

    public function getPermissionCreate(): string
    {
        return $this->getPermissionPrefix().'.create';
    }

    public function getPermissionShow(): string
    {
        return $this->getPermissionPrefix().'.show';
    }

    public function getPermissionEdit(): string
    {
        return $this->getPermissionPrefix().'.edit';
    }

    public function getPermissionDelete(): string
    {
        return $this->getPermissionPrefix().'.delete';
    }

    public function getPermissionActivityLog(): string
    {
        return $this->getPermissionPrefix().'.activity-log';
    }

    public function getFromCurrentRoute(): ?string
    {
        $route = Route::currentRouteName();
        if (empty($route)) {
            return null;
        }

        return mb_substr($route, 0, mb_strrpos($route, '.'));
    }

    // ------------------------------------------------------------------------

    /**
     * Links are only hidden by permission once permissions are switched on.
     */
    private function routeAllowed(string $action): bool
    {
        if (! _check_permissions()) {
            return true;
        }

        return (bool) optional(auth()->user())->can($this->getPermissionPrefix().'.'.$action);
    }

    private function validatePrefix(): void
    {
        // route_prefix and permission_prefix are optional until permissions are used.
    }
}
