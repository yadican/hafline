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
     * Opening carry before year: earned + bonuses − payments.
     */
    public function openingCarry(string $employeeId, int $year): float
    {
        $cutoff = sprintf('%04d-01-01', $year);

        $earned = (float) EmployeeSalary::query()
            ->where('employee_id', $employeeId)
            ->where('period', '<', $cutoff)
            ->sum('earned_salary');

        $bonuses = (float) EmployeeBonus::query()
            ->where('employee_id', $employeeId)
            ->where('occurred_on', '<', $cutoff)
            ->sum('amount');

        $payments = (float) EmployeePayment::query()
            ->where('employee_id', $employeeId)
            ->where('period', '<', $cutoff)
            ->sum('amount');

        return round($earned + $bonuses - $payments, 2);
    }

    /**
     * @return Collection<int, EmployeeSalary>
     */
    public function salariesInYear(string $employeeId, int $year): Collection
    {
        return EmployeeSalary::query()
            ->where('employee_id', $employeeId)
            ->whereYear('period', $year)
            ->orderBy('period')
            ->get();
    }

    /**
     * @return Collection<int, EmployeeBonus>
     */
    public function bonusesInYear(string $employeeId, int $year): Collection
    {
        return EmployeeBonus::query()
            ->where('employee_id', $employeeId)
            ->whereYear('occurred_on', $year)
            ->orderBy('occurred_on')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return Collection<int, EmployeePayment>
     */
    public function paymentsInYear(string $employeeId, int $year): Collection
    {
        return EmployeePayment::query()
            ->where('employee_id', $employeeId)
            ->whereYear('period', $year)
            ->orderBy('occurred_on')
            ->orderBy('created_at')
            ->get();
    }
}
