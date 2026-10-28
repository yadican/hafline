<?php

namespace App\Services\Ledger;

use App\Models\EmployeeBonus;
use App\Models\EmployeePayment;
use App\Models\EmployeeSalary;
use App\Services\Reports\SiteExitReportService;
use Illuminate\Database\Eloquent\Model;

/**
 * Payroll on the employee's cari: a month's salary is owed at its last day, a bonus on its date,
 * and a payment settles it on the day it is paid.
 */
class PayrollLedger
{
    public function __construct(private Ledger $ledger) {}

    public function syncSalary(EmployeeSalary $salary): void
    {
        $salary->loadMissing('employee');
        $period = $salary->period;

        $this->ledger->sync($salary, [
            LedgerLine::credit(
                $salary->employee->party_id,
                $period->copy()->endOfMonth(),
                $salary->earned_salary,
                sprintf('Maaş · %s %d', SiteExitReportService::MONTHS[$period->month], $period->year),
            ),
        ]);
    }

    public function syncBonus(EmployeeBonus $bonus): void
    {
        $bonus->loadMissing('employee');

        $this->ledger->sync($bonus, [
            LedgerLine::credit(
                $bonus->employee->party_id,
                $bonus->occurred_on,
                $bonus->amount,
                trim('Prim'.($bonus->description ? ' · '.$bonus->description : '')),
            ),
        ]);
    }

    public function syncPayment(EmployeePayment $payment): void
    {
        $payment->loadMissing('employee');
        $period = $payment->period;

        $this->ledger->sync($payment, [
            LedgerLine::debit(
                $payment->employee->party_id,
                $payment->occurred_on,
                $payment->amount,
                sprintf('Personel ödemesi · %s %d dönemi', SiteExitReportService::MONTHS[$period->month], $period->year),
            )->paidBy($payment->payment_method),
        ]);
    }

    public function void(Model $document): void
    {
        $this->ledger->void($document);
    }
}
