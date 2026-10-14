<?php

namespace App\Repositories;

use App\Support\Sort;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class Repository
{
    public function __construct(protected Model $model) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model->refresh();
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    /**
     * @return list<string>
     */
    public function sortFields(): array
    {
        return array_keys($this->sortColumns());
    }

    /**
     * Whitelist of sortable list fields: a column name, or a closure that receives the query and direction.
     *
     * @return array<string, string|Closure(Builder, string): mixed>
     */
    protected function sortColumns(): array
    {
        return [];
    }

    /**
     * The key is always added last so equal values keep a stable order across pages.
     */
    protected function applySort(Builder $query, ?Sort $sort): Builder
    {
        $column = $sort === null ? null : ($this->sortColumns()[$sort->field] ?? null);

        match (true) {
            $column instanceof Closure => $column($query, $sort->direction),
            is_string($column) => $query->orderBy($column, $sort->direction),
            default => $this->defaultSort($query),
        };

        return $query->orderByDesc($this->model->getQualifiedKeyName());
    }

    protected function defaultSort(Builder $query): void
    {
        $query->latest();
    }

    /**
     * @param  list<string>  $columns
     */
    protected function whereTerm(Builder $query, ?string $term, array $columns): Builder
    {
        if ($term === null || $term === '') {
            return $query;
        }

        $like = $this->likePattern($term);

        return $query->where(function (Builder $query) use ($columns, $like): void {
            foreach ($columns as $column) {
                $query->orWhereLike($column, $like);
            }
        });
    }

    protected function likePattern(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }

    protected function query(): Builder
    {
        return $this->model->newQuery();
    }
}
