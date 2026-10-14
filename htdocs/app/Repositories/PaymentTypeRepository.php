<?php

namespace App\Repositories;

use App\Models\PaymentType;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PaymentTypeRepository extends Repository
{
    public function __construct(PaymentType $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, active?: string, kind?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query(), $filters['q'] ?? null, ['name'])
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'))
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('kind', $kind));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy('name');
    }

    protected function sortColumns(): array
    {
        return [
            'name' => 'name',
            'kind' => 'kind',
        ];
    }
}
