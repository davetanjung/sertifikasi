<x-layouts.app title="Page not found">
    <section class="mx-auto flex max-w-md flex-col items-center py-16 text-center">
        <p class="text-6xl font-semibold text-black">404</p>
        <h1 class="mt-4 text-2xl font-semibold">Page not found</h1>
        <p class="mt-2 text-sm text-neutral-600">The page you are looking for doesn't exist or has been moved.</p>

        <div class="mt-8 flex items-center gap-3">
            <a href="{{ url('/') }}" class="rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-black focus-visible:ring-offset-2">
                Back to home
            </a>
            <a href="javascript:history.back()" class="rounded-md border border-neutral-400 bg-white px-4 py-2 text-sm font-medium hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-black">
                Go back
            </a>
        </div>
    </section>
</x-layouts.app>
