<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
use App\Cache\SiteCache;
use App\Enums\PartyRole;
use App\Exceptions\OperationRejectedException;
use App\Models\Party;
use App\Models\Site;
use App\Repositories\PartyRepository;
use App\Repositories\SiteRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class SiteService extends Service
{
    public function __construct(
        private SiteRepository $sites,
        private PartyRepository $parties,
    ) {}

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = SiteCache::PER_PAGE): LengthAwarePaginator
    {
        return SiteCache::rememberFirstPage(
            $filters,
            $sort,
            fn () => $this->sites->search($filters, $sort, $perPage),
        );
    }

    /**
     * A role held only by companies lists only companies.
     *
     * @return array<string, array<string, string>> role => party options
     */
    public function partyOptions(?Site $site = null): array
    {
        return collect(PartyRole::forSites())
            ->mapWithKeys(fn (PartyRole $role) => [
                $role->value => $this->parties->options(
                    include: $site?->parties->where('pivot.role', $role->value)->pluck('id')->all(),
                    type: $role->onlyFor(),
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, list<string>> role => party ids
     */
    public function partyValues(Site $site): array
    {
        return collect(PartyRole::forSites())
            ->mapWithKeys(fn (PartyRole $role) => [
                $role->value => $site->parties->where('pivot.role', $role->value)->pluck('id')->values()->all(),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Site
    {
        $parties = Arr::pull($attributes, 'parties', []);

        /** @var Site */
        return $this->transaction(function () use ($attributes, $parties): Site {
            /** @var Site $site */
            $site = $this->sites->create($attributes);
            $this->syncParties($site, $parties);
            $this->flushCaches();

            return $site;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Site $site, array $attributes): Site
    {
        $parties = Arr::pull($attributes, 'parties', []);

        /** @var Site */
        return $this->transaction(function () use ($site, $attributes, $parties): Site {
            /** @var Site $updated */
            $updated = $this->sites->update($site, $attributes);
            $this->syncParties($updated, $parties);
            $this->flushCaches();

            return $updated;
        });
    }

    public function delete(Site $site): void
    {
        if ($site->routes()->exists()) {
            throw new OperationRejectedException('Bu şantiyeye bağlı güzergahlar var. Şantiyeyi silmek yerine pasif yapabilirsiniz.');
        }

        $this->transaction(function () use ($site): void {
            $this->sites->delete($site);
            $this->flushCaches();
        });
    }

    private function flushCaches(): void
    {
        SiteCache::flush();
        FormOptionsCache::flush(
            FormOptionsCache::SITES,
            FormOptionsCache::PARTIES,
            FormOptionsCache::HAUL_ROUTE_SITES,
            FormOptionsCache::HAUL_ROUTE_DUMPS,
            FormOptionsCache::HAUL_ROUTES,
        );
    }

    /**
     * A role missing from the input loses all of its links.
     *
     * @param  array<string, list<string>>  $parties  role => party ids
     */
    private function syncParties(Site $site, array $parties): void
    {
        foreach (PartyRole::forSites() as $role) {
            $ids = $parties[$role->value] ?? [];

            Party::withTrashed()->whereIn('id', $ids)->get()
                ->each(fn (Party $party) => $party->assignRole($role));

            $site->parties()->withPivotValue('role', $role->value)->sync($ids);
        }

        $site->unsetRelation('parties');
    }
}
