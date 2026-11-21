@php
    $currentUser = auth()->user();
    $isTenant = $currentUser !== null && $currentUser->role !== \App\Utilities\Constants\Const_Role::ADMIN;

    /**
     * Menu groups. Items whose route does not exist yet are left out.
     *
     * @var array<int, array{label: string, items: array<int, array{label: string, route: string, icon: string, active: string}>}> $candidateGroups
     */
    $candidateGroups = $isTenant
        ? [
            [
                'label' => 'My account',
                'items' => [
                    ['label' => 'My Rentals', 'route' => 'rental.index', 'icon' => 'key', 'active' => 'rental.*'],
                    ['label' => 'My Vehicles', 'route' => 'vehicle.index', 'icon' => 'car', 'active' => 'vehicle.*'],
                    ['label' => 'Charging', 'route' => 'charging-transaction.index', 'icon' => 'bolt', 'active' => 'charging-transaction.*'],
                ],
            ],
        ]
        : [
            [
                'label' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'active' => 'dashboard'],
                ],
            ],
            [
                'label' => 'Management',
                'items' => [
                    ['label' => 'Tenants', 'route' => 'user.index', 'icon' => 'users', 'active' => 'user.*'],
                    ['label' => 'Garages', 'route' => 'garage.index', 'icon' => 'building', 'active' => 'garage.*'],
                    ['label' => 'Rentals', 'route' => 'rental.index', 'icon' => 'key', 'active' => 'rental.*'],
                    ['label' => 'Extensions', 'route' => 'extension.index', 'icon' => 'clock', 'active' => 'extension.*'],
                ],
            ],
            [
                'label' => 'Insights',
                'items' => [
                    ['label' => 'Reports', 'route' => 'report.index', 'icon' => 'chart', 'active' => 'report.*'],
                ],
            ],
        ];

    $groups = collect($candidateGroups)
        ->map(fn (array $group): array => [
            'label' => $group['label'],
            'items' => array_values(array_filter($group['items'], fn (array $item): bool => Route::has($item['route']))),
        ])
        ->filter(fn (array $group): bool => $group['items'] !== [])
        ->all();

    $linkBase = 'group flex items-center gap-3 rounded px-3 py-2 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-black';
@endphp

<div
    x-show="sidebarOpen"
    x-cloak
    x-transition.opacity
    class="fixed inset-0 z-30 bg-black/60 lg:hidden"
    @click="sidebarOpen = false"
    aria-hidden="true"
></div>

<aside
    id="app-sidebar"
    class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-black transition-transform duration-200 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    aria-label="Sidebar"
>
    <div class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-neutral-800 px-4">
        <a href="{{ url('/') }}" class="flex items-center gap-3 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
            <span class="flex size-9 items-center justify-center rounded bg-white text-black" aria-hidden="true">
                <x-icon name="car" class="size-6" />
            </span>
            <span class="text-lg font-semibold text-white">{{ config('app.name') }}</span>
        </a>

        <button
            type="button"
            class="rounded-md p-1.5 text-neutral-400 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white lg:hidden"
            aria-label="Close sidebar"
            @click="sidebarOpen = false"
        >
            <x-icon name="x" class="size-5" />
        </button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-6" aria-label="Main menu">
        @foreach ($groups as $group)
            <div>
                <p class="px-3 text-xs font-semibold uppercase tracking-wider text-neutral-400">{{ $group['label'] }}</p>

                <ul class="mt-2 space-y-1">
                    @foreach ($group['items'] as $item)
                        @php($isActive = request()->routeIs($item['active']))
                        <li>
                            <a
                                href="{{ route($item['route']) }}"
                                @if ($isActive) aria-current="page" @endif
                                class="{{ $linkBase }} {{ $isActive ? 'bg-white text-black' : 'text-neutral-300 hover:bg-white hover:text-black' }}"
                            >
                                <x-icon :name="$item['icon']" class="size-5 shrink-0" />
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        <div>
            <p class="px-3 text-xs font-semibold uppercase tracking-wider text-neutral-400">System</p>

            <ul class="mt-2 space-y-1">
                <li>
                    <span class="flex cursor-not-allowed items-center gap-3 rounded px-3 py-2 text-sm font-medium text-neutral-500" aria-disabled="true">
                        <x-icon name="cog" class="size-5 shrink-0" />
                        Settings
                    </span>
                </li>
            </ul>
        </div>
    </nav>
</aside>
