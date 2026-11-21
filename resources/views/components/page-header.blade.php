@props(['title', 'subtitle' => null, 'actions' => null])

<div class="mb-8 flex flex-col gap-4 border-b border-black pb-6 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-black">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-neutral-600">{{ $subtitle }}</p>
        @endif
    </div>

    @if ($actions && $actions->isNotEmpty())
        <div class="flex items-center gap-3">
            {{ $actions }}
        </div>
    @endif
</div>
