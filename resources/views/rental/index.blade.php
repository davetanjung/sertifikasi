@php
    use App\Utilities\Constants\Const_Type;

    $statusColors = ['upcoming' => 'blue', 'running' => 'green', 'ended' => 'slate'];
    $isAdmin = auth()->user()->isAdmin();
@endphp

<div>
    <x-flash />

    <x-page-header title="Rentals" subtitle="Garage rentals with their period and status.">
        <x-slot:actions>
            @unless ($isAdmin)
                <a
                    href="{{ route('rental.create') }}"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-black px-4 text-sm font-semibold text-white transition hover:bg-neutral-800 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
                >
                    <x-icon name="plus" class="size-4" />
                    New rental
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
                    placeholder="Code, garage or plate"
                    class="h-10 w-full rounded-md border border-neutral-400 bg-white pl-9 pr-3 text-sm text-black placeholder:text-neutral-500 focus:border-black focus:outline-none focus:ring-2 focus:ring-black"
                >
            </div>
        </div>
        <div class="sm:w-44">
            <x-form.select label="Status" name="period" :options="$periodOptions" placeholder="All statuses" wire:model.live="period" />
        </div>
        <div class="sm:w-44">
            <x-form.select label="Rental type" name="rental_type" :options="$rentalTypeOptions" placeholder="All types" wire:model.live="rental_type" />
        </div>
    </div>

    <x-table>
        <x-slot:head>
            <th scope="col" class="w-12 px-4 py-3">No</th>
            <x-sortable-th label="Code" field="code" :sortField="$sortField" :sortAsc="$sortAsc" />
            <th scope="col" class="px-4 py-3">Garage</th>
            <th scope="col" class="px-4 py-3">Vehicle</th>
            <th scope="col" class="px-4 py-3">Type</th>
            <x-sortable-th label="Start date" field="start_date" :sortField="$sortField" :sortAsc="$sortAsc" />
            <x-sortable-th label="End date" field="latest_end_date" :sortField="$sortField" :sortAsc="$sortAsc" />
            <th scope="col" class="px-4 py-3">Status</th>
            <x-sortable-th label="Rent" field="rent_amount" :sortField="$sortField" :sortAsc="$sortAsc" class="text-right" />
            @if ($isAdmin)
                <th scope="col" class="px-4 py-3">Tenant</th>
            @endif
            <th scope="col" class="px-4 py-3 text-right">Action</th>
        </x-slot:head>

        @forelse ($data as $rental)
            <tr wire:key="rental-{{ $rental->id }}">
                <td class="px-4 py-3 text-neutral-600">{{ $no_item + $loop->index }}</td>
                <td class="whitespace-nowrap px-4 py-3 font-medium">
                    <a href="{{ $rental->getRouteShow() }}" class="text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black">{{ $rental->code }}</a>
                </td>
                <td class="whitespace-nowrap px-4 py-3">{{ $rental->garage?->code }}</td>
                <td class="whitespace-nowrap px-4 py-3">{{ $rental->vehicle?->plate_number ?: trim(($rental->vehicle?->type ? str($rental->vehicle->type)->replace('_', ' ')->ucfirst() : '').' '.$rental->vehicle?->brand) }}</td>
                <td class="px-4 py-3"><x-badge :color="$rental->rental_type === Const_Type::RENTAL_MONTHLY ? 'indigo' : 'amber'">{{ $rentalTypeOptions[$rental->rental_type] ?? $rental->rental_type }}</x-badge></td>
                <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($rental->start_date) }}</td>
                <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($rental->latest_end_date) }}</td>
                <td class="px-4 py-3"><x-badge :color="$statusColors[$rental->period_status] ?? 'slate'">{{ $periodOptions[$rental->period_status] ?? $rental->period_status }}</x-badge></td>
                <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($rental->rent_amount, 'Rp') }}</td>
                @if ($isAdmin)
                    <td class="px-4 py-3">{{ $rental->user?->name }}</td>
                @endif
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <a href="{{ $rental->getRouteShow() }}" title="View" aria-label="View {{ $rental->code }}" class="inline-flex size-8 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-100 hover:text-black focus:outline-none focus-visible:ring-2 focus-visible:ring-black">
                        <x-icon name="eye" class="size-5" />
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $isAdmin ? 11 : 10 }}">
                    <x-empty-state
                        title="No rentals found"
                        :text="$keyword || $period || $rental_type ? 'Try changing your search or filters.' : 'No rental has been made yet.'"
                    >
                        @unless ($isAdmin)
                            <a href="{{ route('rental.create') }}" class="text-sm font-semibold text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:underline">Create a rental</a>
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
