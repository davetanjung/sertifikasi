@php
    use Illuminate\Support\Facades\Storage;

    $isAdmin = auth()->user()->isAdmin();
@endphp

<div>
    <x-flash />

    <x-page-header title="Charging" subtitle="Electric car charging history with the kWh and the amount paid." />

    <div class="mb-4">
        <label for="keyword" class="mb-1.5 block text-sm font-medium text-neutral-800">Search</label>
        <div class="relative sm:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-neutral-500" />
            <input
                id="keyword"
                type="search"
                wire:model.live.debounce.300ms="keyword"
                placeholder="Rental or garage code"
                class="h-10 w-full rounded-md border border-neutral-400 bg-white pl-9 pr-3 text-sm text-black placeholder:text-neutral-500 focus:border-black focus:outline-none focus:ring-2 focus:ring-black"
            >
        </div>
    </div>

    <x-table>
        <x-slot:head>
            <th scope="col" class="w-12 px-4 py-3">No</th>
            <x-sortable-th label="Date" field="created_at" :sortField="$sortField" :sortAsc="$sortAsc" />
            <th scope="col" class="px-4 py-3">Rental</th>
            <th scope="col" class="px-4 py-3">Garage</th>
            <th scope="col" class="px-4 py-3">Vehicle</th>
            <x-sortable-th label="Battery before" field="current_capacity_kw" :sortField="$sortField" :sortAsc="$sortAsc" class="text-right" />
            <x-sortable-th label="Charged" field="charged_kwh" :sortField="$sortField" :sortAsc="$sortAsc" class="text-right" />
            <x-sortable-th label="Price per kWh" field="price_per_kwh" :sortField="$sortField" :sortAsc="$sortAsc" class="text-right" />
            <x-sortable-th label="Amount" field="amount" :sortField="$sortField" :sortAsc="$sortAsc" class="text-right" />
            <th scope="col" class="px-4 py-3">Proof</th>
            @if ($isAdmin)
                <th scope="col" class="px-4 py-3">Tenant</th>
            @endif
        </x-slot:head>

        @forelse ($data as $transaction)
            <tr wire:key="charging-{{ $transaction->id }}">
                <td class="px-4 py-3 text-neutral-600">{{ $no_item + $loop->index }}</td>
                <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($transaction->created_at) }}</td>
                <td class="whitespace-nowrap px-4 py-3 font-medium">
                    <a href="{{ $transaction->rental->getRouteShow() }}" class="text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black">{{ $transaction->rental->code }}</a>
                </td>
                <td class="whitespace-nowrap px-4 py-3">{{ $transaction->rental->garage?->code }}</td>
                <td class="whitespace-nowrap px-4 py-3">{{ $transaction->rental->vehicle?->plate_number ?: trim($transaction->rental->vehicle?->brand.' '.$transaction->rental->vehicle?->model) }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-right">{{ $transaction->current_capacity_kw }} kW</td>
                <td class="whitespace-nowrap px-4 py-3 text-right">{{ $transaction->charged_kwh }} kWh</td>
                <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($transaction->price_per_kwh, 'Rp') }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-right font-medium">{{ _money($transaction->amount, 'Rp') }}</td>
                <td class="whitespace-nowrap px-4 py-3">
                    @if ($transaction->payment_proof_path && Storage::disk('public')->exists($transaction->payment_proof_path))
                        <a href="{{ Storage::disk('public')->url($transaction->payment_proof_path) }}" target="_blank" rel="noopener" class="font-medium text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black">View</a>
                    @endif
                </td>
                @if ($isAdmin)
                    <td class="px-4 py-3">{{ $transaction->rental->user?->name }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ $isAdmin ? 11 : 10 }}">
                    <x-empty-state
                        title="No charging found"
                        :text="$keyword ? 'Try changing your search.' : 'Add charging from the page of an active electric rental.'"
                    />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $data->links() }}
    </div>
</div>
