<?php

namespace App\Repositories;

use App\Models\EmployeePayment;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EmployeePaymentRepository extends Repository
{
    public function __construct(EmployeePayment $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{employee_id?: string, year?: int, month?: int}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['employee.party'])
            ->when($filters['employee_id'] ?? null, fn (Builder $query, string $id) => $query->where('employee_id', $id))
            ->when(isset($filters['year'], $filters['month']), function (Builder $query) use ($filters): void {
                $period = sprintf('%04d-%02d-01', $filters['year'], $filters['month']);
                $query->whereDate('period', $period);
            });

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderByDesc('occurred_on')->orderByDesc('created_at');
    }

    protected function sortColumns(): array
    {
        return [
            'occurred_on' => 'occurred_on',
            'amount' => 'amount',
            'period' => 'period',
        ];
    }
}
