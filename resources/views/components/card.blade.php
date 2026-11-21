@props(['title' => null])

<section {{ $attributes->class('rounded-md border border-neutral-300 bg-white') }}>
    @if ($title)
        <header class="border-b border-neutral-300 px-6 py-4">
            <h2 class="text-base font-semibold text-black">{{ $title }}</h2>
        </header>
    @endif

    <div class="p-6">
        {{ $slot }}
    </div>
</section>
