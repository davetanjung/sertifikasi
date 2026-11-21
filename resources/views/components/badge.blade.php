@props(['color' => 'slate'])

{{--
    Status badge. The `color` prop maps to:
    green = solid dark green, red = solid dark red, black = solid black,
    indigo/blue = outlined, amber = dashed outline, slate = muted gray.
--}}
@php
    $colors = [
        'green' => 'border-green-800 bg-green-800 text-white',
        'red' => 'border-red-800 bg-red-800 text-white',
        'black' => 'border-black bg-black text-white',
        'indigo' => 'border-black bg-white text-black',
        'blue' => 'border-black bg-white text-black',
        'amber' => 'border-dashed border-black bg-white text-black',
        'slate' => 'border-neutral-200 bg-neutral-100 text-neutral-700',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded border px-2 py-0.5 text-xs font-medium', $colors[$color] ?? $colors['slate']]) }}>
    {{ $slot }}
</span>
