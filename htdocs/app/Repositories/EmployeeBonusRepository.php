<?php

namespace App\Repositories;

use App\Models\EmployeeBonus;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EmployeeBonusRepository extends Repository
{
    public function __construct(EmployeeBonus $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{employee_id?: string, from?: string, to?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['employee.party'])
            ->when($filters['employee_id'] ?? null, fn (Builder $query, string $id) => $query->where('employee_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('occurred_on', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('occurred_on', '<=', $to));

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
        ];
    }
}
