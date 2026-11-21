{{--
    Flash messages. Reads the `flash_success` session flash (set by the Livewire
    form traits) and the `flash_danger` error (added with addError()).
--}}
@php
    $messages = collect([
        'success' => session('flash_success'),
        'danger' => isset($errors) ? $errors->first('flash_danger') : null,
    ])->filter();

    $styles = [
        'success' => 'border-green-800 bg-green-800 text-white',
        'danger' => 'border-red-800 bg-red-800 text-white',
    ];

    $icons = [
        'success' => 'check-circle',
        'danger' => 'exclamation-circle',
    ];
@endphp

@foreach ($messages as $type => $message)
    <div
        x-data="{ show: true }"
        x-show="show"
        x-transition.opacity
        role="{{ $type === 'danger' ? 'alert' : 'status' }}"
        class="mb-6 flex items-start gap-3 rounded-md border px-4 py-3 text-sm {{ $styles[$type] }}"
    >
        <x-icon :name="$icons[$type]" class="mt-0.5 size-5 shrink-0" />

        <p class="flex-1 font-bold">{{ $message }}</p>

        <button type="button" class="-m-1 rounded p-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-current {{ $type === 'danger' ? 'hover:bg-red-900' : 'hover:bg-green-900' }}" @click="show = false" aria-label="Dismiss">
            <x-icon name="x" class="size-4" />
        </button>
    </div>
@endforeach
