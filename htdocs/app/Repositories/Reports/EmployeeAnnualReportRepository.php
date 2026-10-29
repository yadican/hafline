<?php

namespace App\Repositories\Reports;

use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeePayment;
use App\Models\EmployeeSalary;
use Illuminate\Support\Collection;

class EmployeeAnnualReportRepository
{
    public function employee(string $employeeId): ?Employee
    {
        return Employee::query()->with(['party.person', 'party.city', 'party.district'])->find($employeeId);
    }

    /**
     * Opening carry before the given date: earned + bonuses − payments.
     */
    public function openingCarry(string $employeeId, string $before): float
    {
        $earned = (float) EmployeeSalary::query()
            ->where('employee_id', $employeeId)
            ->where('period', '<', $before)
            ->sum('earned_salary');

        $bonuses = (float) EmployeeBonus::query()
            ->where('employee_id', $employeeId)
            ->where('occurred_on', '<', $before)
            ->sum('amount');

        $payments = (float) EmployeePayment::query()
            ->where('employee_id', $employeeId)
            ->where('period', '<', $before)
            ->sum('amount');

        return round($earned + $bonuses - $payments, 2);
    }

    /**
     * @return Collection<int, EmployeeSalary>
     */
    public function salariesBetween(string $employeeId, string $from, string $to): Collection
    {
        return EmployeeSalary::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('period', [$from, $to])
            ->orderBy('period')
            ->get();
    }

    /**
     * @return Collection<int, EmployeeBonus>
     */
    public function bonusesBetween(string $employeeId, string $from, string $to): Collection
    {
        return EmployeeBonus::query()
            ->where('employee_id', $employeeId)
            ->whereDate('occurred_on', '>=', $from)
            ->whereDate('occurred_on', '<=', $to)
            ->orderBy('occurred_on')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return Collection<int, EmployeePayment>
     */
    public function paymentsBetween(string $employeeId, string $from, string $to): Collection
    {
        return EmployeePayment::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('period', [$from, $to])
            ->orderBy('occurred_on')
            ->orderBy('created_at')
            ->get();
    }
}
