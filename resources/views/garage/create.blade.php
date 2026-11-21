@php
    use App\Utilities\Constants\Const_Type;
@endphp

<div>
    <x-flash />

    <x-page-header title="Create garages" subtitle="Add a batch of garages of one type. Codes are numbered automatically." />

    <form wire:submit="submitDefault" class="grid items-start gap-6 lg:grid-cols-3" novalidate>
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Garage details">
                <div class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.select label="Type" name="type" :options="$typeOptions" wire:model.live="type" />
                        <x-form.input label="Quantity" name="quantity" type="number" min="1" max="999" inputmode="numeric" wire:model.live.debounce.400ms="quantity" />
                    </div>

                    <div>
                        <x-form.input label="Daily price (Rp)" name="daily_price" type="number" min="0" step="0.01" inputmode="decimal" wire:model="daily_price" />
                        <p class="mt-1.5 text-xs text-neutral-600">A monthly rental costs the same as 25 days at this price.</p>
                    </div>

                    @if ($type === Const_Type::GARAGE_ELECTRIC_CAR)
                        <x-form.input label="Price per kWh (Rp)" name="price_per_kwh" type="number" min="0" step="0.01" inputmode="decimal" wire:model="price_per_kwh" />
                    @endif
                </div>
            </x-card>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a
                    href="{{ route('garage.index') }}"
                    class="inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-neutral-700 transition hover:text-black focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
                >
                    Cancel
                </a>
                <x-button wire:loading.attr="disabled">Create garages</x-button>
            </div>
        </div>

        <div class="lg:sticky lg:top-6">
            <x-card title="What will be created">
                @if ($codeHint === null)
                    <p class="text-sm text-neutral-700">Choose a type and a quantity from 1 to 999 to see the garage codes.</p>
                @elseif ($codeHint['fits'])
                    <div class="space-y-3 text-sm" aria-live="polite">
                        <p class="rounded-md border border-black bg-white p-4 font-medium text-black">
                            This will create {{ $quantity }} {{ (int) $quantity === 1 ? 'garage' : 'garages' }}:
                            {{ $codeHint['first'] }}@if ($codeHint['first'] !== $codeHint['last']) to {{ $codeHint['last'] }}@endif
                        </p>
                        <p class="text-neutral-700">{{ $codeHint['remaining'] }} more {{ $typeOptions[$type] ?? $type }} garages can still be created.</p>
                    </div>
                @else
                    <p class="rounded-md border-2 border-red-800 bg-white p-4 text-sm font-bold text-red-800" role="alert">
                        Only {{ $codeHint['remaining'] }} more can be created (maximum 999 per type).
                    </p>
                @endif
            </x-card>
        </div>
    </form>
</div>
