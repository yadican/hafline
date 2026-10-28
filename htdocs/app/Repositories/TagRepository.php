<?php

namespace App\Repositories;

use App\Cache\FormOptionsCache;
use App\Models\Tag;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TagRepository extends Repository
{
    public function __construct(Tag $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query(), $filters['q'] ?? null, ['name'])
            ->withCount('parties')
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  list<string>  $include
     * @return array<string, string>
     */
    public function options(array $include = []): array
    {
        $options = FormOptionsCache::remember(
            FormOptionsCache::TAGS,
            'all',
            fn (): array => $this->query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all(),
        );

        return FormOptionsCache::withIncludes(
            $options,
            $include,
            fn (array $ids): array => Tag::withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->all(),
        );
    }

    /**
     * A deleted tag leaves the cari cards it was on.
     */
    public function delete(Model $model): void
    {
        /** @var Tag $model */
        $model->parties()->detach();
        $model->delete();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy('name');
    }

    protected function sortColumns(): array
    {
        return [
            'name' => 'name',
            'parties' => 'parties_count',
        ];
    }
}
