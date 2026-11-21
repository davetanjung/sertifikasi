<?php

declare(strict_types=1);

namespace App\Livewire\User;

use App\Models\User;
use App\Traits\Livewire\WithIndexForm;
use App\Utilities\Constants\Const_Role;
use App\Utilities\Constants\Const_Status;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Index extends Component
{
    use WithIndexForm;

    public $model = User::class;

    public $menuTitle = 'Tenant';

    public $keyword;

    public $status;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->sortField = 'created_at';
    }

    public function render(): View
    {
        $this->processFilter();

        return view('user.index', [
            'data' => $this->data,
            'statusOptions' => [
                Const_Status::PENDING => 'Pending',
                Const_Status::APPROVED => 'Approved',
                Const_Status::REJECTED => 'Rejected',
            ],
        ])->layout($this->layout);
    }

    private function getQuery()
    {
        return User::query()
            ->where('role', Const_Role::TENANT)
            ->keywordSearch($this->keyword, ['name', 'email', 'nik', 'phone'])
            ->when($this->status, function ($query) {
                return $query->where('registration_status', $this->status);
            });
    }
}
