<div>
    @if ($registered)
        <div role="status" class="text-center">
            <span class="mx-auto flex size-14 items-center justify-center rounded border border-black bg-white text-black">
                <x-icon name="check-circle" class="size-8" />
            </span>
            <h1 class="mt-4 text-xl font-semibold text-black">Registration received</h1>
            <p class="mt-2 text-sm text-neutral-700">Your registration was received. An admin will verify it, and you can log in after it is approved.</p>
        </div>

        <div class="mt-6">
            <a href="{{ route('login') }}" class="inline-flex h-10 w-full items-center justify-center rounded-md bg-black px-4 text-sm font-semibold text-white hover:bg-neutral-800 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black">Back to login</a>
        </div>
    @else
        <x-flash />

        <h1 class="text-2xl font-semibold text-black">Register</h1>
        <p class="mt-1 mb-6 text-sm text-neutral-600">Create a tenant account. An admin will verify it before you can log in.</p>

        <form wire:submit="submit" class="space-y-5" novalidate>
            <x-form.input label="Full name" name="name" wire:model="name" autocomplete="name" />

            <x-form.input label="NIK (KTP number)" name="nik" wire:model="nik" inputmode="numeric" maxlength="16" />

            <x-form.input label="Phone" name="phone" type="tel" wire:model="phone" autocomplete="tel" />

            <x-form.input label="Email" name="email" type="email" wire:model="email" autocomplete="username" />

            <x-form.input label="Password" name="password" type="password" wire:model="password" autocomplete="new-password" />

            <x-form.input label="Confirm password" name="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" />

            <x-button type="submit" wire:loading.attr="disabled" class="w-full">
                <span wire:loading.remove>Register</span>
                <span wire:loading>Sending...</span>
            </x-button>
        </form>

        <p class="mt-6 text-center text-sm text-neutral-600">
            Already have an account?
            <a href="{{ route('login') }}" class="rounded font-medium text-black underline underline-offset-2 hover:no-underline hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-black">Login</a>
        </p>
    @endif
</div>
