<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

use App\Exceptions\GeneralException;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;

trait WithIndexForm
{
    use HasCheckPermissionGate;
    use WithPagination;

    public $no_item = 1;

    public $perPage = 10;

    public $sortAsc = false;

    public $sortField = 'created_at';

    protected $layout = 'components.layouts.app';

    private $data;

    private $query;

    abstract private function getQuery();

    public function sort($column, $toggleSort = true): void
    {
        if ($this->sortField === $column && $toggleSort) {
            $this->sortAsc = ! $this->sortAsc;
        }

        if ($this->sortField !== $column) {
            $this->sortAsc = true;
        }

        $this->sortField = $column;

        $this->query = $this->getQuery()
            ->reorder()
            ->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc');
    }

    public function processFilter(): void
    {
        if (! $this->query) {
            if ($this->sortField) {
                $this->sort($this->sortField, false);
            } else {
                $this->query = $this->getQuery();
            }
        }

        $this->data = $this->query->paginate($this->perPage);
        $page = $this->getPage();
        $this->no_item = ($page - 1) * $this->perPage + 1;

        if ($this->data->total() < ($page - 1) * $this->perPage + 1 && $page > 1) {
            $this->resetPage();
        }
    }

    public function delete(array $params): void
    {
        try {
            DB::beginTransaction();
            $this->guardDelete($params['id']);
            $this->processDelete($params['id']);
            DB::commit();

            session()->flash('flash_success', $this->menuTitle.' has been deleted.');
        } catch (Exception $exception) {
            DB::rollBack();
            $this->addError('flash_danger', _get_exception_message($exception));
        }

        $this->dispatch('page-to-top');
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

    /**
     * The id comes from the browser, so confirm the record exists and, once
     * permissions are on, that this user may delete it. Hiding a button is not
     * a guard.
     */
    private function guardDelete($id): void
    {
        $obj = $this->getModel()::query()->findOrFail($id);

        if (_check_permissions() && ! $obj->canDelete()) {
            throw new GeneralException('You are not allowed to delete this record.');
        }
    }
}
