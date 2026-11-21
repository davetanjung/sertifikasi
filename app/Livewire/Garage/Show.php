<?php

declare(strict_types=1);

namespace App\Livewire\Garage;

use App\Models\Garage;
use App\Traits\Livewire\WithShowForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Show extends Component
{
    use WithShowForm;

    public $model = Garage::class;

    public $menuTitle = 'Garage';

    public Garage $obj;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function render(): View
    {
        return view('garage.show', [
            'status' => $this->obj->status,
            'usedToday' => $this->obj->rentals()
                ->whereDate('start_date', '<=', today())
                ->whereDate('end_date', '>=', today())
                ->exists(),
            'rentals' => $this->obj->rentals()
                ->with(['user', 'vehicle'])
                ->latest()
                ->limit(10)
                ->get(),
        ])->layout($this->layout);
    }
}
