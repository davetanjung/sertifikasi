<div>
    <x-flash />

    <x-page-header title="Extend a rental" subtitle="Rentals running today. Pick one to move its end date later." />

    <div class="mb-4">
        <label for="keyword" class="mb-1.5 block text-sm font-medium text-neutral-800">Search</label>
        <div class="relative sm:max-w-md">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-neutral-500" />
            <input
                id="keyword"
                type="search"
                wire:model.live.debounce.300ms="keyword"
                placeholder="Rental, garage, tenant or plate"
                class="h-10 w-full rounded-md border border-neutral-400 bg-white pl-9 pr-3 text-sm text-black placeholder:text-neutral-500 focus:border-black focus:outline-none focus:ring-2 focus:ring-black"
            >
        </div>
    </div>

    <x-table>
        <x-slot:head>
            <th scope="col" class="px-4 py-3">Rental</th>
            <th scope="col" class="px-4 py-3">Garage</th>
            <th scope="col" class="px-4 py-3">Tenant</th>
            <th scope="col" class="px-4 py-3">Vehicle</th>
            <th scope="col" class="px-4 py-3">Start date</th>
            <th scope="col" class="px-4 py-3">Current end date</th>
            <th scope="col" class="px-4 py-3"><span class="sr-only">Action</span></th>
        </x-slot:head>

        @forelse ($rentals as $rental)
            <tr wire:key="extension-rental-{{ $rental->id }}">
                <td class="whitespace-nowrap px-4 py-3 font-medium">
                    <a href="{{ $rental->getRouteShow() }}" class="text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black">{{ $rental->code }}</a>
                </td>
                <td class="whitespace-nowrap px-4 py-3">{{ $rental->garage?->code }}</td>
                <td class="px-4 py-3">{{ $rental->user?->name }}</td>
                <td class="whitespace-nowrap px-4 py-3">{{ $rental->vehicle?->plate_number ?: trim($rental->vehicle?->brand.' '.$rental->vehicle?->model) }}</td>
                <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($rental->start_date) }}</td>
                <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($rental->latest_end_date) }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <a
                        href="{{ route('extension.create', ['rental' => $rental->id]) }}"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-white px-3 text-sm font-semibold text-black border border-black transition hover:bg-neutral-100 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
                    >
                        <x-icon name="calendar" class="size-4" />
                        Extend
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <x-empty-state
                        title="No rentals running today"
                        :text="$keyword ? 'Try changing your search.' : 'There is no rental to extend right now.'"
                        icon="calendar"
                    />
                </td>
            </tr>
        @endforelse
    </x-table>
</div>
