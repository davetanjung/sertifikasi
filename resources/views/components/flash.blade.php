{{--
    Flash messages. Reads the `flash_success` session flash (set by the Livewire
    form traits) and the `flash_danger` error (added with addError()).
--}}
@php
    $messages = collect([
        'success' => session('flash_success'),
        'danger' => $errors->first('flash_danger'),
    ])->filter();

    $styles = [
        'success' => 'border-green-300 bg-green-50 text-green-900',
        'danger' => 'border-red-300 bg-red-50 text-red-900',
    ];
@endphp

@foreach ($messages as $type => $message)
    <div
        x-data="{ show: true }"
        x-show="show"
        x-transition.opacity
        role="{{ $type === 'danger' ? 'alert' : 'status' }}"
        class="mb-6 flex items-start justify-between gap-4 rounded-md border px-4 py-3 text-sm {{ $styles[$type] }}"
    >
        <p>{{ $message }}</p>

        <button type="button" class="-m-1 rounded p-1 hover:bg-black/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-current" @click="show = false" aria-label="Dismiss">
            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="m5 5 10 10M15 5 5 15" stroke-linecap="round" />
            </svg>
        </button>
    </div>
@endforeach
