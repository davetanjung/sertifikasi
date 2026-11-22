<?php

declare(strict_types=1);

namespace App\Livewire\Rental;

use App\Models\Rental;
use App\Services\RentalService;
use App\Traits\Livewire\WithShowForm;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Show extends Component
{
    use WithShowForm;

    public $model = Rental::class;

    public $menuTitle = 'Rental';

    public Rental $obj;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin() || $this->obj->user_id === auth()->id(), 403);
        abort_unless($this->obj->status === Const_Status::NEW, 404);

        $this->obj->load(['garage', 'vehicle', 'user']);
    }

    public function render(): View
    {
        $extensions = Rental::query()
            ->where('code', $this->obj->code)
            ->where('user_id', $this->obj->user_id)
            ->where('garage_id', $this->obj->garage_id)
            ->where('status', Const_Status::EXTENSION)
            ->orderBy('start_date')
            ->get();

        $chargings = $this->obj->chargingTransactions()->latest()->get();

        $totalRent = (float) $this->obj->rent_amount + $extensions->sum(fn (Rental $row): float => (float) $row->rent_amount);
        $totalCharging = $chargings->sum(fn ($charging): float => (float) $charging->amount);
        $isActive = RentalService::isActive($this->obj);
        $latestEndDate = RentalService::latestEndDate($this->obj);
        $canExtend = auth()->user()->isAdmin() && $latestEndDate->gte(today());

        $canCharge = $isActive
            && $this->obj->user_id === auth()->id()
            && $this->obj->garage->isElectric()
            && $this->obj->vehicle->type === Const_Type::VEHICLE_ELECTRIC_CAR
            && $this->obj->vehicle->maximum_capacity_kw !== null;

        return view('rental.show', [
            'extensions' => $extensions,
            'chargings' => $chargings,
            'latestEndDate' => $latestEndDate,
            'canExtend' => $canExtend,
            'totalRent' => $totalRent,
            'totalCharging' => $totalCharging,
            'grandTotal' => $totalRent + $totalCharging,
            'isActive' => $isActive,
            'canCharge' => $canCharge,
        ])->layout($this->layout);
    }
}
