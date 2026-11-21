@php
    use App\Utilities\Constants\Const_Type;
@endphp

<div>
    <x-flash />

    <x-page-header title="Vehicle details" :subtitle="$obj->brand.' '.$obj->model">
        <x-slot:actions>
            <a
                href="{{ route('vehicle.index') }}"
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
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Type</dt>
                    <dd class="mt-1.5"><x-vehicle-type-badge :type="$obj->type" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Plate number</dt>
                    <dd class="mt-1.5 text-sm text-black">{{ $obj->plate_number ?: 'No plate' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Brand</dt>
                    <dd class="mt-1.5 text-sm text-black">{{ $obj->brand }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Model</dt>
                    <dd class="mt-1.5 text-sm text-black">{{ $obj->model }}</dd>
                </div>
                @if ($obj->type === Const_Type::VEHICLE_ELECTRIC_CAR)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Battery capacity</dt>
                        <dd class="mt-1.5 text-sm text-black">{{ _get_formatted_number($obj->maximum_capacity_kw) }} kW</dd>
                    </div>
                @endif
                @if (auth()->user()->isAdmin())
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Owner</dt>
                        <dd class="mt-1.5 text-sm text-black">{{ $obj->user->name }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Registered</dt>
                    <dd class="mt-1.5 text-sm text-black">{{ _date_format_output($obj->created_at) }}</dd>
                </div>
            </dl>
        </x-card>

        <section>
            <h2 class="mb-3 text-base font-semibold text-black">Latest rentals</h2>
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-4 py-3">Code</th>
                    <th scope="col" class="px-4 py-3">Garage</th>
                    <th scope="col" class="px-4 py-3">Start date</th>
                    <th scope="col" class="px-4 py-3">End date</th>
                    <th scope="col" class="px-4 py-3">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Rent</th>
                </x-slot:head>

                @forelse ($rentals as $rental)
                    <tr wire:key="rental-{{ $rental->id }}">
                        <td class="whitespace-nowrap px-4 py-3 font-medium">
                            <a href="{{ $rental->getRouteShow() }}" class="text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black">{{ $rental->code }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $rental->garage?->code }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($rental->start_date) }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($rental->end_date) }}</td>
                        <td class="px-4 py-3"><x-badge color="blue">{{ str($rental->status)->replace('_', ' ')->ucfirst() }}</x-badge></td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($rental->rent_amount, 'Rp') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-empty-state title="No rentals yet" text="This vehicle has not been used in any rental." />
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </section>
    </div>
</div>
