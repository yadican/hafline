<?php

namespace App\Repositories;

use App\Cache\FormOptionsCache;
use App\Models\DumpSite;
use App\Models\HaulRoute;
use App\Models\Site;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class HaulRouteRepository extends Repository
{
    public function __construct(HaulRoute $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{site?: string, dump_site?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['site', 'dumpSite', 'classPrices.vehicleClass'])
            ->withCount('vehiclePrices')
            ->when($filters['site'] ?? null, fn (Builder $query, string $site) => $query->where('site_id', $site))
            ->when($filters['dump_site'] ?? null, fn (Builder $query, string $dumpSite) => $query->where('dump_site_id', $dumpSite))
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Sites that have at least one route open for hauls.
     *
     * @return array<string, string>
     */
    public function siteOptions(?string $includeRoute = null): array
    {
        $options = FormOptionsCache::remember(
            FormOptionsCache::HAUL_ROUTE_SITES,
            'open',
            fn (): array => Site::withTrashed()
                ->whereIn('id', $this->open(null)->select('site_id'))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        );

        if (! $includeRoute) {
            return $options;
        }

        $siteId = $this->query()->whereKey($includeRoute)->value('site_id');

        return FormOptionsCache::withIncludes(
            $options,
            $siteId,
            fn (array $ids): array => Site::withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->all(),
        );
    }

    /**
     * Dump sites reachable from the site over an open route.
     *
     * @return array<string, string>
     */
    public function dumpSiteOptions(?string $siteId, ?string $includeRoute = null): array
    {
        if (! $siteId || ! Str::isUuid($siteId)) {
            return [];
        }

        $options = FormOptionsCache::remember(
            FormOptionsCache::HAUL_ROUTE_DUMPS,
            'open:'.$siteId,
            fn (): array => DumpSite::withTrashed()
                ->whereIn('id', $this->open(null)->where('site_id', $siteId)->select('dump_site_id'))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        );

        if (! $includeRoute) {
            return $options;
        }

        $dumpSiteId = $this->query()->whereKey($includeRoute)->value('dump_site_id');

        return FormOptionsCache::withIncludes(
            $options,
            $dumpSiteId,
            fn (array $ids): array => DumpSite::withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->all(),
        );
    }

    /**
     * The open route between the two places; $includeRoute is accepted even when it has been closed since.
     */
    public function findOpen(?string $siteId, ?string $dumpSiteId, ?string $includeRoute = null): ?HaulRoute
    {
        if (! Str::isUuid((string) $siteId) || ! Str::isUuid((string) $dumpSiteId)) {
            return null;
        }

        return $this->open($includeRoute)
            ->where('site_id', $siteId)
            ->where('dump_site_id', $dumpSiteId)
            ->first();
    }

    /**
     * A route is open when it, its site and its dump site are all active.
     *
     * @return Builder<HaulRoute>
     */
    private function open(?string $includeRoute): Builder
    {
        return $this->query()->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query
                ->where('active', true)
                ->whereHas('site', fn (Builder $site) => $site->where('active', true))
                ->whereHas('dumpSite', fn (Builder $dumpSite) => $dumpSite->where('active', true)))
            ->when($includeRoute, fn (Builder $query) => $query->orWhere('id', $includeRoute)));
    }

    protected function defaultSort(Builder $query): void
    {
        $this->sortColumns()['site']($query, 'asc');
        $this->sortColumns()['dump_site']($query, 'asc');
    }

    protected function sortColumns(): array
    {
        return [
            'site' => fn (Builder $query, string $direction) => $query->orderBy(
                Site::withTrashed()->select('name')->whereColumn('sites.id', 'haul_routes.site_id'),
                $direction,
            ),
            'dump_site' => fn (Builder $query, string $direction) => $query->orderBy(
                DumpSite::withTrashed()->select('name')->whereColumn('dump_sites.id', 'haul_routes.dump_site_id'),
                $direction,
            ),
            'price' => 'price',
            'bonus' => 'bonus',
        ];
    }
}
