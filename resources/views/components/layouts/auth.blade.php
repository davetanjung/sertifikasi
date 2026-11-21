<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($title) ? $title . ' - ' : '' }}{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-white font-sans text-black antialiased">
        <div class="grid min-h-screen lg:grid-cols-2">
            <aside class="hidden flex-col justify-between bg-black p-12 text-white lg:flex">
                <a href="{{ url('/') }}" class="flex items-center gap-3 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                    <span class="flex size-10 items-center justify-center rounded bg-white text-black" aria-hidden="true">
                        <x-icon name="car" class="size-6" />
                    </span>
                    <span class="text-xl font-semibold">{{ config('app.name') }}</span>
                </a>

                <div>
                    <h2 class="text-4xl font-bold leading-tight tracking-tight">Garage rental management</h2>
                    <p class="mt-4 max-w-sm text-neutral-300">Rent garages by the day or by the month, with charging costs included.</p>
                </div>

                <p class="text-xs text-neutral-400">&copy; {{ now()->year }} {{ config('app.name') }}</p>
            </aside>

            <div class="flex flex-col items-center justify-center px-4 py-10 sm:px-8">
                <a href="{{ url('/') }}" class="mb-8 flex items-center gap-3 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-black lg:hidden">
                    <span class="flex size-9 items-center justify-center rounded bg-black text-white" aria-hidden="true">
                        <x-icon name="car" class="size-6" />
                    </span>
                    <span class="text-lg font-semibold">{{ config('app.name') }}</span>
                </a>

                <main class="w-full max-w-md rounded-md border border-neutral-300 bg-white p-6 sm:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
