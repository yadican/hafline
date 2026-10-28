<?php

namespace App\Repositories;

use App\Cache\FormOptionsCache;
use App\Models\CompanyGroup;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CompanyGroupRepository extends Repository
{
    public function __construct(CompanyGroup $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query(), $filters['q'] ?? null, ['name'])
            ->withCount('companies')
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, string>
     */
    public function options(?string $include = null): array
    {
        $options = FormOptionsCache::remember(
            FormOptionsCache::COMPANY_GROUPS,
            'all',
            fn (): array => $this->query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all(),
        );

        return FormOptionsCache::withIncludes(
            $options,
            $include,
            fn (array $ids): array => CompanyGroup::withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->all(),
        );
    }

    public function hasCompanies(CompanyGroup $group): bool
    {
        return $group->companies()->exists();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy('name');
    }

    protected function sortColumns(): array
    {
        return [
            'name' => 'name',
            'companies' => 'companies_count',
        ];
    }
}
