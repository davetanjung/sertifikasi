@props(['variant' => 'primary', 'type' => 'submit'])

@php
    $variants = [
        'primary' => 'border border-black bg-black text-white hover:bg-neutral-800 hover:border-neutral-800',
        'secondary' => 'border border-black bg-white text-black hover:bg-neutral-100',
        'success' => 'border border-green-800 bg-green-800 text-white hover:border-green-900 hover:bg-green-900',
        'danger' => 'border border-red-800 bg-red-800 text-white hover:border-red-900 hover:bg-red-900',
    ];
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->class([
        'inline-flex h-10 items-center justify-center gap-2 rounded-md px-4 text-sm font-semibold transition focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black disabled:cursor-not-allowed disabled:border-neutral-200 disabled:bg-neutral-100 disabled:text-neutral-500 disabled:no-underline',
        $variants[$variant] ?? $variants['primary'],
    ]) }}
>
    {{ $slot }}
</button>
