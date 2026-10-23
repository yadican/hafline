<?php

namespace App\Repositories;

use App\Cache\FormOptionsCache;
use App\Models\FuelStation;
use App\Models\Party;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class FuelStationRepository extends Repository
{
    public function __construct(FuelStation $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, site?: string, party?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query()->with(['sites', 'party']), $filters['q'] ?? null, ['name'])
            ->when($filters['site'] ?? null, fn (Builder $query, string $site) => $query->whereHas('sites', fn (Builder $query) => $query->whereKey($site)))
            ->when($filters['party'] ?? null, fn (Builder $query, string $party) => $query->where('party_id', $party))
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
            FormOptionsCache::FUEL_STATIONS,
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
            fn (array $ids): array => FuelStation::withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->all(),
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
            'party' => fn (Builder $query, string $direction) => $query->orderBy(
                Party::withTrashed()->select('name')->whereColumn('parties.id', 'fuel_stations.party_id'),
                $direction,
            ),
            'unit_price' => 'unit_price',
        ];
    }
}
