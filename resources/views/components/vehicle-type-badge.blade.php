@props(['type', 'label' => null])

{{-- Every type uses the same outlined badge: the type is told by its text, not by a style. --}}
<x-badge color="indigo" {{ $attributes }}>{{ $label ?? str($type)->replace('_', ' ')->ucfirst() }}</x-badge>
