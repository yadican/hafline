<?php

namespace App\Repositories\Reports;

use App\Models\Haul;
use App\Models\Site;
use App\Repositories\SiteRepository;
use App\Repositories\VehicleClassRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SiteExitReportRepository
{
    public function __construct(
        private SiteRepository $sites,
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
    public function vehicleClasses(): array
    {
        return $this->classes->options();
    }

    public function findSite(string $siteId): ?Site
    {
        return Site::withTrashed()->find($siteId);
    }

    /**
     * Trip counts for one site in a half-open date window [from, to), keyed for pivot assembly.
     *
     * @return Collection<int, object{vehicle_id: string, route_id: string, plate: string, dump_site_id: string, dump_name: string, trips: int}>
     */
    public function tripCounts(string $siteId, Carbon $from, Carbon $to, ?string $vehicleClassId = null): Collection
    {
        return Haul::query()
            ->toBase()
            ->join('vehicles', 'vehicles.id', '=', 'hauls.vehicle_id')
            ->join('dump_sites', 'dump_sites.id', '=', 'hauls.dump_site_id')
            ->where('hauls.site_id', $siteId)
            ->where('hauls.hauled_at', '>=', $from)
            ->where('hauls.hauled_at', '<', $to)
            ->whereNull('hauls.deleted_at')
            ->when($vehicleClassId, fn ($query, string $class) => $query->where('vehicles.vehicle_class_id', $class))
            ->groupBy('hauls.vehicle_id', 'hauls.route_id', 'vehicles.plate', 'hauls.dump_site_id', 'dump_sites.name')
            ->orderBy('vehicles.plate')
            ->orderBy('dump_sites.name')
            ->get([
                'hauls.vehicle_id',
                'hauls.route_id',
                'vehicles.plate',
                'hauls.dump_site_id',
                'dump_sites.name as dump_name',
                DB::raw('COUNT(*) as trips'),
            ]);
    }
}
