@props(['type', 'label' => null])

@php
    $color = match ($type) {
        \App\Utilities\Constants\Const_Type::VEHICLE_MOTORCYCLE => 'amber',
        \App\Utilities\Constants\Const_Type::VEHICLE_CAR => 'indigo',
        \App\Utilities\Constants\Const_Type::VEHICLE_ELECTRIC_CAR => 'black',
        default => 'slate',
    };
@endphp

<x-badge :color="$color" {{ $attributes }}>{{ $label ?? str($type)->replace('_', ' ')->ucfirst() }}</x-badge>
