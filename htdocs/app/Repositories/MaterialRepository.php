<?php

namespace App\Repositories;

use App\Models\Material;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class MaterialRepository extends Repository
{
    public function __construct(Material $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query(), $filters['q'] ?? null, ['name'])
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Active materials plus the ones a record already uses.
     *
     * @param  string|list<string>|null  $include
     * @return array<string, string>
     */
    public function options(string|array|null $include = null): array
    {
        $include = array_filter((array) $include);

        return $this->query()
            ->withTrashed()
            ->where(fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('active', true)->whereNull('deleted_at'))->orWhereIn('id', $include))
            ->orderBy('name')
            ->get(['id', 'name', 'unit'])
            ->mapWithKeys(fn (Material $material): array => [$material->id => $material->name.' ('.$material->unit->label().')'])
            ->all();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy('name');
    }

    protected function sortColumns(): array
    {
        return ['name' => 'name'];
    }
}
