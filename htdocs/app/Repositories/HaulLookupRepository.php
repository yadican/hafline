<?php

namespace App\Repositories;

use App\Enums\PartyRole;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;

class HaulLookupRepository
{
    public function __construct(
        private SiteRepository $sites,
        private DumpSiteRepository $dumpSites,
        private PartyRepository $parties,
        private VehicleClassRepository $classes,
    ) {}

    /**
     * @return array<string, string>
     */
    public function sites(): array
    {
        return $this->sites->options();
    }

    /**
     * @return array<string, string>
     */
    public function dumpSites(): array
    {
        return $this->dumpSites->options();
    }

    /**
     * @return array<string, string>
     */
    public function owners(): array
    {
        return $this->parties->options(PartyRole::VehicleOwner);
    }

    /**
     * @return array<string, string>
     */
    public function vehicleClasses(): array
    {
        return $this->classes->options();
    }

    /**
     * @return array<string, string>
     */
    public function creators(): array
    {
        return User::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return Collection<int, Vehicle>
     */
    public function vehicles(string $term, int $limit = 10): Collection
    {
        $like = '%'.addcslashes(Vehicle::normalizePlate($term), '%_\\').'%';

        return Vehicle::query()
            ->with(['currentOwnership.party', 'vehicleClass'])
            ->where('active', true)
            ->whereLike('plate_key', $like)
            ->orderBy('plate')
            ->limit($limit)
            ->get();
    }
}
