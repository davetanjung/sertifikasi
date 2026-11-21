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
    private const REPORT_MONTHLY = 'monthly';

    private const REPORT_DAILY = 'daily';

    private const REPORT_GARAGE_TYPE = 'garage_type';

    private const REPORT_TENANT = 'tenant';

    protected $layout = 'components.layouts.app';

    public $menuTitle = 'Report';

    public $report = self::REPORT_MONTHLY;

    public $year;

    public $month;

    public $garage_type;

    public $user_id;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->year = now()->year;
        $this->month = now()->month;
    }

    public function render(): View
    {
        $rows = [];
        $reportError = null;

        try {
            $rows = $this->rows();
        } catch (GeneralException $exception) {
            $reportError = $exception->getMessage();
        }

        return view('report.index', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'reportOptions' => [
                self::REPORT_MONTHLY => 'Monthly',
                self::REPORT_DAILY => 'Daily',
                self::REPORT_GARAGE_TYPE => 'Per garage type',
                self::REPORT_TENANT => 'Per tenant',
            ],
            'yearOptions' => collect(ReportService::years())->mapWithKeys(fn (int $year): array => [$year => (string) $year])->all(),
            'monthOptions' => collect(range(1, 12))->mapWithKeys(fn (int $month): array => [$month => now()->startOfMonth()->month($month)->format('F')])->all(),
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
     * Run the chosen report with only the filters that apply to it.
     *
     * @return list<array<string, mixed>>
     *
     * @throws GeneralException
     */
    private function rows(): array
    {
        $year = $this->optionalInteger($this->year, 'Year');
        $month = $this->optionalInteger($this->month, 'Month');
        $garageType = $this->optionalString($this->garage_type);
        $userId = $this->optionalString($this->user_id);

        return match ($this->report) {
            self::REPORT_MONTHLY => ReportService::monthly(
                $year ?? throw new GeneralException('Year is required.'),
                $garageType,
                $userId,
            ),
            self::REPORT_DAILY => ReportService::daily(
                $year ?? throw new GeneralException('Year is required.'),
                $month ?? throw new GeneralException('Month is required.'),
                $garageType,
                $userId,
            ),
            self::REPORT_GARAGE_TYPE => ReportService::byGarageType($year, $month, $userId),
            self::REPORT_TENANT => ReportService::byTenant($year, $month, $garageType),
            default => throw new GeneralException('Report type is not valid.'),
        };
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

    /**
     * @throws GeneralException
     */
    private function optionalInteger(mixed $value, string $label): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (int) $value != $value) {
            throw new GeneralException("{$label} is not valid.");
        }

        return (int) $value;
    }
}
