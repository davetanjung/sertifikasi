@props(['type', 'label' => null])

@php
    $color = match ($type) {
        \App\Utilities\Constants\Const_Type::GARAGE_BICYCLE => 'slate',
        \App\Utilities\Constants\Const_Type::GARAGE_CAR => 'indigo',
        \App\Utilities\Constants\Const_Type::GARAGE_ELECTRIC_CAR => 'black',
        default => 'slate',
    };
@endphp

<x-badge :color="$color" {{ $attributes }}>{{ $label ?? str($type)->replace('_', ' ')->ucfirst() }}</x-badge>
