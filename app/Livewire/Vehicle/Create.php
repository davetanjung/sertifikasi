<?php

declare(strict_types=1);

namespace App\Livewire\Vehicle;

use App\Models\Vehicle;
use App\Services\VehicleService;
use App\Traits\Livewire\WithCreateForm;
use App\Utilities\Constants\Const_Type;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Create extends Component
{
    use WithCreateForm;

    public $model = Vehicle::class;

    public $menuTitle = 'Vehicle';

    public $type = Const_Type::VEHICLE_CAR;

    public $plate_number;

    public $brand;

    /**
     * Maps to the `model` column ($model is reserved by the traits).
     */
    public $vehicle_model;

    public $maximum_capacity_kw;

    public function mount(): void
    {
        abort_if(auth()->user()->isAdmin(), 403);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', 'in:'.implode(',', array_keys($this->typeOptions()))],
            'plate_number' => ['required_unless:type,'.Const_Type::VEHICLE_BICYCLE, 'nullable', 'string', 'max:20', 'unique:vehicles,plate_number'],
            'brand' => ['required', 'string', 'max:255'],
            'vehicle_model' => ['required', 'string', 'max:255'],
            'maximum_capacity_kw' => ['required_if:type,'.Const_Type::VEHICLE_ELECTRIC_CAR, 'nullable', 'numeric', 'min:0.01', 'max:9999.99'],
        ];
    }

    public function submit(array $validated): Vehicle
    {
        return VehicleService::create([
            'type' => $validated['type'],
            'plate_number' => $validated['plate_number'] ?? null,
            'brand' => $validated['brand'],
            'model' => $validated['vehicle_model'],
            'maximum_capacity_kw' => $validated['maximum_capacity_kw'] ?? null,
            'user_id' => auth()->id(),
        ]);
    }

    public function render(): View
    {
        return view('vehicle.create', [
            'typeOptions' => $this->typeOptions(),
        ])->layout($this->layout);
    }

    /**
     * @return array<string, string>
     */
    private function typeOptions(): array
    {
        return [
            Const_Type::VEHICLE_BICYCLE => 'Bicycle',
            Const_Type::VEHICLE_MOTORCYCLE => 'Motorcycle',
            Const_Type::VEHICLE_CAR => 'Car',
            Const_Type::VEHICLE_ELECTRIC_CAR => 'Electric car',
        ];
    }
}
