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
        <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
            <x-layout.sidebar />

            <div class="flex min-h-screen flex-col lg:pl-64">
                <x-layout.navbar />

                <main class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>

                <x-layout.footer />
            </div>
        </div>
        @livewireScripts
    </body>
</html>
