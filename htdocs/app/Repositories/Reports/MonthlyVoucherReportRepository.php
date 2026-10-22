<?php

namespace App\Repositories\Reports;

use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\VehicleClass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyVoucherReportRepository
{
    /**
     * @return array<string, string>
     */
    public function routes(): array
    {
        return HaulRoute::query()
            ->with(['site', 'dumpSite'])
            ->join('sites', 'sites.id', '=', 'haul_routes.site_id')
            ->join('dump_sites', 'dump_sites.id', '=', 'haul_routes.dump_site_id')
            ->orderBy('sites.name')
            ->orderBy('dump_sites.name')
            ->select('haul_routes.*')
            ->get()
            ->mapWithKeys(fn (HaulRoute $route): array => [
                $route->id => trim(($route->site?->name ?? '').' – '.($route->dumpSite?->name ?? '')),
            ])
            ->all();
    }

    /**
     * Route options labeled dump-site first (for dump_site orientation).
     *
     * @return array<string, string>
     */
    public function routesDumpFirst(): array
    {
        return HaulRoute::query()
            ->with(['site', 'dumpSite'])
            ->join('sites', 'sites.id', '=', 'haul_routes.site_id')
            ->join('dump_sites', 'dump_sites.id', '=', 'haul_routes.dump_site_id')
            ->orderBy('dump_sites.name')
            ->orderBy('sites.name')
            ->select('haul_routes.*')
            ->get()
            ->mapWithKeys(fn (HaulRoute $route): array => [
                $route->id => trim(($route->dumpSite?->name ?? '').' – '.($route->site?->name ?? '')),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function vehicleClasses(): array
    {
        return VehicleClass::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function findRoute(string $routeId): ?HaulRoute
    {
        return HaulRoute::withTrashed()->with(['site', 'dumpSite'])->find($routeId);
    }

    /**
     * Trip counts per vehicle per calendar day for one route in [from, toExclusive).
     *
     * @return Collection<int, object{vehicle_id: string, plate: string, class_name: ?string, haul_day: string, trips: int}>
     */
    public function dayCounts(string $routeId, Carbon $from, Carbon $toExclusive, ?string $vehicleClassId = null): Collection
    {
        $dayExpr = $this->dayExpression('hauls.hauled_at');

        return Haul::query()
            ->toBase()
            ->join('vehicles', 'vehicles.id', '=', 'hauls.vehicle_id')
            ->leftJoin('vehicle_classes', 'vehicle_classes.id', '=', 'vehicles.vehicle_class_id')
            ->where('hauls.route_id', $routeId)
            ->where('hauls.hauled_at', '>=', $from)
            ->where('hauls.hauled_at', '<', $toExclusive)
            ->whereNull('hauls.deleted_at')
            ->when($vehicleClassId, fn ($query, string $class) => $query->where('vehicles.vehicle_class_id', $class))
            ->groupBy('hauls.vehicle_id', 'vehicles.plate', 'vehicle_classes.name', DB::raw($dayExpr))
            ->orderBy('vehicle_classes.name')
            ->orderBy('vehicles.plate')
            ->orderBy(DB::raw($dayExpr))
            ->get([
                'hauls.vehicle_id',
                'vehicles.plate',
                'vehicle_classes.name as class_name',
                DB::raw("{$dayExpr} as haul_day"),
                DB::raw('COUNT(*) as trips'),
            ]);
    }

    private function dayExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', {$column})",
            default => "DATE({$column})",
        };
    }
}
