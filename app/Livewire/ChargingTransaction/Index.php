<?php

declare(strict_types=1);

namespace App\Livewire\ChargingTransaction;

use App\Models\ChargingTransaction;
use App\Traits\Livewire\WithIndexForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Index extends Component
{
    use WithIndexForm;

    public $model = ChargingTransaction::class;

    public $menuTitle = 'Charging';

    public $keyword;

    public function mount(): void
    {
        $this->sortField = 'created_at';
    }

    public function render(): View
    {
        $this->processFilter();

        return view('charging-transaction.index', [
            'data' => $this->data,
        ])->layout($this->layout);
    }

    private function getQuery()
    {
        return ChargingTransaction::query()
            ->with(['rental.garage', 'rental.vehicle', 'rental.user'])
            ->when(! auth()->user()->isAdmin(), function ($query) {
                return $query->whereHas('rental', fn ($rental) => $rental->where('user_id', auth()->id()));
            })
            ->when($this->keyword, function ($query) {
                $keyword = '%'.mb_trim((string) $this->keyword).'%';

                return $query->whereHas('rental', function ($rental) use ($keyword): void {
                    $rental->where(function ($rental) use ($keyword): void {
                        $rental->whereLike('code', $keyword)
                            ->orWhereHas('garage', fn ($garage) => $garage->whereLike('code', $keyword));
                    });
                });
            });
    }
}
