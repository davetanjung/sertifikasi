<?php

declare(strict_types=1);

namespace App\Livewire\Garage;

use App\Models\Garage;
use App\Traits\Livewire\WithIndexForm;
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
     * '' (all), 'free' or 'used' (used today or not).
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
                'free' => 'Free today',
                'used' => 'Used today',
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
            ->when($this->availability === 'used', function ($query) use ($coversToday) {
                return $query->whereHas('rentals', $coversToday);
            })
            ->when($this->availability === 'free', function ($query) use ($coversToday) {
                return $query->whereDoesntHave('rentals', $coversToday);
            });
    }
}
