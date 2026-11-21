@props(['label', 'name', 'type' => 'text'])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-black">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class([
            'h-10 w-full rounded-md bg-white px-3 text-sm text-black placeholder:text-neutral-500 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-1',
            'border border-neutral-400 focus:border-black' => ! $errors->has($name),
            'border-2 border-red-800' => $errors->has($name),
        ]) }}
    >
    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 flex items-center gap-1 text-xs font-bold text-red-800"><x-icon name="exclamation-circle" class="size-4 shrink-0" />{{ $message }}</p>
    @enderror
</div>
