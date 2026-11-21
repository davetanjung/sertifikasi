@props(['head' => null])

<div {{ $attributes->class('overflow-x-auto rounded-md border border-neutral-300 bg-white') }}>
    <table class="min-w-full divide-y divide-neutral-300 text-left text-sm">
        @if ($head)
            <thead class="bg-white text-xs font-semibold uppercase tracking-wider text-black">
                <tr>
                    {{ $head }}
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-neutral-200 text-neutral-800 [&>tr:hover]:bg-neutral-100">
            {{ $slot }}
        </tbody>
    </table>
</div>
