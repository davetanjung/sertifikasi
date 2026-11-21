@php
    $currentUser = auth()->user();
    $displayName = $currentUser?->name ?? 'Admin';
    $displayRole = $currentUser === null
        ? 'Guest'
        : ($currentUser->role === \App\Utilities\Constants\Const_Role::ADMIN ? 'Administrator' : 'Tenant');
    $initials = collect(preg_split('/\s+/', trim($displayName)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
@endphp

<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-neutral-300 bg-white px-4 sm:px-6">
    <button
        type="button"
        class="-ml-1 rounded-md p-2 text-neutral-700 hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-black focus-visible:ring-offset-2 lg:hidden"
        aria-label="Open sidebar"
        aria-controls="app-sidebar"
        :aria-expanded="sidebarOpen"
        @click="sidebarOpen = true"
    >
        <x-icon name="menu" class="size-6" />
    </button>

    <div class="relative min-w-0 flex-1 sm:max-w-md">
        <label class="sr-only" for="navbar-search">Search</label>
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-neutral-500" />
        <input
            id="navbar-search"
            type="search"
            placeholder="Search..."
            class="h-10 w-full rounded-md border border-neutral-400 bg-white pl-9 pr-3 text-sm placeholder:text-neutral-500 focus:border-black focus:outline-none focus:ring-2 focus:ring-black"
        >
    </div>

    <div
        x-data="{ open: false }"
        class="relative ml-auto"
        @click.outside="open = false"
        @keydown.escape.window="open = false"
    >
        <button
            type="button"
            class="flex items-center gap-3 rounded-md p-1 text-left text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-black focus-visible:ring-offset-2"
            aria-haspopup="true"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="flex size-9 items-center justify-center rounded-full bg-black text-sm font-semibold text-white" aria-hidden="true">{{ $initials }}</span>
            <span class="hidden leading-tight sm:block">
                <span class="block font-medium text-black">{{ $displayName }}</span>
                <span class="block text-xs text-neutral-600">{{ $displayRole }}</span>
            </span>
            <x-icon name="chevron-down" class="size-4 text-neutral-600 transition-transform" ::class="open ? 'rotate-180' : ''" />
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition
            class="absolute right-0 z-50 mt-2 w-64 rounded-md border border-black bg-white"
        >
            <div class="px-4 py-3">
                <p class="truncate text-sm font-medium text-black">{{ $displayName }}</p>
                @if ($currentUser?->email)
                    <p class="truncate text-xs text-neutral-600">{{ $currentUser->email }}</p>
                @endif
                <p class="text-xs text-neutral-600">{{ $displayRole }}</p>
            </div>

            <div class="border-t border-neutral-300 p-1">
                @if (Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium text-black hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-black focus-visible:ring-offset-2"
                        >
                            <x-icon name="logout" class="size-4" />
                            Logout
                        </button>
                    </form>
                @else
                    <span class="block px-3 py-2 text-sm text-neutral-500" aria-disabled="true">No actions yet</span>
                @endif
            </div>
        </div>
    </div>
</header>
