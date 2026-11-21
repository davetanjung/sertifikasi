@php
    use App\Utilities\Constants\Const_Type;
@endphp

<div>
    <x-flash />

    <x-page-header title="New rental" subtitle="Choose a vehicle, the dates and a free garage." />

    <form wire:submit="submitDefault" class="grid items-start gap-6 lg:grid-cols-3" novalidate>
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Rental details">
                <div class="space-y-5">
                    <x-form.select label="Vehicle" name="vehicle_id" :options="$vehicles" placeholder="Select a vehicle" wire:model.live="vehicle_id" />

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.select label="Rental type" name="rental_type" :options="$rentalTypeOptions" wire:model.live="rental_type" />

                        @if ($rental_type === Const_Type::RENTAL_DAILY)
                            <x-form.input label="Number of days" name="days" type="number" min="1" max="31" inputmode="numeric" wire:model.live="days" />
                        @else
                            <p class="self-end rounded-md bg-neutral-100 px-3 py-2 text-sm text-neutral-700">A monthly rental lasts one full month from the start date.</p>
                        @endif
                    </div>

                    <div>
                        <x-form.input label="Start date" name="start_date" type="date" :min="today()->toDateString()" wire:model.live="start_date" />
                        @if ($previewError)
                            <p class="mt-1.5 text-xs font-bold text-red-800" role="alert">{{ $previewError }}</p>
                        @elseif ($period)
                            <p class="mt-1.5 flex items-center gap-1.5 text-xs text-neutral-700">
                                <x-icon name="calendar" class="size-4 text-neutral-500" />
                                From {{ _date_format_output($period['start_date']) }} to {{ _date_format_output($period['end_date']) }} ({{ $period['days'] }} {{ $period['days'] === 1 ? 'day' : 'days' }})
                            </p>
                        @endif
                    </div>
                </div>
            </x-card>

            <x-card title="Garage">
                @if (! $vehicle_id)
                    <p class="rounded-md bg-neutral-100 px-3 py-2 text-sm text-neutral-700">Choose a vehicle first.</p>
                @elseif (! $period)
                    <p class="rounded-md bg-neutral-100 px-3 py-2 text-sm text-neutral-700">Choose a valid start date to see the free garages.</p>
                @else
                    <div class="mb-3">
                        <label for="garage_search" class="mb-1.5 block text-sm font-medium text-neutral-800">Search garage code</label>
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-neutral-500" />
                            <input
                                id="garage_search"
                                type="search"
                                placeholder="e.g. C012"
                                autocomplete="off"
                                wire:model.live.debounce.400ms="garage_search"
                                class="block h-10 w-full rounded-md border border-neutral-400 bg-white pl-9 pr-3 text-sm text-black placeholder:text-neutral-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-black"
                            >
                        </div>
                    </div>

                    @if ($garagesTotal === 0)
                        @if (filled($garage_search))
                            <p class="rounded-md border border-black px-3 py-2 text-sm font-medium text-black">No free garage matches your search.</p>
                        @else
                            <p class="rounded-md border border-black px-3 py-2 text-sm font-medium text-black">No free garage for those dates. Try another start date or length.</p>
                        @endif
                    @else
                        <p class="mb-2 text-xs text-neutral-600">Showing {{ $garagesShown }} of {{ $garagesTotal }} free {{ $garagesTotal === 1 ? 'garage' : 'garages' }}</p>
                        <fieldset>
                            <legend class="sr-only">Garage</legend>
                            <div class="max-h-80 space-y-2 overflow-y-auto pr-1">
                                @foreach ($garages as $garage)
                                    <label
                                        wire:key="garage-{{ $garage->id }}"
                                        class="flex cursor-pointer items-center gap-3 rounded-md border border-neutral-400 bg-white px-3 py-2 text-sm transition hover:bg-neutral-100 has-[:checked]:border-2 has-[:checked]:border-black has-[:checked]:bg-neutral-100 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-black"
                                    >
                                        <input type="radio" name="garage_id" value="{{ $garage->id }}" wire:model.live="garage_id" class="size-4 shrink-0 border-neutral-400 text-black focus:outline-none">
                                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                            <span class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                                <span class="font-semibold text-black">{{ $garage->code }} - {{ _money($garage->daily_price, 'Rp') }} / day</span>
                                                @if ($garage->type === Const_Type::GARAGE_ELECTRIC_CAR)
                                                    <x-garage-type-badge :type="$garage->type" label="Electric garage" />
                                                @elseif ($garage->type === Const_Type::GARAGE_CAR)
                                                    <x-garage-type-badge :type="$garage->type" label="Car garage" />
                                                @elseif ($garage->type === Const_Type::GARAGE_BICYCLE)
                                                    <x-garage-type-badge :type="$garage->type" label="Bicycle garage" />
                                                @endif
                                            </span>
                                            @if ($garage->type === Const_Type::GARAGE_ELECTRIC_CAR)
                                                <span class="text-xs text-neutral-600">Charging available ({{ _money($garage->price_per_kwh, 'Rp') }} / kWh)</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        @if ($hasMoreGarages)
                            <div class="mt-3">
                                <x-button type="button" variant="secondary" wire:click="showMoreGarages" wire:loading.attr="disabled" wire:target="showMoreGarages">
                                    <span wire:loading.remove wire:target="showMoreGarages">Show more garages</span>
                                    <span wire:loading wire:target="showMoreGarages">Loading...</span>
                                </x-button>
                            </div>
                        @endif
                    @endif
                @endif
                @error('garage_id')
                    <p class="mt-2 text-xs font-bold text-red-800" role="alert">{{ $message }}</p>
                @enderror
            </x-card>

            <x-card title="Proof of payment">
                <label for="payment_proof" class="mb-1.5 block text-sm font-medium text-neutral-800">Upload proof (image, max 2 MB)</label>
                <input
                    id="payment_proof"
                    type="file"
                    accept="image/*"
                    wire:model="payment_proof"
                    class="block w-full text-sm text-neutral-800 file:mr-4 file:rounded-md file:border file:border-black file:bg-white file:px-4 file:py-2 file:text-sm file:font-semibold file:text-black hover:file:bg-neutral-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-black"
                >
                <p wire:loading wire:target="payment_proof" class="mt-2 flex items-center gap-1.5 text-xs text-neutral-700">
                    <x-icon name="upload" class="size-4" />
                    Uploading...
                </p>
                @error('payment_proof')
                    <p class="mt-1.5 text-xs font-bold text-red-800" role="alert">{{ $message }}</p>
                @enderror
@if ($payment_proof && ! $errors->has('payment_proof'))
                    <div wire:loading.remove wire:target="payment_proof" class="mt-3 flex items-center gap-3">
                        @if ($payment_proof->isPreviewable())
                            <img src="{{ $payment_proof->temporaryUrl() }}" alt="Preview of the proof of payment" class="size-20 rounded-md object-cover border border-neutral-300">
                        @endif
                        <span class="text-sm text-neutral-700">{{ $payment_proof->getClientOriginalName() }}</span>
                    </div>
                @endif
            </x-card>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a
                    href="{{ route('rental.index') }}"
                    class="inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-neutral-700 transition hover:text-black focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
                >
                    Cancel
                </a>
                <x-button wire:loading.attr="disabled">Save rental</x-button>
            </div>
        </div>

        <div class="lg:sticky lg:top-6">
            <x-card title="Price summary">
                @if ($preview)
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-neutral-700">Daily price</dt>
                            <dd class="font-medium text-black">{{ _money($preview['daily_price'], 'Rp') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-neutral-700">Number of days</dt>
                            <dd class="font-medium text-black">{{ $preview['days'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-neutral-700">Start date</dt>
                            <dd class="font-medium text-black">{{ _date_format_output($preview['start_date']) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-neutral-700">End date</dt>
                            <dd class="font-medium text-black">{{ _date_format_output($preview['end_date']) }}</dd>
                        </div>
                    </dl>
                    @if ($preview['rental_type'] === Const_Type::RENTAL_MONTHLY)
                        <p class="mt-3 text-xs text-neutral-600">Monthly rate = 25 x daily price.</p>
                    @endif
                    <div class="mt-5 rounded-md border border-black bg-white p-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-black">Total to pay</p>
                        <p class="mt-1 text-2xl font-bold text-black">{{ _money($preview['rent_amount'], 'Rp') }}</p>
                    </div>
                    <p class="mt-3 text-xs text-neutral-600">Payment is due upfront. Upload the proof of payment below.</p>
                @else
                    <p class="text-sm text-neutral-700">Choose a vehicle, the dates and a garage to see the price.</p>
                @endif
            </x-card>
        </div>
    </form>
</div>
