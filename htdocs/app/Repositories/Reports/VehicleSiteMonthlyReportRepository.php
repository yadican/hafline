<?php

namespace App\Repositories\Reports;

use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\Site;
use App\Models\Vehicle;
use App\Repositories\SiteRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VehicleSiteMonthlyReportRepository
{
    public function __construct(private SiteRepository $sites) {}

    /**
     * @return array<string, string>
     */
    public function sites(): array
    {
        return $this->sites->options();
    }

    public function findVehicle(string $vehicleId): ?Vehicle
    {
        return Vehicle::withTrashed()->find($vehicleId);
    }

    public function findSite(string $siteId): ?Site
    {
        return Site::withTrashed()->find($siteId);
    }

    /**
     * Haul aggregates for one vehicle at one site in a half-open date window [from, to), one row per route.
     *
     * @return Collection<int, object{route_id: string, site_name: string, dump_site_name: string, trips: string, amount: string}>
     */
    public function haulTotals(string $vehicleId, string $siteId, Carbon $from, Carbon $to): Collection
    {
        return Haul::query()
            ->toBase()
            ->join('sites', 'sites.id', '=', 'hauls.site_id')
            ->join('dump_sites', 'dump_sites.id', '=', 'hauls.dump_site_id')
            ->where('hauls.vehicle_id', $vehicleId)
            ->where('hauls.site_id', $siteId)
            ->where('hauls.hauled_at', '>=', $from)
            ->where('hauls.hauled_at', '<', $to)
            ->whereNull('hauls.deleted_at')
            ->groupBy('hauls.route_id', 'sites.name', 'dump_sites.name')
            ->orderBy('sites.name')
            ->orderBy('dump_sites.name')
            ->get([
                'hauls.route_id',
                'sites.name as site_name',
                'dump_sites.name as dump_site_name',
                DB::raw('COUNT(*) as trips'),
                DB::raw('SUM(hauls.price) as amount'),
            ]);
    }

    /**
     * Fuel aggregates for one vehicle at one site in a half-open date window [from, to), one row per station.
     *
     * Receipts tagged with the site are included; untagged receipts from stations linked to the site
     * are included to match the legacy station→site association.
     *
     * @return Collection<int, object{fuel_station_id: string, station_name: string, liters: string, amount: string, receipts: string}>
     */
    public function fuelTotals(string $vehicleId, string $siteId, Carbon $from, Carbon $to): Collection
    {
        return FuelReceipt::query()
            ->toBase()
            ->join('fuel_stations', 'fuel_stations.id', '=', 'fuel_receipts.fuel_station_id')
            ->where('fuel_receipts.vehicle_id', $vehicleId)
            ->where('fuel_receipts.issued_on', '>=', $from->toDateString())
            ->where('fuel_receipts.issued_on', '<', $to->toDateString())
            ->whereNull('fuel_receipts.deleted_at')
            ->where(function ($query) use ($siteId): void {
                $query->where('fuel_receipts.site_id', $siteId)
                    ->orWhere(function ($untagged) use ($siteId): void {
                        $untagged->whereNull('fuel_receipts.site_id')
                            ->whereExists(function ($sub) use ($siteId): void {
                                $sub->selectRaw('1')
                                    ->from('fuel_station_sites')
                                    ->whereColumn('fuel_station_sites.fuel_station_id', 'fuel_receipts.fuel_station_id')
                                    ->where('fuel_station_sites.site_id', $siteId);
                            });
                    });
            })
            ->groupBy('fuel_receipts.fuel_station_id', 'fuel_stations.name')
            ->orderBy('fuel_stations.name')
            ->get([
                'fuel_receipts.fuel_station_id',
                'fuel_stations.name as station_name',
                DB::raw('SUM(fuel_receipts.quantity) as liters'),
                DB::raw('SUM(fuel_receipts.amount) as amount'),
                DB::raw('COUNT(*) as receipts'),
            ]);
    }

    /**
     * @return array{trips: int, liters: float, receipts: int, haul_tickets: int}
     */
    public function footerStats(string $vehicleId, string $siteId, Carbon $from, Carbon $to): array
    {
        $trips = (int) Haul::query()
            ->where('vehicle_id', $vehicleId)
            ->where('site_id', $siteId)
            ->where('hauled_at', '>=', $from)
            ->where('hauled_at', '<', $to)
            ->count();

        $fuel = FuelReceipt::query()
            ->toBase()
            ->where('vehicle_id', $vehicleId)
            ->where('issued_on', '>=', $from->toDateString())
            ->where('issued_on', '<', $to->toDateString())
            ->whereNull('deleted_at')
            ->where(function ($query) use ($siteId): void {
                $query->where('site_id', $siteId)
                    ->orWhere(function ($untagged) use ($siteId): void {
                        $untagged->whereNull('site_id')
                            ->whereExists(function ($sub) use ($siteId): void {
                                $sub->selectRaw('1')
                                    ->from('fuel_station_sites')
                                    ->whereColumn('fuel_station_sites.fuel_station_id', 'fuel_receipts.fuel_station_id')
                                    ->where('fuel_station_sites.site_id', $siteId);
                            });
                    });
            })
            ->selectRaw('COALESCE(SUM(quantity), 0) as liters, COUNT(*) as receipts, COALESCE(SUM(haul_ticket_count), 0) as haul_tickets')
            ->first();

        return [
            'trips' => $trips,
            'liters' => round((float) ($fuel->liters ?? 0), 2),
            'receipts' => (int) ($fuel->receipts ?? 0),
            'haul_tickets' => (int) ($fuel->haul_tickets ?? 0),
        ];
    }
}
