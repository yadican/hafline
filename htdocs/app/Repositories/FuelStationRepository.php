<?php

namespace App\Repositories;

use App\Models\FuelStation;
use App\Models\Site;
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
     * @param  array{q?: string, site?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query()->with('site'), $filters['q'] ?? null, ['name'])
            ->when($filters['site'] ?? null, fn (Builder $query, string $site) => $query->where('site_id', $site))
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy('name');
    }

    protected function sortColumns(): array
    {
        return [
            'name' => 'name',
            'site' => fn (Builder $query, string $direction) => $query->orderBy(
                Site::withTrashed()->select('name')->whereColumn('sites.id', 'fuel_stations.site_id'),
                $direction,
            ),
            'unit_price' => 'unit_price',
        ];
    }
}
