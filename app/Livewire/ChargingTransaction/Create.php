<?php

declare(strict_types=1);

namespace App\Livewire\ChargingTransaction;

use App\Exceptions\GeneralException;
use App\Models\ChargingTransaction;
use App\Models\Rental;
use App\Services\ChargingTransactionService;
use App\Services\RentalService;
use App\Traits\Livewire\WithCreateForm;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

final class Create extends Component
{
    use WithCreateForm;
    use WithFileUploads;

    public $model = ChargingTransaction::class;

    public $menuTitle = 'Charging';

    #[Url(as: 'rental')]
    public $rental_id;

    public $current_capacity_kw;

    public $payment_proof;

    public function mount(): void
    {
        abort_if(auth()->user()->isAdmin(), 403);

        $this->loadRental();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $capacityRules = ['required', 'numeric', 'min:0'];
        $maximum = $this->maximumCapacity($this->loadRental());

        if ($maximum !== null) {
            $capacityRules[] = 'max:'.$maximum;
        }

        return [
            'current_capacity_kw' => $capacityRules,
            'payment_proof' => ['required', 'image', 'max:2048'],
        ];
    }

    public function submit(array $validated): ChargingTransaction
    {
        $rental = $this->loadRental();
        $path = $this->payment_proof->store('charging-proofs', 'public');

        try {
            return ChargingTransactionService::create($rental, $validated['current_capacity_kw'], $path);
        } catch (Exception $exception) {
            // Nothing was saved, so do not leave the uploaded proof behind.
            Storage::disk('public')->delete($path);

            throw $exception;
        }
    }

    protected function submitRedirectUrl(): ?string
    {
        return route('rental.show', $this->rental_id);
    }

    public function render(): View
    {
        $rental = $this->loadRental();
        $maximum = $this->maximumCapacity($rental);
        $blockedReason = $this->blockedReason($rental);

        $preview = null;
        $previewError = null;

        if ($blockedReason === null
            && filled($this->current_capacity_kw)
            && is_numeric($this->current_capacity_kw)
            && (float) $this->current_capacity_kw >= 0
            && ($maximum === null || (float) $this->current_capacity_kw <= $maximum)) {
            try {
                $preview = ChargingTransactionService::preview($rental, $this->current_capacity_kw);
            } catch (GeneralException $exception) {
                $previewError = $exception->getMessage();
            }
        }

        return view('charging-transaction.create', [
            'rental' => $rental,
            'maximumCapacity' => $maximum,
            'preview' => $preview,
            'previewError' => $previewError,
            'blockedReason' => $blockedReason,
        ])->layout($this->layout);
    }

    /**
     * The chosen rental: it must exist, be the new row and belong to the logged-in tenant.
     * Checked on every request because the id lives in the URL and can be changed.
     */
    private function loadRental(): Rental
    {
        $rental = Rental::query()->with(['garage', 'vehicle'])->find($this->rental_id);

        abort_if($rental === null || $rental->status !== Const_Status::NEW, 404);
        abort_if($rental->user_id !== auth()->id(), 403);

        return $rental;
    }

    private function maximumCapacity(Rental $rental): ?float
    {
        $maximum = $rental->vehicle->maximum_capacity_kw;

        return $maximum === null ? null : (float) $maximum;
    }

    /**
     * The first simple reason why this rental cannot be charged, or null when charging is possible.
     */
    private function blockedReason(Rental $rental): ?string
    {
        return match (true) {
            ! RentalService::isActive($rental) => 'The rental is not running today.',
            ! $rental->garage->isElectric() => 'Charging is only available in an electric car garage.',
            $rental->vehicle->type !== Const_Type::VEHICLE_ELECTRIC_CAR
                || $rental->vehicle->maximum_capacity_kw === null => 'Charging is only available for an electric car with a maximum capacity.',
            $rental->garage->price_per_kwh === null => 'The garage has no price per kWh.',
            default => null,
        };
    }
}
