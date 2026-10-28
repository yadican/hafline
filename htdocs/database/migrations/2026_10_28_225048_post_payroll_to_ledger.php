<?php

use App\Models\EmployeeBonus;
use App\Models\EmployeePayment;
use App\Models\EmployeeSalary;
use App\Services\Ledger\PayrollLedger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Salary, bonus and payment records that already exist post to the employee's cari, the same way
 * new ones do from now on. Deleted records do not post.
 */
return new class extends Migration
{
    public function up(): void
    {
        $payroll = app(PayrollLedger::class);

        EmployeeSalary::query()->with('employee')->lazyById(500)->each(fn (EmployeeSalary $salary) => $payroll->syncSalary($salary));
        EmployeeBonus::query()->with('employee')->lazyById(500)->each(fn (EmployeeBonus $bonus) => $payroll->syncBonus($bonus));
        EmployeePayment::query()->with('employee')->lazyById(500)->each(fn (EmployeePayment $payment) => $payroll->syncPayment($payment));
    }

    public function down(): void
    {
        DB::table('ledger_entries')
            ->whereIn('source_type', ['employee_salary', 'employee_bonus', 'employee_payment'])
            ->whereNotNull('reversal_of')
            ->delete();

        DB::table('ledger_entries')
            ->whereIn('source_type', ['employee_salary', 'employee_bonus', 'employee_payment'])
            ->delete();
    }
};
