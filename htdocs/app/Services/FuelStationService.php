<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
use App\Cache\FuelStationCache;
use App\Enums\PartyRole;
use App\Models\FuelStation;
use App\Repositories\FuelStationRepository;
use App\Repositories\PartyRepository;
use App\Repositories\SiteRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class FuelStationService extends Service
{
    public function __construct(
        private FuelStationRepository $stations,
        private SiteRepository $sites,
        private PartyRepository $parties,
    ) {}

    /**
     * @param  array{q?: string, site?: string, party?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = FuelStationCache::PER_PAGE): LengthAwarePaginator
    {
        return FuelStationCache::rememberFirstPage(
            $filters,
            $sort,
            fn () => $this->stations->search($filters, $sort, $perPage),
        );
    }

    /**
     * @return array<string, string>
     */
    public function siteOptions(?FuelStation $station = null, bool $activeOnly = true): array
    {
        return $this->sites->options(activeOnly: $activeOnly, include: $station?->sites->pluck('id')->all());
    }

    /**
     * @return array<string, string>
     */
    public function partyOptions(?FuelStation $station = null): array
    {
        return $this->parties->options(include: $station?->party_id);
    }

    /**
     * @return array<string, string>
     */
    public function operatorOptions(): array
    {
        return $this->parties->options(PartyRole::Fuel);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): FuelStation
    {
        $siteIds = Arr::pull($attributes, 'site_ids', []);

        /** @var FuelStation */
        return $this->transaction(function () use ($attributes, $siteIds): FuelStation {
            /** @var FuelStation $station */
            $station = $this->stations->create($attributes);
            $station->sites()->sync($siteIds);
            $station->party->assignRole(PartyRole::Fuel);
            $this->flushCaches();

            return $station;
        });
    }

    /**
     * Deleted sites are not offered in the form, so their existing links are kept.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(FuelStation $station, array $attributes): FuelStation
    {
        $siteIds = Arr::pull($attributes, 'site_ids', []);

        /** @var FuelStation */
        return $this->transaction(function () use ($station, $attributes, $siteIds): FuelStation {
            /** @var FuelStation $updated */
            $updated = $this->stations->update($station, $attributes);
            $trashed = $updated->sites()->onlyTrashed()->pluck('sites.id')->all();
            $updated->sites()->sync([...$siteIds, ...$trashed]);
            $updated->party->assignRole(PartyRole::Fuel);
            $this->flushCaches();

            return $updated;
        });
    }

    public function delete(FuelStation $station): void
    {
        $this->transaction(function () use ($station): void {
            $this->stations->delete($station);
            $this->flushCaches();
        });
    }

    private function flushCaches(): void
    {
        FuelStationCache::flush();
        FormOptionsCache::flush(FormOptionsCache::FUEL_STATIONS, FormOptionsCache::PARTIES);
    }
}
