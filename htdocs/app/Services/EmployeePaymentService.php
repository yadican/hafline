<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\EmployeePayment;
use App\Repositories\EmployeePaymentRepository;
use App\Repositories\EmployeeSalaryRepository;
use App\Services\Reports\SiteExitReportService;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class EmployeePaymentService extends Service
{
    public function __construct(
        private EmployeePaymentRepository $payments,
        private EmployeeSalaryRepository $salaries,
        private EmployeeService $employees,
    ) {}

    /**
     * @return array{employees: array<string, string>, years: array<int, string>, months: array<int, string>, payment_methods: array<string, string>}
     */
    public function formOptions(?string $includeEmployee = null): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            'employees' => $this->employees->options($includeEmployee),
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
            'payment_methods' => PaymentMethod::options(),
        ];
    }

    /**
     * @param  array{employee_id?: string, year?: int, month?: int}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->payments->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EmployeePayment
    {
        return $this->transaction(function () use ($data): EmployeePayment {
            $period = Carbon::create((int) $data['year'], (int) $data['month'], 1)->toDateString();

            $this->salaries->ensureCardsForActiveEmployees($period);

            /** @var EmployeePayment $payment */
            $payment = $this->payments->create([
                'employee_id' => $data['employee_id'],
                'payment_method' => $data['payment_method'] ?? null,
                'amount' => $data['amount'],
                'period' => $period,
                'occurred_on' => $data['occurred_on'] ?? now()->toDateString(),
                'description' => $data['description'] ?? null,
                'created_by' => Auth::id(),
            ]);

            return $payment;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EmployeePayment $payment, array $data): EmployeePayment
    {
        return $this->transaction(function () use ($payment, $data): EmployeePayment {
            $period = Carbon::create((int) $data['year'], (int) $data['month'], 1)->toDateString();

            /** @var EmployeePayment $updated */
            $updated = $this->payments->update($payment, [
                'employee_id' => $data['employee_id'],
                'payment_method' => $data['payment_method'] ?? null,
                'amount' => $data['amount'],
                'period' => $period,
                'occurred_on' => $data['occurred_on'] ?? $payment->occurred_on?->toDateString(),
                'description' => $data['description'] ?? null,
            ]);

            return $updated;
        });
    }

    public function delete(EmployeePayment $payment): void
    {
        $this->transaction(fn () => $this->payments->delete($payment));
    }
}
