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
     * @param  array{q?: string, direction?: string, group?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query(), $filters['q'] ?? null, ['name'])
            ->with('parent')
            ->withCount('children')
            ->when($filters['group'] ?? null, fn (Builder $query, string $group) => $query->where(fn (Builder $query) => $query->whereKey($group)->orWhere('parent_id', $group)))
            ->when($filters['direction'] ?? null, fn (Builder $query, string $direction) => $query->where('direction', $direction))
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Active categories usable for the given side, plus the ones already chosen on a record.
     *
     * @param  string|list<string>|null  $include
     * @return array<string, string>
     */
    public function options(?CashDirection $direction = null, string|array|null $include = null): array
    {
        $options = FormOptionsCache::remember(
            FormOptionsCache::CASH_CATEGORIES,
            $direction?->value ?? 'all',
            fn (): array => $this->query()
                ->with('parent')
                ->where('active', true)
                ->when($direction, fn (Builder $query, CashDirection $direction) => $query->for($direction))
                ->get()
                ->mapWithKeys(fn (CashCategory $category) => [$category->id => $category->fullName()])
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->all(),
        );

        return FormOptionsCache::withIncludes(
            $options,
            $include,
            fn (array $ids): array => CashCategory::withTrashed()->with('parent')->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (CashCategory $category) => [$category->id => $category->fullName()])
                ->all(),
        );
    }

    /**
     * Categories that can hold others: active ones that are not in a group themselves.
     *
     * @return array<string, string>
     */
    public function groupOptions(?string $except = null): array
    {
        return $this->query()
            ->whereNull('parent_id')
            ->where('active', true)
            ->when($except, fn (Builder $query, string $except) => $query->whereKeyNot($except))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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
