<?php

declare(strict_types=1);

namespace App\Livewire\Vehicle;

use App\Models\Vehicle;
use App\Traits\Livewire\WithShowForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Show extends Component
{
    use WithShowForm;

    public $model = Vehicle::class;

    public $menuTitle = 'Vehicle';

    public Vehicle $obj;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin() || $this->obj->user_id === auth()->id(), 403);
    }

    public function render(): View
    {
        return view('vehicle.show', [
            'rentals' => $this->obj->rentals()->with('garage')->latest()->limit(10)->get(),
        ])->layout($this->layout);
    }
}
