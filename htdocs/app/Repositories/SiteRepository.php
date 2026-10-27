<?php

namespace App\Repositories;

use App\Cache\FormOptionsCache;
use App\Models\Site;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SiteRepository extends Repository
{
    public function __construct(Site $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->withCount([
            'routes',
            'parties' => fn (Builder $query) => $query->select(DB::raw('count(distinct parties.id)')),
        ]);

        $query = $this->whereTerm($query, $filters['q'] ?? null, ['name'])
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  string|array<int, string>|null  $include
     * @return array<string, string>
     */
    public function options(bool $activeOnly = false, string|array|null $include = null): array
    {
        $options = FormOptionsCache::remember(
            FormOptionsCache::SITES,
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
            fn (array $ids): array => Site::withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->all(),
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
            'routes' => 'routes_count',
            'parties' => 'parties_count',
        ];
    }
}
