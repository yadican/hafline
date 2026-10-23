<?php

namespace App\Repositories;

use App\Cache\FormOptionsCache;
use App\Models\Bank;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class BankRepository extends Repository
{
    public function __construct(Bank $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query(), $filters['q'] ?? null, ['name', 'representative', 'phone', 'account_no'])
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  string|array<int, string>|null  $include
     * @return array<string, string>
     */
    public function options(bool $activeOnly = true, string|array|null $include = null): array
    {
        $options = FormOptionsCache::remember(
            FormOptionsCache::BANKS,
            $activeOnly ? 'active' : 'all',
            fn (): array => $this->query()
                ->when($activeOnly, fn (Builder $query) => $query->where('active', true))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        );

        return FormOptionsCache::withIncludes(
            $options,
            $include,
            fn (array $ids): array => Bank::withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->all(),
        );
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy('name');
    }

    protected function sortColumns(): array
    {
        return [
            'name' => 'name',
            'representative' => 'representative',
        ];
    }
}
