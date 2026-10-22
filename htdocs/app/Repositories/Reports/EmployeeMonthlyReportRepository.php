<?php

namespace App\Repositories\Reports;

use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeePayment;
use App\Models\EmployeeSalary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeMonthlyReportRepository
{
    /**
     * Employees with salary, payment, bonus activity in period or non-zero carry.
     *
     * @return Collection<int, object{
     *     id: string,
     *     name: string,
     *     earned: float,
     *     base_salary: float,
     *     worked_days: int,
     *     bonus: float,
     *     paid: float,
     *     carry: float
     * }>
     */
    public function rows(string $period, string $periodEnd): Collection
    {
        $salaries = EmployeeSalary::query()
            ->whereDate('period', $period)
            ->get()
            ->keyBy('employee_id');

        $bonuses = EmployeeBonus::query()
            ->select('employee_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereBetween('occurred_on', [$period, $periodEnd])
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        $payments = EmployeePayment::query()
            ->select('employee_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereDate('period', $period)
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        $priorEarned = EmployeeSalary::query()
            ->select('employee_id', DB::raw('COALESCE(SUM(earned_salary), 0) as total'))
            ->where('period', '<', $period)
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        $priorBonuses = EmployeeBonus::query()
            ->select('employee_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->where('occurred_on', '<', $period)
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        $priorPayments = EmployeePayment::query()
            ->select('employee_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->where('period', '<', $period)
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        $ids = collect()
            ->merge($salaries->keys())
            ->merge($bonuses->keys())
            ->merge($payments->keys())
            ->merge($priorEarned->keys())
            ->merge($priorBonuses->keys())
            ->merge($priorPayments->keys())
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $employees = Employee::query()
            ->with('party')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return $ids
            ->map(function (string $id) use ($employees, $salaries, $bonuses, $payments, $priorEarned, $priorBonuses, $priorPayments) {
                $employee = $employees->get($id);

                if ($employee === null) {
                    return null;
                }

                $salary = $salaries->get($id);
                $earned = round((float) ($salary?->earned_salary ?? 0), 2);
                $bonus = round((float) ($bonuses[$id] ?? 0), 2);
                $paid = round((float) ($payments[$id] ?? 0), 2);
                $carry = round(
                    (float) ($priorEarned[$id] ?? 0)
                    + (float) ($priorBonuses[$id] ?? 0)
                    - (float) ($priorPayments[$id] ?? 0),
                    2
                );

                if ($earned == 0.0 && $bonus == 0.0 && $paid == 0.0 && $carry == 0.0) {
                    return null;
                }

                return (object) [
                    'id' => $id,
                    'name' => $employee->name,
                    'earned' => $earned,
                    'base_salary' => round((float) ($salary?->base_salary ?? 0), 2),
                    'worked_days' => (int) ($salary?->worked_days ?? 0),
                    'bonus' => $bonus,
                    'paid' => $paid,
                    'carry' => $carry,
                ];
            })
            ->filter()
            ->sortBy(fn ($row) => mb_strtolower($row->name))
            ->values();
    }
}
