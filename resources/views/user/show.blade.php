@php
    use App\Utilities\Constants\Const_Status;

    $statusColor = match ($obj->registration_status) {
        Const_Status::APPROVED => 'green',
        Const_Status::REJECTED => 'red',
        default => 'amber',
    };
    $statusLabel = str($obj->registration_status)->ucfirst();
@endphp

<div>
    <x-flash />

    <x-page-header :title="$obj->name">
        <x-slot:actions>
            <x-badge :color="$statusColor">{{ $statusLabel }}</x-badge>
            <a
                href="{{ route('user.index') }}"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-white px-4 text-sm font-semibold text-neutral-800 border border-neutral-400 transition hover:bg-neutral-100 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
            >
                <x-icon name="arrow-left" class="size-4" />
                Back to list
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-6">
        <x-card>
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <h2 class="text-base font-semibold text-black">Registration</h2>
                <div class="flex flex-wrap gap-3">
                    @if ($canApprove)
                        <x-button type="button" variant="success" wire:click="approve" wire:loading.attr="disabled">
                            <x-icon name="check-circle" class="size-4" />
                            Approve
                        </x-button>
                    @endif
                    @if ($canReject)
                        <x-button type="button" variant="danger" wire:click="reject" wire:loading.attr="disabled">
                            <x-icon name="x" class="size-4" />
                            Reject
                        </x-button>
                    @endif
                </div>
            </div>

            <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Name</dt>
                    <dd class="mt-1.5 text-sm text-black">{{ $obj->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Email</dt>
                    <dd class="mt-1.5 break-all text-sm text-black">{{ $obj->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">NIK</dt>
                    <dd class="mt-1.5 text-sm text-black">{{ $obj->nik }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Phone</dt>
                    <dd class="mt-1.5 text-sm text-black">{{ $obj->phone }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Status</dt>
                    <dd class="mt-1.5"><x-badge :color="$statusColor">{{ $statusLabel }}</x-badge></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-neutral-600">Registered</dt>
                    <dd class="mt-1.5 text-sm text-black">{{ _date_format_output($obj->created_at) }}</dd>
                </div>
            </dl>

            <p class="mt-5 text-xs text-neutral-600">Check the KTP data before approving. Only approved tenants can log in.</p>
        </x-card>

        <section>
            <h2 class="mb-3 text-base font-semibold text-black">Vehicles</h2>
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-4 py-3">Type</th>
                    <th scope="col" class="px-4 py-3">Plate number</th>
                    <th scope="col" class="px-4 py-3">Brand</th>
                    <th scope="col" class="px-4 py-3">Model</th>
                    <th scope="col" class="px-4 py-3">Capacity</th>
                </x-slot:head>

                @forelse ($vehicles as $vehicle)
                    <tr wire:key="vehicle-{{ $vehicle->id }}">
                        <td class="px-4 py-3"><x-vehicle-type-badge :type="$vehicle->type" /></td>
                        <td class="whitespace-nowrap px-4 py-3 font-medium">
                            <a href="{{ $vehicle->getRouteShow() }}" class="text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black">{{ $vehicle->plate_number ?: 'No plate' }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $vehicle->brand }}</td>
                        <td class="px-4 py-3">{{ $vehicle->model }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($vehicle->maximum_capacity_kw !== null)
                                {{ _get_formatted_number($vehicle->maximum_capacity_kw) }} kW
                            @else
                                <span class="text-neutral-500">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state title="No vehicles" text="This tenant has not registered a vehicle." />
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </section>

        <section>
            <h2 class="mb-3 text-base font-semibold text-black">Latest rentals</h2>
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-4 py-3">Code</th>
                    <th scope="col" class="px-4 py-3">Garage</th>
                    <th scope="col" class="px-4 py-3">Type</th>
                    <th scope="col" class="px-4 py-3">Start date</th>
                    <th scope="col" class="px-4 py-3">End date</th>
                    <th scope="col" class="px-4 py-3 text-right">Rent</th>
                </x-slot:head>

                @forelse ($rentals as $rental)
                    <tr wire:key="rental-{{ $rental->id }}">
                        <td class="whitespace-nowrap px-4 py-3 font-medium">
                            <a href="{{ $rental->getRouteShow() }}" class="text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black">{{ $rental->code }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $rental->garage?->code }}</td>
                        <td class="px-4 py-3">{{ str($rental->rental_type)->replace('_', ' ')->ucfirst() }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($rental->start_date) }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($rental->end_date) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($rental->rent_amount, 'Rp') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-empty-state title="No rentals yet" text="This tenant has not rented a garage." />
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </section>
    </div>
</div>
