<?php

namespace App\Repositories;

use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeePayment;
use App\Models\EmployeeSalary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeSalaryRepository extends Repository
{
    public function __construct(EmployeeSalary $model)
    {
        parent::__construct($model);
    }

    public function findForEmployeePeriod(string $employeeId, string $period): ?EmployeeSalary
    {
        return $this->query()
            ->where('employee_id', $employeeId)
            ->whereDate('period', $period)
            ->first();
    }

    /**
     * @param  list<string>  $employeeIds
     * @return Collection<string, EmployeeSalary>
     */
    public function forEmployeesInPeriod(array $employeeIds, string $period): Collection
    {
        if ($employeeIds === []) {
            return collect();
        }

        return $this->query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('period', $period)
            ->get()
            ->keyBy('employee_id');
    }

    /**
     * Carry-forward balance before period: earned + bonuses − payments.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, float>
     */
    public function carryForwardBefore(array $employeeIds, string $period): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $earned = $this->query()
            ->select('employee_id', DB::raw('COALESCE(SUM(earned_salary), 0) as total'))
            ->whereIn('employee_id', $employeeIds)
            ->where('period', '<', $period)
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        $bonuses = EmployeeBonus::query()
            ->select('employee_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereIn('employee_id', $employeeIds)
            ->where('occurred_on', '<', $period)
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        $payments = EmployeePayment::query()
            ->select('employee_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereIn('employee_id', $employeeIds)
            ->where('period', '<', $period)
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        $result = [];

        foreach ($employeeIds as $id) {
            $result[$id] = round(
                (float) ($earned[$id] ?? 0) + (float) ($bonuses[$id] ?? 0) - (float) ($payments[$id] ?? 0),
                2
            );
        }

        return $result;
    }

    /**
     * @param  list<string>  $employeeIds
     * @return array<string, float>
     */
    public function periodBonuses(array $employeeIds, string $period, string $periodEnd): array
    {
        if ($employeeIds === []) {
            return [];
        }

        return EmployeeBonus::query()
            ->select('employee_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('occurred_on', [$period, $periodEnd])
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id')
            ->map(fn ($total) => round((float) $total, 2))
            ->all();
    }

    /**
     * @param  array{employee_id: string, period: string, base_salary: float|string, worked_days: int, earned_salary: float|string}  $attributes
     */
    public function upsertForPeriod(array $attributes): EmployeeSalary
    {
        $existing = $this->findForEmployeePeriod($attributes['employee_id'], $attributes['period']);

        if ($existing !== null) {
            /** @var EmployeeSalary $updated */
            $updated = $this->update($existing, $attributes);

            return $updated;
        }

        /** @var EmployeeSalary $created */
        $created = $this->create($attributes);

        return $created;
    }

    /**
     * Period payroll snapshot for payment / bonus forms.
     * Carry is unpaid remainder before the period (past months).
     *
     * @return array{carry: float, earned: float, bonus: float, paid: float, balance: float}
     */
    public function balanceSummary(string $employeeId, string $period): array
    {
        $periodEnd = Carbon::parse($period)->endOfMonth()->toDateString();
        $carry = $this->carryForwardBefore([$employeeId], $period)[$employeeId] ?? 0.0;

        $earned = (float) $this->query()
            ->where('employee_id', $employeeId)
            ->whereDate('period', $period)
            ->sum('earned_salary');

        $bonus = (float) EmployeeBonus::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('occurred_on', [$period, $periodEnd])
            ->sum('amount');

        $paid = (float) EmployeePayment::query()
            ->where('employee_id', $employeeId)
            ->whereDate('period', $period)
            ->sum('amount');

        return [
            'carry' => round($carry, 2),
            'earned' => round($earned, 2),
            'bonus' => round($bonus, 2),
            'paid' => round($paid, 2),
            'balance' => round($carry + $earned + $bonus - $paid, 2),
        ];
    }

    /**
     * Ensure every active employee has a salary card for the period.
     *
     * @return list<EmployeeSalary> the cards created now
     */
    public function ensureCardsForActiveEmployees(string $period): array
    {
        $existing = $this->query()
            ->whereDate('period', $period)
            ->pluck('employee_id')
            ->all();

        $created = [];

        Employee::query()
            ->where('active', true)
            ->whereNotIn('id', $existing)
            ->each(function (Employee $employee) use ($period, &$created): void {
                $base = (float) $employee->salary;
                $created[] = $this->create([
                    'employee_id' => $employee->id,
                    'period' => $period,
                    'base_salary' => $base,
                    'worked_days' => 30,
                    'earned_salary' => $base,
                ]);
            });

        return $created;
    }
}
