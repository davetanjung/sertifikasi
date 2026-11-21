<div>
    <x-flash />

    <a
        href="{{ route('rental.show', $rental->id) }}"
        class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-neutral-700 hover:text-black focus:outline-none focus-visible:ring-2 focus-visible:ring-black"
    >
        <x-icon name="arrow-left" class="size-4" />
        Back to rental
    </a>

    <x-page-header title="Add charging" subtitle="Charge your electric car to full in this garage." />

    <div class="mb-6">
        <x-card title="Rental">
            <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-neutral-600">Rental code</dt>
                    <dd class="mt-0.5 font-medium text-black">{{ $rental->code }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-600">Garage</dt>
                    <dd class="mt-0.5 font-medium text-black">
                        {{ $rental->garage?->code }}
                        @if ($rental->garage?->price_per_kwh !== null)
                            <span class="block text-xs font-normal text-neutral-700">Charging {{ _money($rental->garage->price_per_kwh, 'Rp') }} / kWh</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-neutral-600">Vehicle</dt>
                    <dd class="mt-0.5 font-medium text-black">{{ $rental->vehicle?->plate_number ?: trim($rental->vehicle?->brand.' '.$rental->vehicle?->model) }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-600">Battery</dt>
                    <dd class="mt-0.5 flex items-center gap-1.5 font-medium text-black">
                        <x-icon name="bolt" class="size-4 text-neutral-500" />
                        Maximum battery: {{ $maximumCapacity !== null ? $maximumCapacity.' kW' : '-' }}
                    </dd>
                </div>
            </dl>
        </x-card>
    </div>

    @if ($blockedReason)
        <div class="rounded-md border-2 border-red-800 bg-white p-5" role="alert">
            <div class="flex items-start gap-3">
                <x-icon name="exclamation-circle" class="mt-0.5 size-6 shrink-0 text-red-800" />
                <div>
                    <h2 class="text-base font-semibold text-red-800">Charging is not available</h2>
                    <p class="mt-1 text-sm text-neutral-800">{{ $blockedReason }}</p>
                    <a
                        href="{{ route('rental.show', $rental->id) }}"
                        class="mt-4 inline-flex h-10 items-center justify-center rounded-md bg-black px-4 text-sm font-semibold text-white transition hover:bg-neutral-800 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
                    >
                        Back to rental
                    </a>
                </div>
            </div>
        </div>
    @else
        <form wire:submit="submitDefault" class="grid items-start gap-6 lg:grid-cols-3" novalidate>
            <div class="space-y-6 lg:col-span-2">
                <x-card title="Charging details">
                    <div class="space-y-5">
                        <p class="rounded-md bg-neutral-100 px-3 py-2 text-sm text-neutral-700">Enter the battery level of your car right now in kW (not a percentage). The system charges it to full.</p>

                        <x-form.input
                            label="Current battery (kW)"
                            name="current_capacity_kw"
                            type="number"
                            step="0.01"
                            min="0"
                            :max="$maximumCapacity"
                            inputmode="decimal"
                            wire:model.live.debounce.400ms="current_capacity_kw"
                        />
                    </div>
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
                        href="{{ route('rental.show', $rental->id) }}"
                        class="inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-neutral-700 transition hover:text-black focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
                    >
                        Cancel
                    </a>
                    <x-button wire:loading.attr="disabled">Save charging</x-button>
                </div>
            </div>

            <div class="lg:sticky lg:top-6">
                <x-card title="Charging summary">
                    @if ($preview)
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-neutral-700">To charge</dt>
                                <dd class="font-medium text-black">{{ $preview['charged_kwh'] }} kWh</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-neutral-700">Price per kWh</dt>
                                <dd class="font-medium text-black">{{ _money($preview['price_per_kwh'], 'Rp') }}</dd>
                            </div>
                        </dl>
                        <div class="mt-5 rounded-md border border-black bg-white p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-black">Total to pay</p>
                            <p class="mt-1 text-2xl font-bold text-black">{{ _money($preview['amount'], 'Rp') }}</p>
                        </div>
                        <p class="mt-3 text-xs text-neutral-600">Payment is due upfront. Upload the proof of payment on the left.</p>
                    @elseif ($previewError)
                        <p class="text-sm font-bold text-red-800" role="alert">{{ $previewError }}</p>
                    @else
                        <p class="text-sm text-neutral-700">Enter the current battery to see the price</p>
                    @endif
                </x-card>
            </div>
        </form>
    @endif
</div>
