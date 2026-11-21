<?php

declare(strict_types=1);

namespace App\Livewire\Vehicle;

use App\Models\Vehicle;
use App\Traits\Livewire\WithIndexForm;
use App\Utilities\Constants\Const_Type;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Index extends Component
{
    use WithIndexForm;

    public $model = Vehicle::class;

    public $menuTitle = 'Vehicle';

    public $keyword;

    public $type;

    public function mount(): void
    {
        $this->sortField = 'created_at';
    }

    public function render(): View
    {
        $this->processFilter();

        return view('vehicle.index', [
            'data' => $this->data,
            'typeOptions' => [
                Const_Type::VEHICLE_BICYCLE => 'Bicycle',
                Const_Type::VEHICLE_MOTORCYCLE => 'Motorcycle',
                Const_Type::VEHICLE_CAR => 'Car',
                Const_Type::VEHICLE_ELECTRIC_CAR => 'Electric car',
            ],
        ])->layout($this->layout);
    }

    private function getQuery()
    {
        return Vehicle::query()
            ->with('user')
            ->when(! auth()->user()->isAdmin(), function ($query) {
                return $query->where('user_id', auth()->id());
            })
            ->keywordSearch($this->keyword, ['plate_number', 'brand', 'model'])
            ->when($this->type, function ($query) {
                return $query->where('type', $this->type);
            });
    }
}
