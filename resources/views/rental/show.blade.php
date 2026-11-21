@php
    use App\Utilities\Constants\Const_Type;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Storage;

    $proofLinkClass = 'inline-flex items-center gap-1.5 text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black';
    $labelClass = 'text-xs font-medium uppercase tracking-wide text-neutral-600';
@endphp

<div>
    <x-flash />

    <x-page-header :title="'Rental '.$obj->code" :subtitle="'Garage '.$obj->garage?->code.' for '.$obj->vehicle?->brand.' '.$obj->vehicle?->model">
        <x-slot:actions>
            @if ($isActive)
                <x-badge color="green">Running</x-badge>
            @else
                <x-badge color="slate">Not active today</x-badge>
            @endif

            @if ($canCharge && Route::has('charging-transaction.create'))
                <a
                    href="{{ route('charging-transaction.create', ['rental' => $obj->id]) }}"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-black px-4 text-sm font-semibold text-white transition hover:bg-neutral-800 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
                >
                    <x-icon name="bolt" class="size-4" />
                    Add charging
                </a>
            @endif

            <a
                href="{{ route('rental.index') }}"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-white px-4 text-sm font-semibold text-neutral-800 border border-neutral-400 transition hover:bg-neutral-100 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
            >
                <x-icon name="arrow-left" class="size-4" />
                Back to list
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-6">
        <div class="grid items-start gap-6 lg:grid-cols-3">
            <x-card title="Details" class="lg:col-span-2">
                <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="{{ $labelClass }}">Garage</dt>
                        <dd class="mt-1.5 text-sm text-black">{{ $obj->garage?->code }} ({{ str($obj->garage?->type)->replace('_', ' ')->ucfirst() }})</dd>
                    </div>
                    <div>
                        <dt class="{{ $labelClass }}">Vehicle</dt>
                        <dd class="mt-1.5 text-sm text-black">{{ $obj->vehicle?->plate_number ?: 'No plate' }} - {{ $obj->vehicle?->brand }} {{ $obj->vehicle?->model }}</dd>
                    </div>
                    @if (auth()->user()->isAdmin())
                        <div>
                            <dt class="{{ $labelClass }}">Tenant</dt>
                            <dd class="mt-1.5 text-sm text-black">{{ $obj->user?->name }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="{{ $labelClass }}">Rental type</dt>
                        <dd class="mt-1.5"><x-badge :color="$obj->rental_type === Const_Type::RENTAL_MONTHLY ? 'indigo' : 'amber'">{{ str($obj->rental_type)->ucfirst() }}</x-badge></dd>
                    </div>
                    <div>
                        <dt class="{{ $labelClass }}">Start date</dt>
                        <dd class="mt-1.5 text-sm text-black">{{ _date_format_output($obj->start_date) }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $labelClass }}">End date</dt>
                        <dd class="mt-1.5 text-sm text-black">{{ _date_format_output($latestEndDate) }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $labelClass }}">Days</dt>
                        <dd class="mt-1.5 text-sm text-black">{{ $obj->days }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $labelClass }}">Daily price</dt>
                        <dd class="mt-1.5 text-sm text-black">{{ _money($obj->daily_price, 'Rp') }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $labelClass }}">Rent amount</dt>
                        <dd class="mt-1.5 text-sm text-black">{{ _money($obj->rent_amount, 'Rp') }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $labelClass }}">Payment proof</dt>
                        <dd class="mt-1.5 text-sm">
                            @if ($obj->payment_proof_path && Storage::disk('public')->exists($obj->payment_proof_path))
                                <a href="{{ Storage::disk('public')->url($obj->payment_proof_path) }}" target="_blank" rel="noopener" class="{{ $proofLinkClass }}">
                                    <x-icon name="document" class="size-4" />
                                    View payment proof
                                </a>
                            @else
                                <span class="text-neutral-600">Not available</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Totals">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-neutral-700">Rent</dt>
                        <dd class="font-medium text-black">{{ _money($totalRent, 'Rp') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-neutral-700">Charging</dt>
                        <dd class="font-medium text-black">{{ _money($totalCharging, 'Rp') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-neutral-300 pt-3">
                        <dt class="font-semibold text-black">Grand total</dt>
                        <dd class="text-lg font-bold text-black">{{ _money($grandTotal, 'Rp') }}</dd>
                    </div>
                </dl>
            </x-card>
        </div>

        <section>
            <h2 class="mb-3 text-base font-semibold text-black">Extensions</h2>
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-4 py-3">Start date</th>
                    <th scope="col" class="px-4 py-3">End date</th>
                    <th scope="col" class="px-4 py-3">Added days</th>
                    <th scope="col" class="px-4 py-3 text-right">Amount</th>
                    <th scope="col" class="px-4 py-3">Proof</th>
                </x-slot:head>

                @forelse ($extensions as $extension)
                    <tr wire:key="extension-{{ $extension->id }}">
                        <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($extension->start_date) }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($extension->end_date) }}</td>
                        <td class="px-4 py-3">{{ $extension->days }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($extension->rent_amount, 'Rp') }}</td>
                        <td class="px-4 py-3">
                            @if ($extension->payment_proof_path && Storage::disk('public')->exists($extension->payment_proof_path))
                                <a href="{{ Storage::disk('public')->url($extension->payment_proof_path) }}" target="_blank" rel="noopener" class="{{ $proofLinkClass }}">View proof</a>
                            @else
                                <span class="text-neutral-500">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state title="No extensions yet" text="Extensions of this rental will be listed here." />
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </section>

        <section>
            <h2 class="mb-3 text-base font-semibold text-black">Charging history</h2>
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-4 py-3">Date</th>
                    <th scope="col" class="px-4 py-3">Current kW</th>
                    <th scope="col" class="px-4 py-3">Charged kWh</th>
                    <th scope="col" class="px-4 py-3 text-right">Price per kWh</th>
                    <th scope="col" class="px-4 py-3 text-right">Amount</th>
                    <th scope="col" class="px-4 py-3">Proof</th>
                </x-slot:head>

                @forelse ($chargings as $charging)
                    <tr wire:key="charging-{{ $charging->id }}">
                        <td class="whitespace-nowrap px-4 py-3">{{ _date_format_output($charging->created_at) }}</td>
                        <td class="px-4 py-3">{{ _get_formatted_number($charging->current_capacity_kw) }}</td>
                        <td class="px-4 py-3">{{ _get_formatted_number($charging->charged_kwh) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($charging->price_per_kwh, 'Rp') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ _money($charging->amount, 'Rp') }}</td>
                        <td class="px-4 py-3">
                            @if ($charging->payment_proof_path && Storage::disk('public')->exists($charging->payment_proof_path))
                                <a href="{{ Storage::disk('public')->url($charging->payment_proof_path) }}" target="_blank" rel="noopener" class="{{ $proofLinkClass }}">View proof</a>
                            @else
                                <span class="text-neutral-500">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-empty-state title="No charging yet" text="Charging sessions for this rental will be listed here." />
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </section>
    </div>
</div>
