<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

use App\Exceptions\GeneralException;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait WithShowForm
{
    use HasCheckPermissionGate;

    protected $layout = 'components.layouts.app';

    /**
     * The $params the browser sends are deliberately ignored: a detail page can
     * only delete the record it is showing, which is the record the gate just
     * authorised.
     */
    public function delete(array $params = [])
    {
        $this->checkPermissionDeleteGate();

        try {
            DB::beginTransaction();
            $this->processDelete($this->obj->getKey());
            DB::commit();

            session()->flash('flash_success', $this->menuTitle.' has been deleted.');

            return redirect()->to($this->obj->getRouteIndex());
        } catch (Exception $exception) {
            DB::rollBack();
            $this->addError('flash_danger', _get_exception_message($exception));
        }
    }

    public function processDelete($id): void
    {
        throw new GeneralException('processDelete function must be defined in '.static::class);
    }

    public function processAction($action, $parameters = [])
    {
        try {
            DB::beginTransaction();
            $this->{$action}($parameters);
            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            $this->dispatch('page-to-top');

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            $this->addError('flash_danger', _get_exception_message($exception));

            return false;
        }
    }
}
