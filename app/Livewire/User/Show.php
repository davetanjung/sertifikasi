<?php

declare(strict_types=1);

namespace App\Livewire\User;

use App\Models\User;
use App\Services\UserService;
use App\Traits\Livewire\WithShowForm;
use App\Utilities\Constants\Const_Role;
use App\Utilities\Constants\Const_Status;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

final class Show extends Component
{
    use WithShowForm;

    public $model = User::class;

    public $menuTitle = 'Tenant';

    public User $obj;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless($this->obj->role === Const_Role::TENANT, 404);
    }

    public function approve(): void
    {
        $this->changeRegistration(
            fn (): User => UserService::approve($this->obj),
            'Registration approved.',
        );
    }

    public function reject(): void
    {
        $this->changeRegistration(
            fn (): User => UserService::reject($this->obj),
            'Registration rejected.',
        );
    }

    public function render(): View
    {
        return view('user.show', [
            'vehicles' => $this->obj->vehicles()->latest()->get(),
            'rentals' => $this->obj->rentals()
                ->with('garage')
                ->where('status', Const_Status::NEW)
                ->latest()
                ->limit(10)
                ->get(),
            'canApprove' => $this->obj->registration_status !== Const_Status::APPROVED,
            'canReject' => $this->obj->registration_status !== Const_Status::REJECTED,
        ])->layout($this->layout);
    }

    /**
     * Run a status change through the service, then refresh the tenant and flash the result.
     *
     * @param  callable(): User  $change
     */
    private function changeRegistration(callable $change, string $successMessage): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        try {
            $change();
            $this->obj = $this->obj->fresh();
            session()->flash('flash_success', $successMessage);
        } catch (Throwable $e) {
            $this->addError('flash_danger', _get_exception_message($e, false));
        }
    }
}
