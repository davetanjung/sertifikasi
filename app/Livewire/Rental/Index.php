<?php

declare(strict_types=1);

namespace App\Livewire\Rental;

use App\Models\Rental;
use App\Traits\Livewire\WithIndexForm;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

final class Index extends Component
{
    use WithIndexForm;

    public $model = Rental::class;

    public $menuTitle = 'Rental';

    public $keyword;

    public $period;

    public $rental_type;

    public function mount(): void
    {
        $this->sortField = 'created_at';
    }

    public function render(): View
    {
        $this->processFilter();

        $today = today()->toDateString();

        $this->data->getCollection()->each(function (Rental $rental) use ($today): void {
            $latestEndDate = Carbon::parse($rental->latest_end_date)->toDateString();
            $rental->setAttribute('latest_end_date', $latestEndDate);
            $rental->setAttribute('period_status', match (true) {
                $rental->start_date->toDateString() > $today => 'upcoming',
                $latestEndDate < $today => 'ended',
                default => 'running',
            });
        });

        return view('rental.index', [
            'data' => $this->data,
            'periodOptions' => [
                'upcoming' => 'Upcoming',
                'running' => 'Running',
                'ended' => 'Ended',
            ],
            'rentalTypeOptions' => [
                Const_Type::RENTAL_DAILY => 'Daily',
                Const_Type::RENTAL_MONTHLY => 'Monthly',
            ],
        ])->layout($this->layout);
    }

    private function getQuery()
    {
        $today = today()->toDateString();

        return Rental::query()
            ->select('rentals.*')
            ->addSelect(['latest_end_date' => $this->latestEndDateQuery()])
            ->with(['garage', 'vehicle', 'user'])
            ->where('rentals.status', Const_Status::NEW)
            ->when(! auth()->user()->isAdmin(), function ($query) {
                return $query->where('rentals.user_id', auth()->id());
            })
            ->when($this->keyword, function ($query) {
                return $query->where(function ($query): void {
                    $query->keywordSearch($this->keyword, ['code']);

                    $keyword = '%'.mb_trim((string) $this->keyword).'%';

                    $query->orWhereHas('garage', fn ($garage) => $garage->whereLike('code', $keyword))
                        ->orWhereHas('vehicle', fn ($vehicle) => $vehicle->whereLike('plate_number', $keyword));
                });
            })
            ->when($this->rental_type, function ($query) {
                return $query->where('rentals.rental_type', $this->rental_type);
            })
            ->when($this->period === 'upcoming', function ($query) use ($today) {
                return $query->whereDate('rentals.start_date', '>', $today);
            })
            ->when($this->period === 'running', function ($query) use ($today) {
                return $query->whereDate('rentals.start_date', '<=', $today)
                    ->where($this->latestEndDateQuery(), '>=', $today);
            })
            ->when($this->period === 'ended', function ($query) use ($today) {
                return $query->where($this->latestEndDateQuery(), '<', $today);
            });
    }

    /**
     * The latest end date among the new row and its extensions (same code, tenant and garage).
     */
    private function latestEndDateQuery(): Builder
    {
        return Rental::query()
            ->from('rentals as same_rental')
            ->selectRaw('max(same_rental.end_date)')
            ->whereColumn('same_rental.code', 'rentals.code')
            ->whereColumn('same_rental.user_id', 'rentals.user_id')
            ->whereColumn('same_rental.garage_id', 'rentals.garage_id');
    }
}
