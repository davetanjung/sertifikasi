<?php

declare(strict_types=1);

namespace App\Livewire\Extension;

use App\Models\Rental;
use App\Services\RentalService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

final class Index extends Component
{
    protected $layout = 'components.layouts.app';

    public $menuTitle = 'Extension';

    public $keyword;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function render(): View
    {
        return view('extension.index', [
            'rentals' => $this->rentals(),
        ])->layout($this->layout);
    }

    /**
     * The rentals running today that match the keyword, each with its current end date.
     *
     * @return Collection<int, Rental>
     */
    private function rentals(): Collection
    {
        $keyword = trim((string) $this->keyword);

        $rentals = RentalService::activeRentals()
            ->filter(fn (Rental $rental): bool => $keyword === '' || $this->matches($rental, $keyword))
            ->values();

        if ($rentals->isEmpty()) {
            return $rentals;
        }

        // One query for all rentals: the latest end date of every code (extensions included).
        $latestEndDates = Rental::query()
            ->whereIn('code', $rentals->pluck('code')->all())
            ->groupBy('code', 'user_id', 'garage_id')
            ->selectRaw('code, user_id, garage_id, MAX(end_date) as latest_end_date')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $row->code.'|'.$row->user_id.'|'.$row->garage_id => Carbon::parse($row->latest_end_date)->toDateString(),
            ]);

        return $rentals->each(function (Rental $rental) use ($latestEndDates): void {
            $rental->setAttribute(
                'latest_end_date',
                $latestEndDates->get($rental->code.'|'.$rental->user_id.'|'.$rental->garage_id, $rental->end_date->toDateString()),
            );
        });
    }

    private function matches(Rental $rental, string $keyword): bool
    {
        return collect([$rental->code, $rental->garage->code, $rental->user->name, $rental->vehicle->plate_number])
            ->contains(fn ($value): bool => $value !== null && Str::contains((string) $value, $keyword, true));
    }
}
