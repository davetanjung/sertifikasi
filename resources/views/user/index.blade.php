@php
    use App\Utilities\Constants\Const_Status;
@endphp

<div>
    <x-flash />

    <x-page-header title="Tenants" subtitle="Review registrations and see every tenant." />

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="keyword" class="mb-1.5 block text-sm font-medium text-neutral-800">Search</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-neutral-500" />
                <input
                    id="keyword"
                    type="search"
                    wire:model.live.debounce.300ms="keyword"
                    placeholder="Name, email, NIK or phone"
                    class="h-10 w-full rounded-md border border-neutral-400 bg-white pl-9 pr-3 text-sm text-black placeholder:text-neutral-500 focus:border-black focus:outline-none focus:ring-2 focus:ring-black"
                >
            </div>
        </div>
        <div class="sm:w-56">
            <x-form.select label="Status" name="status" :options="$statusOptions" placeholder="All statuses" wire:model.live.debounce.300ms="status" />
        </div>
    </div>

    <x-table>
        <x-slot:head>
            <th scope="col" class="w-12 px-4 py-3">No</th>
            <x-sortable-th label="Name" field="name" :sortField="$sortField" :sortAsc="$sortAsc" />
            <x-sortable-th label="Email" field="email" :sortField="$sortField" :sortAsc="$sortAsc" />
            <th scope="col" class="px-4 py-3">NIK</th>
            <th scope="col" class="px-4 py-3">Phone</th>
            <th scope="col" class="px-4 py-3">Status</th>
            <x-sortable-th label="Registered" field="created_at" :sortField="$sortField" :sortAsc="$sortAsc" />
            <th scope="col" class="px-4 py-3 text-right">Action</th>
        </x-slot:head>

        @forelse ($data as $user)
            <tr wire:key="user-{{ $user->id }}">
                <td class="px-4 py-3 text-neutral-600">{{ $no_item + $loop->index }}</td>
                <td class="whitespace-nowrap px-4 py-3 font-medium">
                    <a href="{{ $user->getRouteShow() }}" class="text-black underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black">{{ $user->name }}</a>
                </td>
                <td class="px-4 py-3">{{ $user->email }}</td>
                <td class="whitespace-nowrap px-4 py-3">{{ $user->nik }}</td>
                <td class="whitespace-nowrap px-4 py-3">{{ $user->phone }}</td>
                <td class="px-4 py-3">
                    <x-badge :color="match ($user->registration_status) {
                        Const_Status::APPROVED => 'green',
                        Const_Status::REJECTED => 'red',
                        default => 'amber',
                    }">{{ $statusOptions[$user->registration_status] ?? str($user->registration_status)->ucfirst() }}</x-badge>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-neutral-600">{{ _date_format_output($user->created_at) }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <a href="{{ $user->getRouteShow() }}" title="View" aria-label="View {{ $user->name }}" class="inline-flex size-8 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-100 hover:text-black focus:outline-none focus-visible:ring-2 focus-visible:ring-black">
                        <x-icon name="eye" class="size-5" />
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8">
                    <x-empty-state
                        title="No tenants found"
                        :text="$keyword || $status ? 'Try changing your search or filter.' : 'No tenant has registered yet.'"
                    />
                </td>
            </tr>
        @endforelse
    </x-table>

    <div class="mt-4">
        {{ $data->links() }}
    </div>
</div>
