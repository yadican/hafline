<?php

namespace App\Repositories;

use App\Models\Party;
use App\Models\Quarry;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class QuarryRepository extends Repository
{
    public function __construct(Quarry $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query()->with('party'), $filters['q'] ?? null, ['name'])
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
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
            'party' => fn (Builder $query, string $direction) => $query->orderBy(
                Party::withTrashed()->select('name')->whereColumn('parties.id', 'quarries.party_id'),
                $direction,
            ),
        ];
    }
}
