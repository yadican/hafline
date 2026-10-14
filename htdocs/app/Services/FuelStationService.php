<?php

namespace App\Services;

use App\Models\FuelStation;
use App\Repositories\FuelStationRepository;
use App\Repositories\SiteRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FuelStationService extends Service
{
    public function __construct(
        private FuelStationRepository $stations,
        private SiteRepository $sites,
    ) {}

    /**
     * @param  array{q?: string, site?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->stations->search($filters, $sort, $perPage);
    }

    /**
     * @return array<string, string>
     */
    public function siteOptions(?FuelStation $station = null, bool $activeOnly = true): array
    {
        return $this->sites->options(activeOnly: $activeOnly, include: $station?->site_id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): FuelStation
    {
        /** @var FuelStation */
        return $this->transaction(fn () => $this->stations->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(FuelStation $station, array $attributes): FuelStation
    {
        /** @var FuelStation */
        return $this->transaction(fn () => $this->stations->update($station, $attributes));
    }

    public function delete(FuelStation $station): void
    {
        $this->transaction(fn () => $this->stations->delete($station));
    }
}
