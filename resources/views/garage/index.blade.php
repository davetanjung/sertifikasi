<div>
    <x-flash />

    <x-page-header title="Garages" subtitle="This is the page of all garages and their status.">
        <x-slot:actions>
            <a
                href="{{ route('garage.create') }}"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-black px-4 text-sm font-semibold text-white transition hover:bg-neutral-800 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
            >
                <x-icon name="plus" class="size-4" />
                Create garages
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid gap-3 sm:grid-cols-3 sm:items-end">
        <div>
            <label for="keyword" class="mb-1.5 block text-sm font-medium text-neutral-800">Search</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-neutral-500" />
                <input
                    id="keyword"
                    type="search"
                    wire:model.live.debounce.300ms="keyword"
                    placeholder="Garage code"
                    class="h-10 w-full rounded-md border border-neutral-400 bg-white pl-9 pr-3 text-sm text-black placeholder:text-neutral-500 focus:border-black focus:outline-none focus:ring-2 focus:ring-black"
                >
            </div>
        </div>
        <x-form.select label="Type" name="type" :options="$typeOptions" placeholder="All types" wire:model.live.debounce.300ms="type" />
        <x-form.select label="Status" name="availability" :options="$availabilityOptions" placeholder="All statuses" wire:model.live.debounce.300ms="availability" />
    </div>

    <x-table>
        <x-slot:head>
            <th scope="col" class="w-12 px-4 py-3">No</th>
            <x-sortable-th label="Code" field="code" :sortField="$sortField" :sortAsc="$sortAsc" />
            <x-sortable-th label="Type" field="type" :sortField="$sortField" :sortAsc="$sortAsc" />
            <x-sortable-th label="Daily price" field="daily_price" :sortField="$sortField" :sortAsc="$sortAsc" class="text-right" title="A monthly rental costs the same as 25 days" />
            <th scope="col" class="px-4 py-3 text-right">Price per kWh</th>
            <th scope="col" class="px-4 py-3" title="A garage is used when a rental covers today.">Status</th>
            <th scope="col" class="px-4 py-3 text-right">Action</th>
        </x-slot:head>

        @forelse ($data as $garage)
            <tr wire:key="garage-{{ $garage->id }}">
                <td class="px-4 py-3 text-neutral-600">{{ $no_item + $loop->index }}</td>
                <td class="whitespace-nowrap px-4 py-3 font-medium">
                    <a href="{{ $garage->getRouteShow() }}" class="text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black">{{ $garage->code }}</a>
                </td>
                <td class="px-4 py-3"><x-garage-type-badge :type="$garage->type" :label="$typeOptions[$garage->type] ?? null" /></td>
                <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($garage->daily_price, 'Rp') }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    @if ($garage->price_per_kwh !== null)
                        {{ _money($garage->price_per_kwh, 'Rp') }}
                    @else
                        <span class="text-neutral-500">&mdash;</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if ($garage->status === \App\Utilities\Constants\Const_Status::USED)
                        <x-badge color="black">Used</x-badge>
                    @elseif ($garage->status === \App\Utilities\Constants\Const_Status::AVAILABLE)
                        <x-badge color="green">Available</x-badge>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <a href="{{ $garage->getRouteShow() }}" title="View" aria-label="View {{ $garage->code }}" class="inline-flex size-8 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-100 hover:text-black focus:outline-none focus-visible:ring-2 focus-visible:ring-black">
                        <x-icon name="eye" class="size-5" />
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <x-empty-state
                        title="No garages found"
                        :text="$keyword || $type || $availability ? 'Try changing your search or filter.' : 'No garage has been created yet.'"
                    >
                        <a href="{{ route('garage.create') }}" class="text-sm font-semibold text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:underline">Create garages</a>
                    </x-empty-state>
                </td>
            </tr>
        @endforelse
    </x-table>

    <p class="mt-3 text-xs text-neutral-600">A monthly rental costs the same as 25 days.</p>
    <p class="mt-1 text-xs text-neutral-600">Status is worked out from the rental dates: a garage is used when a rental covers today.</p>

    <div class="mt-4">
        {{ $data->links() }}
    </div>
</div>
