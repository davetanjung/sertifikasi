<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

trait WithEditForm
{
    use HasCheckPermissionGate;
    use WithFormSubmit;

    protected $layout = 'components.layouts.app';

    protected string $submitMessage = 'has been updated';
}
