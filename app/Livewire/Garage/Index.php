<?php

declare(strict_types=1);

namespace App\Livewire\Garage;

use App\Models\Garage;
use App\Traits\Livewire\WithIndexForm;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Index extends Component
{
    use WithIndexForm;

    public $model = Garage::class;

    public $menuTitle = 'Garage';

    public $keyword;

    public $type;

    /**
     * '' (all), Const_Status::AVAILABLE or Const_Status::USED (computed from today's rentals).
     */
    public $availability = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->sortField = 'code';
        $this->sortAsc = true;
    }

    public function render(): View
    {
        $this->processFilter();

        return view('garage.index', [
            'data' => $this->data,
            'typeOptions' => [
                Const_Type::GARAGE_BICYCLE => 'Bicycle',
                Const_Type::GARAGE_CAR => 'Car',
                Const_Type::GARAGE_ELECTRIC_CAR => 'Electric car',
            ],
            'availabilityOptions' => [
                Const_Status::AVAILABLE => 'Available',
                Const_Status::USED => 'Used',
            ],
        ])->layout($this->layout);
    }

    private function getQuery()
    {
        $coversToday = function ($query): void {
            $query->whereDate('start_date', '<=', today())
                ->whereDate('end_date', '>=', today());
        };

        return Garage::query()
            ->withExists(['rentals as used_today' => $coversToday])
            ->keywordSearch($this->keyword, ['code'])
            ->when($this->type, function ($query) {
                return $query->where('type', $this->type);
            })
            ->when($this->availability === Const_Status::USED, function ($query) use ($coversToday) {
                return $query->whereHas('rentals', $coversToday);
            })
            ->when($this->availability === Const_Status::AVAILABLE, function ($query) use ($coversToday) {
                return $query->whereDoesntHave('rentals', $coversToday);
            });
    }
}
