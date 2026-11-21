<?php

declare(strict_types=1);

namespace App\Livewire\Extension;

use App\Exceptions\GeneralException;
use App\Models\Rental;
use App\Services\RentalService;
use App\Traits\Livewire\WithCreateForm;
use App\Utilities\Constants\Const_Status;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

final class Create extends Component
{
    use WithCreateForm;
    use WithFileUploads;

    public $model = Rental::class;

    public $menuTitle = 'Extension';

    #[Url(as: 'rental')]
    public $rental_id;

    public $new_end_date;

    public $payment_proof;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->loadRental();
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        $latestEndDate = RentalService::latestEndDate($this->loadRental())->toDateString();

        return [
            'new_end_date' => ['required', 'date_format:Y-m-d', 'after:'.$latestEndDate],
            'payment_proof' => ['required', 'image', 'max:2048'],
        ];
    }

    public function submit(array $validated): Rental
    {
        $rental = $this->loadRental();
        $path = $this->payment_proof->store('extension-proofs', 'public');

        try {
            return RentalService::extend($rental, $validated['new_end_date'], $path);
        } catch (Exception $exception) {
            // Nothing was saved, so do not leave the uploaded proof behind.
            Storage::disk('public')->delete($path);

            throw $exception;
        }
    }

    protected function submitRedirectUrl(): ?string
    {
        return route('rental.show', $this->rental_id);
    }

    public function render(): View
    {
        $rental = $this->loadRental();
        $latestEndDate = RentalService::latestEndDate($rental);
        $blockedReason = $latestEndDate->lt(today()) ? 'The rental has already ended and cannot be extended.' : null;

        $preview = null;
        $previewError = null;

        if ($blockedReason === null && $this->hasValidDate()) {
            try {
                $preview = RentalService::previewExtension($rental, $this->new_end_date);
            } catch (GeneralException $exception) {
                $previewError = $exception->getMessage();
            }
        }

        return view('extension.create', [
            'rental' => $rental,
            'latestEndDate' => $latestEndDate->toDateString(),
            'preview' => $preview,
            'previewError' => $previewError,
            'blockedReason' => $blockedReason,
        ])->layout($this->layout);
    }

    /**
     * The chosen rental: it must exist and be the new row, not an extension row.
     * Checked on every request because the id lives in the URL and can be changed.
     */
    private function loadRental(): Rental
    {
        $rental = Rental::query()->with(['garage', 'vehicle', 'user'])->find($this->rental_id);

        abort_if($rental === null || $rental->status !== Const_Status::NEW, 404);

        return $rental;
    }

    private function hasValidDate(): bool
    {
        return is_string($this->new_end_date)
            && Carbon::canBeCreatedFromFormat($this->new_end_date, 'Y-m-d');
    }
}
