@php
    /**
     * Menu items. `href` is where the link goes, `active` is the request pattern that highlights it.
     *
     * @var array<int, array{label: string, href: string, active: string}> $items
     */
    $items = [
        ['label' => 'Dashboard', 'href' => '#', 'active' => 'dashboard'],
        ['label' => 'A', 'href' => '#', 'active' => 'a*'],
        ['label' => 'B', 'href' => '#', 'active' => 'b*'],
        ['label' => 'C', 'href' => '#', 'active' => 'c*'],
        ['label' => 'Settings', 'href' => '#', 'active' => 'settings*'],
    ];
@endphp

<aside class="hidden w-60 shrink-0 flex-col border-r border-zinc-300 bg-white md:flex">
    <nav class="flex-1 px-3 py-6" aria-label="Main menu">
        <p class="px-3 text-xs font-medium uppercase tracking-wide text-zinc-500">Menu</p>

        <ul class="mt-2 space-y-1">
            @foreach ($items as $item)
                @php($isActive = request()->is($item['active']))
                <li>
                    <a
                        href="{{ $item['href'] }}"
                        @if ($isActive) aria-current="page" @endif
                        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-zinc-100 {{ $isActive ? 'bg-zinc-100 font-medium' : '' }}"
                    >
                        <span class="size-5 rounded-full bg-zinc-300" aria-hidden="true"></span>
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="px-3 pb-6">
        @if (Route::has('logout'))
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-zinc-100">
                    <span class="size-5 rounded-full bg-zinc-300" aria-hidden="true"></span>
                    Logout
                </button>
            </form>
        @else
            <span class="flex items-center gap-3 px-3 py-2 text-sm text-zinc-500">
                <span class="size-5 rounded-full bg-zinc-300" aria-hidden="true"></span>
                Logout
            </span>
        @endif
    </div>
</aside>
