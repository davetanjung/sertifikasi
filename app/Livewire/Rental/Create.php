<?php

declare(strict_types=1);

namespace App\Livewire\Rental;

use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Services\GarageService;
use App\Services\RentalService;
use App\Traits\Livewire\WithCreateForm;
use App\Utilities\Constants\Const_Type;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;

final class Create extends Component
{
    use WithCreateForm;
    use WithFileUploads;

    public $model = Rental::class;

    public $menuTitle = 'Rental';

    public $vehicle_id;

    public $rental_type = Const_Type::RENTAL_DAILY;

    public $days = 1;

    public $start_date;

    public $garage_id;

    public $payment_proof;

    public $garage_search = '';

    public int $garage_limit = self::GARAGE_PAGE_SIZE;

    private const GARAGE_PAGE_SIZE = 12;

    public function mount(): void
    {
        abort_if(auth()->user()->isAdmin(), 403);

        $this->start_date = today()->toDateString();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('user_id', auth()->id())],
            'rental_type' => ['required', 'in:'.Const_Type::RENTAL_DAILY.','.Const_Type::RENTAL_MONTHLY],
            'days' => ['required_if:rental_type,'.Const_Type::RENTAL_DAILY, 'nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'garage_id' => ['required', 'exists:garages,id'],
            'payment_proof' => ['required', 'image', 'max:2048'],
        ];
    }

    /**
     * The form is live: a garage that is no longer free for the new vehicle or dates is cleared.
     */
    public function updated($property): void
    {
        if (in_array($property, ['vehicle_id', 'rental_type', 'days', 'start_date', 'garage_search'], true)) {
            $this->garage_limit = self::GARAGE_PAGE_SIZE;
        }

        if (! in_array($property, ['vehicle_id', 'rental_type', 'days', 'start_date'], true)) {
            return;
        }

        if ($this->garage_id && ! $this->selectedGarageIsFree()) {
            $this->garage_id = null;
        }
    }

    public function showMoreGarages(): void
    {
        $this->garage_limit += self::GARAGE_PAGE_SIZE;
    }

    public function submit(array $validated): Rental
    {
        $path = $this->payment_proof->store('payment-proofs', 'public');

        try {
            return RentalService::create([
                'user_id' => auth()->id(),
                'vehicle_id' => $validated['vehicle_id'],
                'garage_id' => $validated['garage_id'],
                'rental_type' => $validated['rental_type'],
                'days' => $validated['days'] ?? null,
                'start_date' => $validated['start_date'],
                'payment_proof_path' => $path,
            ]);
        } catch (Exception $exception) {
            // Nothing was saved, so do not leave the uploaded proof behind.
            Storage::disk('public')->delete($path);

            throw $exception;
        }
    }

    public function render(): View
    {
        $period = null;
        $previewError = null;
        $preview = null;

        try {
            $period = $this->resolvePeriod();

            if ($this->garage_id) {
                $preview = RentalService::preview([
                    'garage_id' => $this->garage_id,
                    'rental_type' => $this->rental_type,
                    'start_date' => $this->start_date,
                    'days' => $this->days,
                ]);
            }
        } catch (GeneralException|InvalidArgumentException $exception) {
            $previewError = $exception instanceof GeneralException
                ? $exception->getMessage()
                : 'Start date is not a valid date.';
        }

        $garageList = $this->garageList();

        return view('rental.create', [
            'vehicles' => $this->vehicleOptions(),
            'rentalTypeOptions' => [
                Const_Type::RENTAL_DAILY => 'Daily',
                Const_Type::RENTAL_MONTHLY => 'Monthly',
            ],
            'garages' => $garageList['garages'],
            'garagesTotal' => $garageList['total'],
            'garagesShown' => $garageList['garages']->count(),
            'hasMoreGarages' => $garageList['total'] > $garageList['garages']->count(),
            'preview' => $preview,
            'period' => $period,
            'previewError' => $previewError,
        ])->layout($this->layout);
    }

    /**
     * The start date, end date and days of the chosen terms.
     *
     * @return array{start_date: string, end_date: string, days: int}
     *
     * @throws GeneralException
     */
    private function resolvePeriod(): array
    {
        return RentalService::period(
            (string) $this->rental_type,
            filled($this->start_date) ? (string) $this->start_date : null,
            $this->days,
        );
    }

    /**
     * The tenant's chosen vehicle and the period, or null when either is missing or invalid.
     *
     * @return array{vehicle: Vehicle, period: array{start_date: string, end_date: string, days: int}}|null
     */
    private function garageContext(): ?array
    {
        $vehicle = $this->vehicle_id
            ? Vehicle::query()->where('user_id', auth()->id())->find($this->vehicle_id)
            : null;

        if ($vehicle === null) {
            return null;
        }

        try {
            return ['vehicle' => $vehicle, 'period' => $this->resolvePeriod()];
        } catch (GeneralException|InvalidArgumentException) {
            return null;
        }
    }

    /**
     * One page of the free garages that fit the chosen vehicle, filtered by the search text and
     * sorted by code. The order never changes when a garage is selected.
     * Empty when there is no vehicle or the dates are invalid.
     *
     * @return array{garages: Collection<int, Garage>, total: int}
     */
    private function garageList(): array
    {
        $context = $this->garageContext();

        if ($context === null) {
            return ['garages' => new Collection, 'total' => 0];
        }

        $result = GarageService::availableForTypes(
            GarageService::typesFor($context['vehicle']->type),
            $context['period']['start_date'],
            $context['period']['end_date'],
            filled($this->garage_search) ? (string) $this->garage_search : null,
            $this->garage_limit,
        );

        return ['garages' => new Collection($result['garages']->all()), 'total' => $result['total']];
    }

    /**
     * The selected garage when it is free for the period and fits the vehicle (one query).
     *
     * @param  array{vehicle: Vehicle, period: array{start_date: string, end_date: string, days: int}}  $context
     */
    private function selectedGarage(array $context): ?Garage
    {
        $query = Garage::query()
            ->whereKey($this->garage_id)
            ->whereIn('type', GarageService::typesFor($context['vehicle']->type));

        GarageService::whereFree($query, $context['period']['start_date'], $context['period']['end_date']);

        return $query->first();
    }

    private function selectedGarageIsFree(): bool
    {
        $context = $this->garageContext();

        return $context !== null && $this->selectedGarage($context) !== null;
    }

    /**
     * The tenant's vehicles as id => label.
     *
     * @return array<string, string>
     */
    private function vehicleOptions(): array
    {
        return Vehicle::query()
            ->where('user_id', auth()->id())
            ->orderBy('brand')
            ->orderBy('model')
            ->get()
            ->mapWithKeys(fn (Vehicle $vehicle): array => [
                $vehicle->id => $vehicle->plate_number !== null
                    ? $vehicle->plate_number.' - '.$vehicle->brand.' '.$vehicle->model
                    : 'Bicycle - '.$vehicle->brand.' '.$vehicle->model,
            ])
            ->all();
    }
}
