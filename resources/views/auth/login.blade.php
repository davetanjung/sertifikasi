<div>
    <x-flash />

    <h1 class="text-2xl font-semibold text-black">Login</h1>
    <p class="mt-1 mb-6 text-sm text-neutral-600">Welcome back. Sign in to manage your garages.</p>

    <form wire:submit="process" class="space-y-5" novalidate>
        <x-form.input label="Email" name="email" type="email" wire:model="email" autocomplete="username" />

        <x-form.input label="Password" name="password" type="password" wire:model="password" autocomplete="current-password" />

        <div class="flex items-center gap-2">
            <input
                id="remember"
                name="remember"
                type="checkbox"
                wire:model="remember"
                class="size-4 rounded border-neutral-400 text-black focus:outline-none focus:ring-2 focus:ring-black"
            >
            <label for="remember" class="text-sm text-neutral-800">Remember me</label>
        </div>

        <x-button type="submit" wire:loading.attr="disabled" class="w-full">
            <span wire:loading.remove>Login</span>
            <span wire:loading>Signing in...</span>
        </x-button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-600">
        Don't have an account?
        <a href="{{ route('register') }}" class="rounded font-medium text-black underline underline-offset-2 hover:no-underline hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black">Create an account</a>
    </p>
</div>
