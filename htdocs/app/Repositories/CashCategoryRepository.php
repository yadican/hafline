<?php

namespace App\Repositories;

use App\Cache\FormOptionsCache;
use App\Enums\CashDirection;
use App\Models\CashCategory;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CashCategoryRepository extends Repository
{
    public function __construct(CashCategory $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, direction?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query(), $filters['q'] ?? null, ['name'])
            ->when($filters['direction'] ?? null, fn (Builder $query, string $direction) => $query->where('direction', $direction))
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Active categories usable for the given side, plus the one already chosen on a record.
     *
     * @return array<string, string>
     */
    public function options(?CashDirection $direction = null, ?string $include = null): array
    {
        $options = FormOptionsCache::remember(
            FormOptionsCache::CASH_CATEGORIES,
            $direction?->value ?? 'all',
            fn (): array => $this->query()
                ->where('active', true)
                ->when($direction, fn (Builder $query, CashDirection $direction) => $query->for($direction))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        );

        return FormOptionsCache::withIncludes(
            $options,
            $include,
            fn (array $ids): array => CashCategory::withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->all(),
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
            'direction' => 'direction',
        ];
    }
}
