<?php

declare(strict_types=1);

namespace App\Livewire\Garage;

use App\Exceptions\GeneralException;
use App\Models\Garage;
use App\Services\GarageService;
use App\Traits\Livewire\WithCreateForm;
use App\Utilities\Constants\Const_Type;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Create extends Component
{
    use WithCreateForm;

    public $model = Garage::class;

    public $menuTitle = 'Garages';

    public $type = Const_Type::GARAGE_CAR;

    public $quantity = 1;

    public $daily_price;

    public $price_per_kwh;

    /**
     * Runs on every request (mount only runs once), so the flash reads "Garages have been created."
     */
    public function boot(): void
    {
        $this->submitMessage = 'have been created';
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', 'in:'.implode(',', Const_Type::GARAGE_TYPES)],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'daily_price' => ['required', 'numeric', 'min:0'],
            'price_per_kwh' => ['required_if:type,'.Const_Type::GARAGE_ELECTRIC_CAR, 'nullable', 'numeric', 'min:0'],
        ];
    }

    public function submit(array $validated): Garage
    {
        $garages = GarageService::createBatch(
            $validated['type'],
            (int) $validated['quantity'],
            $validated['daily_price'],
            $validated['price_per_kwh'] ?? null,
        );

        return $garages->first();
    }

    protected function submitRedirectUrl(): ?string
    {
        return route('garage.index');
    }

    public function render(): View
    {
        return view('garage.create', [
            'typeOptions' => [
                Const_Type::GARAGE_BICYCLE => 'Bicycle',
                Const_Type::GARAGE_CAR => 'Car',
                Const_Type::GARAGE_ELECTRIC_CAR => 'Electric car',
            ],
            'codeHint' => $this->codeHint(),
        ])->layout($this->layout);
    }

    /**
     * @return array{first: string|null, last: string|null, remaining: int, fits: bool}|null
     */
    private function codeHint(): ?array
    {
        $quantity = filter_var($this->quantity, FILTER_VALIDATE_INT);

        if ($quantity === false || ! in_array($this->type, Const_Type::GARAGE_TYPES, true)) {
            return null;
        }

        try {
            return GarageService::nextCodes((string) $this->type, $quantity);
        } catch (GeneralException) {
            return null;
        }
    }
}
