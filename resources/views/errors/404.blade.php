<x-layouts.app title="Page not found">
    <section class="mx-auto flex max-w-md flex-col items-center py-16 text-center">
        <p class="text-6xl font-semibold text-zinc-400">404</p>
        <h1 class="mt-4 text-2xl font-semibold">Page not found</h1>
        <p class="mt-2 text-sm text-zinc-600">The page you are looking for doesn't exist or has been moved.</p>

        <div class="mt-8 flex items-center gap-3">
            <a href="{{ url('/') }}" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 focus-visible:ring-offset-2">
                Back to home
            </a>
            <a href="javascript:history.back()" class="rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-medium hover:bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400">
                Go back
            </a>
        </div>
    </section>
</x-layouts.app>
