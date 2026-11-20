<header class="flex h-16 items-center justify-between gap-4 border-b border-zinc-300 bg-white px-6">
    <a href="{{ url('/') }}" class="flex items-center gap-3">
        <span class="size-8 rounded-md bg-zinc-300" aria-hidden="true"></span>
        <span class="text-lg font-semibold">{{ config('app.name') }}</span>
    </a>

    <div class="flex items-center gap-4">
        <label class="sr-only" for="navbar-search">Search</label>
        <input
            id="navbar-search"
            type="search"
            placeholder="Search..."
            class="h-9 w-56 rounded-md border border-zinc-300 bg-white px-3 text-sm placeholder:text-zinc-500 focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-300 sm:w-72"
        >

        <div x-data="{ open: false }" class="relative" @click.outside="open = false" @keydown.escape.window="open = false">
            <button
                type="button"
                class="flex items-center gap-2 rounded-md py-1 pr-1 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400"
                aria-haspopup="true"
                :aria-expanded="open"
                @click="open = !open"
            >
                <span class="size-8 rounded-full bg-zinc-300" aria-hidden="true"></span>
                <span>{{ auth()->user()?->name ?? 'Admin' }}</span>
                <svg class="size-4 text-zinc-600 transition" :class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path d="m5 8 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <div
                x-show="open"
                x-cloak
                x-transition.opacity
                class="absolute right-0 z-10 mt-2 w-44 rounded-md border border-zinc-300 bg-white py-1 shadow-sm"
            >
                @if (Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm hover:bg-zinc-100">Logout</button>
                    </form>
                @else
                    <span class="block px-4 py-2 text-sm text-zinc-500">No actions yet</span>
                @endif
            </div>
        </div>
    </div>
</header>
