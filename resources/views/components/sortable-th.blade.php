@props(['label', 'field', 'sortField' => null, 'sortAsc' => true])

@php
    $isActive = $sortField === $field;
@endphp

<th scope="col" aria-sort="{{ $isActive ? ($sortAsc ? 'ascending' : 'descending') : 'none' }}" {{ $attributes->class('px-4 py-3') }}>
    <button
        type="button"
        wire:click="sort('{{ $field }}')"
        class="inline-flex items-center gap-1 rounded uppercase tracking-wider hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black"
    >
        {{ $label }}
        @if ($isActive)
            <x-icon :name="$sortAsc ? 'arrow-up' : 'arrow-down'" class="size-3.5 text-black" />
        @else
            <x-icon name="chevron-up-down" class="size-3.5 text-neutral-500" />
        @endif
    </button>
</th>
