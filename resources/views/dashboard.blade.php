@php
    /**
     * Quick links for the admin sections.
     *
     * @var array<int, array{title: string, description: string, route: string, icon: string}> $sections
     */
    $sections = [
        ['title' => 'Tenants', 'description' => 'Verify new tenants and review their status.', 'route' => 'user.index', 'icon' => 'users'],
        ['title' => 'Garages', 'description' => 'Create garages in batches and set daily prices.', 'route' => 'garage.index', 'icon' => 'building'],
        ['title' => 'Rentals', 'description' => 'Follow active rentals and extend end dates.', 'route' => 'rental.index', 'icon' => 'key'],
        ['title' => 'Reports', 'description' => 'See rentals and income per month, day, type and tenant.', 'route' => 'report.index', 'icon' => 'chart'],
    ];

    /**
     * Quick links for the tenant sections.
     *
     * @var array<int, array{title: string, description: string, route: string, icon: string}> $tenantSections
     */
    $tenantSections = [
        ['title' => 'My vehicles', 'description' => 'See the vehicles you registered.', 'route' => 'vehicle.index', 'icon' => 'car'],
        ['title' => 'Register a vehicle', 'description' => 'Add a car or motorcycle to your account.', 'route' => 'vehicle.create', 'icon' => 'plus'],
        ['title' => 'My rentals', 'description' => 'See your garage rentals.', 'route' => 'rental.index', 'icon' => 'key'],
        ['title' => 'Rent a garage', 'description' => 'Pick a vehicle, dates and a garage.', 'route' => 'rental.create', 'icon' => 'building'],
        ['title' => 'Charging', 'description' => 'Your electric charging history.', 'route' => 'charging-transaction.index', 'icon' => 'bolt'],
    ];

    $isAdmin = auth()->user()->isAdmin();
    $cards = $isAdmin ? $sections : $tenantSections;
@endphp

<x-layouts.app title="Dashboard">
    <x-page-header title="Dashboard" :subtitle="'Welcome, '.auth()->user()->name.'.'" />

    @unless ($isAdmin)
        <p class="-mt-2 mb-6 text-sm text-neutral-600">What would you like to do?</p>
    @endunless

    <div class="grid gap-6 sm:grid-cols-2 {{ $isAdmin ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
        @foreach ($cards as $section)
            @php($exists = Route::has($section['route']))

            @if ($exists)
                <a
                    href="{{ route($section['route']) }}"
                    class="group block rounded-md border border-neutral-300 bg-white p-6 transition hover:border-black focus:outline-none focus-visible:ring-2 focus-visible:ring-black"
                >
            @else
                <div class="block rounded-md border border-neutral-300 bg-white p-6" aria-disabled="true">
            @endif
                    <div class="flex items-start justify-between">
                        <span class="flex size-11 items-center justify-center {{ $exists ? 'text-black' : 'text-neutral-500' }}">
                            <x-icon :name="$section['icon']" class="size-6" />
                        </span>
                        @unless ($exists)
                            <span class="rounded border border-neutral-400 bg-white px-2 py-0.5 text-xs font-medium text-neutral-700">Coming soon</span>
                        @endunless
                    </div>
                    <h2 class="mt-4 text-base font-semibold {{ $exists ? 'text-black group-hover:text-black' : 'text-neutral-600' }}">{{ $section['title'] }}</h2>
                    <p class="mt-1 text-sm text-neutral-600">{{ $section['description'] }}</p>
            @if ($exists)
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>
</x-layouts.app>
