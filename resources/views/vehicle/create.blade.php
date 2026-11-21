@php
    use App\Utilities\Constants\Const_Type;
@endphp

<div>
    <x-flash />

    <x-page-header title="Register vehicle" subtitle="Add a vehicle you want to park in a garage." />

    <x-card class="max-w-2xl">
        <form wire:submit="submitDefault" class="space-y-5" novalidate>
            <x-form.select label="Type" name="type" :options="$typeOptions" wire:model.live="type" />

            @if ($type !== Const_Type::VEHICLE_BICYCLE)
                <x-form.input label="Plate number" name="plate_number" wire:model="plate_number" autocomplete="off" />
            @else
                <p class="rounded-md bg-neutral-100 px-3 py-2 text-sm text-neutral-700">A bicycle has no plate number.</p>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input label="Brand" name="brand" wire:model="brand" autocomplete="off" />
                <x-form.input label="Model" name="vehicle_model" wire:model="vehicle_model" autocomplete="off" />
            </div>

            @if ($type === Const_Type::VEHICLE_ELECTRIC_CAR)
                <x-form.input label="Battery capacity (kW)" name="maximum_capacity_kw" type="number" step="0.01" min="0" inputmode="decimal" wire:model="maximum_capacity_kw" />
            @endif

            <div class="flex flex-col-reverse gap-3 border-t border-neutral-300 pt-5 sm:flex-row sm:justify-end">
                <a
                    href="{{ route('vehicle.index') }}"
                    class="inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-neutral-700 transition hover:text-black focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
                >
                    Cancel
                </a>
                <x-button variant="secondary" type="button" wire:click="submitAndCreate" wire:loading.attr="disabled">Save and add another</x-button>
                <x-button wire:loading.attr="disabled">Save</x-button>
            </div>
        </form>
    </x-card>
</div>
