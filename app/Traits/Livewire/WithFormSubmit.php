<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

use App\Exceptions\GeneralException;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shared submit flow for create and edit pages:
 * validate -> sanitize -> transaction -> submit() -> flash -> redirect.
 *
 * @property mixed $menuTitle
 */
trait WithFormSubmit
{
    public function submitDefault(): void
    {
        $this->submitAndShow();
    }

    public function submitAndCreate(): void
    {
        $this->processSubmit('create');
    }

    public function submitAndShow(): void
    {
        $this->processSubmit('show');
    }

    public function submitAndBackToIndex(): void
    {
        $this->processSubmit('index');
    }

    public function submit(array $validated): never
    {
        throw new GeneralException('submit function must be defined in '.static::class);
    }

    /**
     * Override in a component to always redirect to a fixed URL after submit.
     */
    protected function submitRedirectUrl(): ?string
    {
        return null;
    }

    private function processSubmit(string $to)
    {
        $transactionStarted = false;

        try {
            $validated = _sanitize_data($this->validate());

            DB::beginTransaction();
            $transactionStarted = true;
            $obj = $this->submit($validated);
            DB::commit();

            session()->flash('flash_success', $this->menuTitle.' '.$this->submitMessage.'.');

            if ($this->submitRedirectUrl()) {
                return redirect()->to($this->submitRedirectUrl());
            }

            return redirect()->to(match ($to) {
                'show' => $obj->getRouteShow(),
                'create' => $obj->getRouteCreate(),
                default => $obj->getRouteIndex(),
            });
        } catch (Exception $exception) {
            // Only roll back a transaction this method opened: a validation error happens before it starts.
            if ($transactionStarted) {
                DB::rollBack();
            }

            $this->dispatch('page-to-top');

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            $this->addError('flash_danger', _get_exception_message($exception));

            return false;
        }
    }
}
