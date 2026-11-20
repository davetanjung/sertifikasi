<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($title) ? $title . ' - ' : '' }}{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-zinc-200 font-sans text-zinc-900 antialiased">
        <x-layout.navbar />

        <div class="flex flex-1">
            <x-layout.sidebar />

            <main class="min-w-0 flex-1 p-8">
                <x-flash />

                {{ $slot }}
            </main>
        </div>

        <x-layout.footer />
    </body>
</html>
