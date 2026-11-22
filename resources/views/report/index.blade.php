@php
    $firstColumn = $isDaily ? 'Day' : 'Month';
    $minimumDate = '2026-01-01';
@endphp

<div>
    <x-flash />

    <x-page-header title="Rental reports" subtitle="Pick a start and end date: a range inside one month shows each day, a longer range shows each month. Narrow it by garage type or tenant." />

    <x-card class="mb-6">
        <form wire:submit="$refresh" class="flex flex-wrap items-end gap-4">
            <div class="w-full sm:w-52">
                <x-form.input
                    label="Start date"
                    name="start_date"
                    type="date"
                    :min="$minimumDate"
                    wire:model="start_date"
                />
            </div>

            <div class="w-full sm:w-52">
                <x-form.input
                    label="End date"
                    name="end_date"
                    type="date"
                    :min="$minimumDate"
                    wire:model="end_date"
                />
            </div>

            <div class="w-full sm:w-52">
                <x-form.select
                    label="Garage type"
                    name="garage_type"
                    :options="$garageTypeOptions"
                    placeholder="All types"
                    wire:model="garage_type"
                />
            </div>

            <div class="w-full sm:w-52">
                <x-form.select
                    label="Tenant"
                    name="user_id"
                    :options="$tenantOptions"
                    placeholder="All tenants"
                    wire:model="user_id"
                />
            </div>

            <div class="flex items-center gap-2">
                <x-button type="submit" wire:loading.attr="disabled">
                    <x-icon name="funnel" class="size-4" />
                    Filter
                </x-button>
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="rounded-md px-4 py-2 text-sm font-semibold text-neutral-700 hover:text-black focus:outline-none focus-visible:ring-2 focus-visible:ring-black"
                >
                    Reset
                </button>
            </div>
        </form>
    </x-card>

    @if ($reportError)
        <div class="rounded-md border-2 border-red-800 bg-white p-4 text-sm font-medium text-red-800" role="alert">
            <div class="flex items-start gap-3">
                <x-icon name="exclamation-circle" class="mt-0.5 size-5 shrink-0 text-red-800" />
                <p>{{ $reportError }}</p>
            </div>
        </div>
    @else
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-md border border-black bg-white p-5">
                <p class="text-sm text-neutral-700">Rentals</p>
                <p class="mt-1 text-2xl font-bold text-black">{{ $totals['rentals'] }}</p>
            </div>
            <div class="rounded-md border border-black bg-white p-5">
                <p class="text-sm text-neutral-700">Rent income</p>
                <p class="mt-1 text-2xl font-bold text-black">{{ _money($totals['rent_income'], 'Rp') }}</p>
            </div>
            <div class="rounded-md border border-black bg-white p-5">
                <p class="text-sm text-neutral-700">Charging income</p>
                <p class="mt-1 text-2xl font-bold text-black">{{ _money($totals['charging_income'], 'Rp') }}</p>
            </div>
            <div class="rounded-md border border-black bg-white p-5">
                <p class="text-sm text-black">Total income</p>
                <p class="mt-1 text-2xl font-bold text-black">{{ _money($totals['total_income'], 'Rp') }}</p>
            </div>
        </div>

        <x-table>
            <x-slot:head>
                <th scope="col" class="px-4 py-3">{{ $firstColumn }}</th>
                <th scope="col" class="px-4 py-3 text-right">Rentals</th>
                <th scope="col" class="px-4 py-3 text-right">Rent income</th>
                <th scope="col" class="px-4 py-3 text-right">Charging income</th>
                <th scope="col" class="px-4 py-3 text-right">Total income</th>
            </x-slot:head>

            @forelse ($rows as $row)
                <tr wire:key="report-row-{{ $loop->index }}" @class(['text-neutral-500' => $row['total_income'] == 0 && $row['rentals'] == 0])>
                    <td class="whitespace-nowrap px-4 py-3 font-medium">
                        @if ($isDaily)
                            {{ _date_format_output($row['date']) }}
                        @else
                            {{ \Carbon\Carbon::parse($row['month'].'-01')->format('F Y') }}
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ $row['rentals'] }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($row['rent_income'], 'Rp') }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($row['charging_income'], 'Rp') }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($row['total_income'], 'Rp') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <x-empty-state
                            title="No activity for these filters"
                            text="Try choosing another period or removing a filter."
                            icon="chart"
                        />
                    </td>
                </tr>
            @endforelse

            @if (count($rows) > 0)
                <tr class="bg-neutral-100 font-bold text-black">
                    <td class="whitespace-nowrap px-4 py-3">Total</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ $totals['rentals'] }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($totals['rent_income'], 'Rp') }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($totals['charging_income'], 'Rp') }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($totals['total_income'], 'Rp') }}</td>
                </tr>
            @endif
        </x-table>
    @endif
</div>
