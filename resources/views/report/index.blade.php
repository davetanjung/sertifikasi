@php
    $isOverview = in_array($report, ['garage_type', 'tenant'], true);
    $firstColumn = match ($report) {
        'monthly' => 'Month',
        'daily' => 'Day',
        'garage_type' => 'Garage type',
        default => 'Tenant',
    };
@endphp

<div>
    <x-flash />

    <x-page-header title="Rental reports" subtitle="Number of rentals and income, including charging and extensions." />

    <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="Report type">
        @foreach ($reportOptions as $value => $label)
            <button
                type="button"
                wire:click="$set('report', '{{ $value }}')"
                wire:key="report-tab-{{ $value }}"
                @if ($report === $value) aria-pressed="true" @else aria-pressed="false" @endif
                @class([
                    'flex-1 whitespace-nowrap rounded-md px-4 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-black',
                    'border border-black bg-black text-white' => $report === $value,
                    'border border-neutral-400 bg-white text-black hover:border-black hover:bg-neutral-100' => $report !== $value,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <x-card class="mb-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-form.select
                label="Year"
                name="year"
                :options="$yearOptions"
                :placeholder="$isOverview ? 'All years' : null"
                wire:model.live="year"
            />

            @if (in_array($report, ['daily', 'garage_type', 'tenant'], true))
                <x-form.select
                    label="Month"
                    name="month"
                    :options="$monthOptions"
                    :placeholder="$report === 'daily' ? null : 'All months'"
                    wire:model.live="month"
                />
            @endif

            @if (in_array($report, ['monthly', 'daily', 'tenant'], true))
                <x-form.select
                    label="Garage type"
                    name="garage_type"
                    :options="$garageTypeOptions"
                    placeholder="All types"
                    wire:model.live="garage_type"
                />
            @endif

            @if (in_array($report, ['monthly', 'daily', 'garage_type'], true))
                <x-form.select
                    label="Tenant"
                    name="user_id"
                    :options="$tenantOptions"
                    placeholder="All tenants"
                    wire:model.live="user_id"
                />
            @endif
        </div>
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
                <tr wire:key="report-row-{{ $report }}-{{ $loop->index }}" @class(['text-neutral-500' => $row['total_income'] == 0 && $row['rentals'] == 0])>
                    <td class="whitespace-nowrap px-4 py-3 font-medium">
                        @if ($report === 'monthly')
                            {{ $monthOptions[$row['month']] ?? $row['month'] }}
                        @elseif ($report === 'daily')
                            {{ _date_format_output($row['date']) }}
                        @elseif ($report === 'garage_type')
                            <x-garage-type-badge :type="$row['garage_type']" />
                        @else
                            {{ $row['name'] }}
                            <span class="block text-xs font-normal text-neutral-600">{{ $row['email'] }}</span>
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
