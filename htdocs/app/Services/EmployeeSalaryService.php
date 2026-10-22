<?php

namespace App\Services;

use App\Exceptions\OperationRejectedException;
use App\Repositories\EmployeeRepository;
use App\Repositories\EmployeeSalaryRepository;
use App\Services\Reports\SiteExitReportService;
use Illuminate\Support\Carbon;

class EmployeeSalaryService extends Service
{
    public function __construct(
        private EmployeeSalaryRepository $salaries,
        private EmployeeRepository $employees,
    ) {}

    /**
     * @return array{years: array<int, string>, months: array<int, string>}
     */
    public function formOptions(): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{year: int, month: int}  $filters
     * @return array{
     *     period: string,
     *     period_label: string,
     *     year: int,
     *     month: int,
     *     rows: list<array{
     *         employee_id: string,
     *         name: string,
     *         occupation: ?string,
     *         started_on: ?string,
     *         ended_on: ?string,
     *         carry: float,
     *         base_salary: float,
     *         bonus: float,
     *         worked_days: int,
     *         earned: float,
     *         payable: float
     *     }>
     * }
     */
    public function grid(array $filters): array
    {
        $period = Carbon::create($filters['year'], $filters['month'], 1)->startOfMonth();
        $this->assertPastPeriod($period);

        $periodDate = $period->toDateString();
        $periodEnd = $period->copy()->endOfMonth()->toDateString();
        $staff = $this->employees->coveringPeriod($periodDate);
        $ids = $staff->pluck('id')->all();
        $cards = $this->salaries->forEmployeesInPeriod($ids, $periodDate);
        $carry = $this->salaries->carryForwardBefore($ids, $periodDate);
        $bonuses = $this->salaries->periodBonuses($ids, $periodDate, $periodEnd);

        $rows = [];

        foreach ($staff as $employee) {
            $card = $cards->get($employee->id);
            $base = $card !== null ? (float) $card->base_salary : (float) $employee->salary;
            $days = $card !== null ? (int) $card->worked_days : 30;
            $earned = $card !== null
                ? (float) $card->earned_salary
                : $this->earnedSalary($base, $days);
            $bonus = (float) ($bonuses[$employee->id] ?? 0);
            $carryAmount = (float) ($carry[$employee->id] ?? 0);

            $covering = $employee->periods->first(function ($p) use ($periodDate, $periodEnd) {
                $startedOk = $p->started_on === null || $p->started_on->toDateString() <= $periodEnd;
                $endedOk = $p->ended_on === null || $p->ended_on->toDateString() >= $periodDate;

                return $startedOk && $endedOk;
            });

            $rows[] = [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'occupation' => $employee->occupation?->label(),
                'started_on' => $covering?->started_on?->toDateString(),
                'ended_on' => $covering?->ended_on?->toDateString(),
                'carry' => $carryAmount,
                'base_salary' => $base,
                'bonus' => $bonus,
                'worked_days' => $days,
                'earned' => $earned,
                'payable' => round($carryAmount + $earned + $bonus, 2),
            ];
        }

        return [
            'period' => $periodDate,
            'period_label' => SiteExitReportService::MONTHS[$filters['month']].' '.$filters['year'].' Maaş Kartı',
            'year' => $filters['year'],
            'month' => $filters['month'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{year: int, month: int, rows: list<array{employee_id: string, base_salary: float|string, worked_days: int|string}>}  $data
     */
    public function save(array $data): void
    {
        $period = Carbon::create($data['year'], $data['month'], 1)->startOfMonth();
        $this->assertPastPeriod($period);
        $periodDate = $period->toDateString();

        $this->transaction(function () use ($data, $periodDate): void {
            $allowed = $this->employees->coveringPeriod($periodDate)->pluck('id')->all();

            foreach ($data['rows'] as $row) {
                if (! in_array($row['employee_id'], $allowed, true)) {
                    continue;
                }

                $base = round((float) $row['base_salary'], 2);
                $days = max(0, min(31, (int) $row['worked_days']));

                $this->salaries->upsertForPeriod([
                    'employee_id' => $row['employee_id'],
                    'period' => $periodDate,
                    'base_salary' => $base,
                    'worked_days' => $days,
                    'earned_salary' => $this->earnedSalary($base, $days),
                ]);
            }
        });
    }

    public function earnedSalary(float $base, int $days): float
    {
        return round($base / 30 * $days, 2);
    }

    private function assertPastPeriod(Carbon $period): void
    {
        if ($period->greaterThanOrEqualTo(now()->startOfMonth())) {
            throw new OperationRejectedException('İçinde bulunulan veya gelecek ay için maaş kartı oluşturulamaz.');
        }
    }
}
