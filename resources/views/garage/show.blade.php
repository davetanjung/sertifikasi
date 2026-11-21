@php
    use App\Utilities\Constants\Const_Status;
@endphp

<div>
    <x-flash />

    <x-page-header title="Garage details" :subtitle="$obj->code">
        <x-slot:actions>
            <a
                href="{{ route('garage.index') }}"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-white px-4 text-sm font-semibold text-neutral-800 border border-neutral-400 transition hover:bg-neutral-100 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
            >
                <x-icon name="arrow-left" class="size-4" />
                Back to list
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-6">
        <x-card title="Details">
            <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Code</dt>
                    <dd class="mt-1.5 text-sm font-medium text-black">{{ $obj->code }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Type</dt>
                    <dd class="mt-1.5"><x-garage-type-badge :type="$obj->type" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Daily price</dt>
                    <dd class="mt-1.5 text-sm text-black">{{ _money($obj->daily_price, 'Rp') }}</dd>
                </div>
                @if ($obj->price_per_kwh !== null)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Price per kWh</dt>
                        <dd class="mt-1.5 text-sm text-black">{{ _money($obj->price_per_kwh, 'Rp') }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Today</dt>
                    <dd class="mt-1.5">
                        @if ($usedToday)
                            <x-badge color="amber">Used today</x-badge>
                        @else
                            <x-badge color="green">Free today</x-badge>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Created</dt>
                    <dd class="mt-1.5 text-sm text-black">{{ _date_format_output($obj->created_at) }}</dd>
                </div>
            </dl>
            <p class="mt-5 text-xs text-neutral-600">The monthly price is always 25 x the daily price.</p>
        </x-card>

        <section>
            <h2 class="mb-3 text-base font-semibold text-black">Latest rentals</h2>
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-4 py-3">Code</th>
                    <th scope="col" class="px-4 py-3">Tenant</th>
                    <th scope="col" class="px-4 py-3">Vehicle</th>
                    <th scope="col" class="px-4 py-3">Start date</th>
                    <th scope="col" class="px-4 py-3">End date</th>
                    <th scope="col" class="px-4 py-3">Kind</th>
                    <th scope="col" class="px-4 py-3 text-right">Amount</th>
                </x-slot:head>

                @forelse ($rentals as $rental)
                    <tr wire:key="rental-{{ $rental->id }}">
                        <td class="whitespace-nowrap px-4 py-3 font-medium">
                            <a href="{{ $rental->getRouteShow() }}" class="text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black">{{ $rental->code }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $rental->user?->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $rental->vehicle?->plate_number ?: $rental->vehicle?->brand.' '.$rental->vehicle?->model }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($rental->start_date) }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($rental->end_date) }}</td>
                        <td class="px-4 py-3">
                            @if ($rental->status === Const_Status::EXTENSION)
                                <x-badge color="amber">Extension</x-badge>
                            @else
                                <x-badge color="blue">Rental</x-badge>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($rental->rent_amount, 'Rp') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-empty-state title="No rentals yet" text="This garage has not been rented." />
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </section>
    </div>
</div>
