<?php

namespace App\Repositories\Reports;

use App\Models\DumpSite;
use App\Models\Haul;
use App\Models\VehicleClass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DumpSiteReportRepository
{
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
    public function vehicleClasses(): array
    {
        return VehicleClass::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function findDumpSite(string $dumpSiteId): ?DumpSite
    {
        return DumpSite::withTrashed()->find($dumpSiteId);
    }

    /**
     * Trip counts for one dump site in a month, keyed for pivot assembly.
     *
     * @return Collection<int, object{vehicle_id: string, route_id: string, plate: string, site_id: string, site_name: string, trips: int}>
     */
    public function tripCounts(string $dumpSiteId, Carbon $from, Carbon $to, ?string $vehicleClassId = null): Collection
    {
        return Haul::query()
            ->toBase()
            ->join('vehicles', 'vehicles.id', '=', 'hauls.vehicle_id')
            ->join('sites', 'sites.id', '=', 'hauls.site_id')
            ->where('hauls.dump_site_id', $dumpSiteId)
            ->where('hauls.hauled_at', '>=', $from)
            ->where('hauls.hauled_at', '<', $to)
            ->whereNull('hauls.deleted_at')
            ->when($vehicleClassId, fn ($query, string $class) => $query->where('vehicles.vehicle_class_id', $class))
            ->groupBy('hauls.vehicle_id', 'hauls.route_id', 'vehicles.plate', 'hauls.site_id', 'sites.name')
            ->orderBy('vehicles.plate')
            ->orderBy('sites.name')
            ->get([
                'hauls.vehicle_id',
                'hauls.route_id',
                'vehicles.plate',
                'hauls.site_id',
                'sites.name as site_name',
                DB::raw('COUNT(*) as trips'),
            ]);
    }
}
