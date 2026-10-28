<?php

namespace App\Services;

use App\Models\EmployeeBonus;
use App\Repositories\EmployeeBonusRepository;
use App\Services\Ledger\PayrollLedger;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class EmployeeBonusService extends Service
{
    public function __construct(
        private EmployeeBonusRepository $bonuses,
        private EmployeeService $employees,
        private PayrollLedger $payroll,
    ) {}

    /**
     * @return array{employees: array<string, string>}
     */
    public function formOptions(?string $includeEmployee = null): array
    {
        return [
            'employees' => $this->employees->options($includeEmployee, withPeriodOnly: true),
        ];
    }

    /**
     * @param  array{employee_id?: string, from?: string, to?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->bonuses->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EmployeeBonus
    {
        return $this->transaction(function () use ($data): EmployeeBonus {
            /** @var EmployeeBonus $bonus */
            $bonus = $this->bonuses->create([
                'employee_id' => $data['employee_id'],
                'amount' => $data['amount'],
                'occurred_on' => $data['occurred_on'],
                'description' => $data['description'] ?? null,
                'created_by' => Auth::id(),
            ]);
            $this->payroll->syncBonus($bonus);

            return $bonus;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EmployeeBonus $bonus, array $data): EmployeeBonus
    {
        return $this->transaction(function () use ($bonus, $data): EmployeeBonus {
            /** @var EmployeeBonus $updated */
            $updated = $this->bonuses->update($bonus, [
                'employee_id' => $data['employee_id'],
                'amount' => $data['amount'],
                'occurred_on' => $data['occurred_on'],
                'description' => $data['description'] ?? null,
            ]);
            $this->payroll->syncBonus($updated);

            return $updated;
        });
    }

    public function delete(EmployeeBonus $bonus): void
    {
        $this->transaction(function () use ($bonus): void {
            $this->payroll->void($bonus);
            $this->bonuses->delete($bonus);
        });
    }
}
