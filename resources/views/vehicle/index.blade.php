<div>
    <x-flash />

    <x-page-header title="Vehicles" subtitle="Manage the vehicles that can be parked in the garages.">
        <x-slot:actions>
            @unless (auth()->user()->isAdmin())
                <a
                    href="{{ route('vehicle.create') }}"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-black px-4 text-sm font-semibold text-white transition hover:bg-neutral-800 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
                >
                    <x-icon name="plus" class="size-4" />
                    Register vehicle
                </a>
            @endunless
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="keyword" class="mb-1.5 block text-sm font-medium text-neutral-800">Search</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-neutral-500" />
                <input
                    id="keyword"
                    type="search"
                    wire:model.live.debounce.300ms="keyword"
                    placeholder="Plate number, brand or model"
                    class="h-10 w-full rounded-md border border-neutral-400 bg-white pl-9 pr-3 text-sm text-black placeholder:text-neutral-500 focus:border-black focus:outline-none focus:ring-2 focus:ring-black"
                >
            </div>
        </div>
        <div class="sm:w-56">
            <x-form.select label="Type" name="type" :options="$typeOptions" placeholder="All types" wire:model.live.debounce.300ms="type" />
        </div>
    </div>

    <x-table>
        <x-slot:head>
            <th scope="col" class="w-12 px-4 py-3">No</th>
            <x-sortable-th label="Plate number" field="plate_number" :sortField="$sortField" :sortAsc="$sortAsc" />
            <th scope="col" class="px-4 py-3">Type</th>
            <x-sortable-th label="Brand" field="brand" :sortField="$sortField" :sortAsc="$sortAsc" />
            <x-sortable-th label="Model" field="model" :sortField="$sortField" :sortAsc="$sortAsc" />
            @if (auth()->user()->isAdmin())
                <th scope="col" class="px-4 py-3">Owner</th>
            @endif
            <x-sortable-th label="Registered" field="created_at" :sortField="$sortField" :sortAsc="$sortAsc" />
            <th scope="col" class="px-4 py-3 text-right">Action</th>
        </x-slot:head>

        @forelse ($data as $vehicle)
            <tr wire:key="vehicle-{{ $vehicle->id }}">
                <td class="px-4 py-3 text-neutral-600">{{ $no_item + $loop->index }}</td>
                <td class="whitespace-nowrap px-4 py-3 font-medium">
                    <a href="{{ $vehicle->getRouteShow() }}" class="text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black">
                        {{ $vehicle->plate_number ?: 'No plate' }}
                    </a>
                </td>
                <td class="px-4 py-3"><x-vehicle-type-badge :type="$vehicle->type" :label="$typeOptions[$vehicle->type] ?? null" /></td>
                <td class="px-4 py-3">{{ $vehicle->brand }}</td>
                <td class="px-4 py-3">{{ $vehicle->model }}</td>
                @if (auth()->user()->isAdmin())
                    <td class="px-4 py-3">{{ $vehicle->user->name }}</td>
                @endif
                <td class="whitespace-nowrap px-4 py-3 text-neutral-600">{{ _date_format_output($vehicle->created_at) }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <a href="{{ $vehicle->getRouteShow() }}" title="View" aria-label="View {{ $vehicle->plate_number ?: 'vehicle' }}" class="inline-flex size-8 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-100 hover:text-black focus:outline-none focus-visible:ring-2 focus-visible:ring-black">
                        <x-icon name="eye" class="size-5" />
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}">
                    <x-empty-state
                        title="No vehicles found"
                        :text="$keyword || $type ? 'Try changing your search or filter.' : 'No vehicle has been registered yet.'"
                    >
                        @unless (auth()->user()->isAdmin())
                            <a href="{{ route('vehicle.create') }}" class="text-sm font-semibold text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:underline">Register a vehicle</a>
                        @endunless
                    </x-empty-state>
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $data->links() }}
    </div>
</div>
