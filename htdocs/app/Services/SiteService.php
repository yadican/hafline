<?php

namespace App\Services;

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
            fn() => $this->sites->search($filters, $sort, $perPage),
        );
    }

    /**
     * @return array<string, string>
     */
    public function partyOptions(?Site $site = null): array
    {
        return $this->parties->options(include: $site?->parties->pluck('id')->all());
    }

    /**
     * @return array<string, list<string>> role => party ids
     */
    public function partyValues(Site $site): array
    {
        return collect(PartyRole::forSites())
            ->mapWithKeys(fn(PartyRole $role) => [
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

            return $updated;
        });
    }

    public function delete(Site $site): void
    {
        if ($site->routes()->exists()) {
            throw new OperationRejectedException('Bu şantiyeye bağlı güzergahlar var. Şantiyeyi silmek yerine pasif yapabilirsiniz.');
        }

        $this->transaction(fn() => $this->sites->delete($site));
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
                ->each(fn(Party $party) => $party->assignRole($role));

            $site->parties()->withPivotValue('role', $role->value)->sync($ids);
        }

        $site->unsetRelation('parties');
    }
}
