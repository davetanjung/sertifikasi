<?php

declare(strict_types=1);

namespace App\Livewire\Report;

use App\Exceptions\GeneralException;
use App\Models\User;
use App\Services\ReportService;
use App\Utilities\Constants\Const_Role;
use App\Utilities\Constants\Const_Status;
use App\Utilities\Constants\Const_Type;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Index extends Component
{
    protected $layout = 'components.layouts.app';

    public $menuTitle = 'Report';

    public $start_date;

    public $end_date;

    public $garage_type;

    public $user_id;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->resetFilters();
    }

    /**
     * Back to the default filters: today, all garage types and all tenants.
     */
    public function resetFilters(): void
    {
        $this->start_date = today()->toDateString();
        $this->end_date = today()->toDateString();
        $this->garage_type = null;
        $this->user_id = null;
    }

    public function render(): View
    {
        $rows = [];
        $granularity = ReportService::BY_MONTH;
        $reportError = null;

        try {
            ['granularity' => $granularity, 'rows' => $rows] = $this->report();
        } catch (GeneralException $exception) {
            $reportError = $exception->getMessage();
        }

        return view('report.index', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'isDaily' => $granularity === ReportService::BY_DAY,
            'garageTypeOptions' => [
                Const_Type::GARAGE_BICYCLE => 'Bicycle',
                Const_Type::GARAGE_CAR => 'Car',
                Const_Type::GARAGE_ELECTRIC_CAR => 'Electric car',
            ],
            'tenantOptions' => User::query()
                ->where('role', Const_Role::TENANT)
                ->where('registration_status', Const_Status::APPROVED)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            'reportError' => $reportError,
        ])->layout($this->layout);
    }

    /**
     * One row per day when the range is inside one month, otherwise one row per month. The garage
     * type and tenant filters narrow both.
     *
     * @return array{granularity: string, rows: list<array<string, mixed>>}
     *
     * @throws GeneralException
     */
    private function report(): array
    {
        return ReportService::forRange(
            $this->optionalString($this->start_date),
            $this->optionalString($this->end_date),
            $this->optionalString($this->garage_type),
            $this->optionalString($this->user_id),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{rentals: int, rent_income: float, charging_income: float, total_income: float}
     */
    private function totals(array $rows): array
    {
        $rows = collect($rows);

        return [
            'rentals' => (int) $rows->sum('rentals'),
            'rent_income' => round((float) $rows->sum('rent_income'), 2),
            'charging_income' => round((float) $rows->sum('charging_income'), 2),
            'total_income' => round((float) $rows->sum('total_income'), 2),
        ];
    }

    private function optionalString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
