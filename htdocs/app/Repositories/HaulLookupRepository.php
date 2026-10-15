<?php

namespace App\Repositories;

use App\Enums\PartyRole;
use App\Models\DumpSite;
use App\Models\Party;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Database\Eloquent\Collection;

class HaulLookupRepository
{
    /**
     * @return array<string, string>
     */
    public function sites(): array
    {
        return Site::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    public function dumpSites(): array
    {
        return DumpSite::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    public function owners(): array
    {
        return Party::withTrashed()
            ->withRole(PartyRole::VehicleOwner)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function vehicleClasses(): array
    {
        return VehicleClass::query()->orderBy('name')->pluck('name', 'id')->all();
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
