<?php

namespace App\Repositories;

use App\Models\DumpSite;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Models\VehicleOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class HaulLookupRepository
{
    /**
     * @return array<string, string>
     */
    public function sites(bool $activeOnly = false, ?string $include = null): array
    {
        return Site::query()
            ->when($activeOnly, fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('active', true)->orWhere('id', $include),
            ))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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
    public function dumpSitesForSite(?string $siteId): array
    {
        if (! $siteId || ! Str::isUuid($siteId)) {
            return [];
        }

        return DumpSite::query()
            ->where('active', true)
            ->whereHas('routes', fn (Builder $route) => $route->where('site_id', $siteId)->where('active', true))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function owners(): array
    {
        return VehicleOwner::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (VehicleOwner $owner) => [$owner->id => $owner->full_name])
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
            ->with(['owner', 'vehicleClass'])
            ->where('active', true)
            ->whereLike('plate_key', $like)
            ->orderBy('plate')
            ->limit($limit)
            ->get();
    }
}
