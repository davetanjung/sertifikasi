@props(['title', 'text' => null, 'icon' => 'inbox'])

<div {{ $attributes->class('flex flex-col items-center px-6 py-12 text-center') }}>
    <span class="text-neutral-500">
        <x-icon :name="$icon" class="size-8" />
    </span>
    <h3 class="mt-4 text-sm font-semibold text-black">{{ $title }}</h3>
    @if ($text)
        <p class="mt-1 max-w-sm text-sm text-neutral-600">{{ $text }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5">
            {{ $slot }}
        </div>
    @endif
</div>
