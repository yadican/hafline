<?php

namespace App\Repositories\Reports;

use App\Enums\InvoiceDirection;
use App\Enums\PartyRole;
use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Vehicle;
use App\Repositories\PartyRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PeriodicMovementReportRepository
{
    public function __construct(private PartyRepository $parties) {}

    /**
     * @return array<string, string>
     */
    public function ownerOptions(?string $include = null): array
    {
        return $this->parties->options(PartyRole::VehicleOwner, $include);
    }

    public function findOwner(string $partyId): ?Party
    {
        return Party::withTrashed()->find($partyId);
    }

    public function findVehicleByPlate(string $plate): ?Vehicle
    {
        return Vehicle::findByPlate($plate);
    }

    /**
     * Haul totals by route for one vehicle in [from, toExclusive).
     *
     * @return Collection<int, object{route_id: string, site_name: string, dump_site_name: string, trips: string, tonnage: string}>
     */
    public function vehicleHaulTotals(string $vehicleId, Carbon $from, Carbon $toExclusive): Collection
    {
        return Haul::query()
            ->toBase()
            ->join('sites', 'sites.id', '=', 'hauls.site_id')
            ->join('dump_sites', 'dump_sites.id', '=', 'hauls.dump_site_id')
            ->where('hauls.vehicle_id', $vehicleId)
            ->where('hauls.hauled_at', '>=', $from)
            ->where('hauls.hauled_at', '<', $toExclusive)
            ->whereNull('hauls.deleted_at')
            ->groupBy('hauls.route_id', 'hauls.site_id', 'hauls.dump_site_id', 'sites.name', 'dump_sites.name')
            ->orderBy('sites.name')
            ->orderBy('dump_sites.name')
            ->get([
                'hauls.route_id',
                'sites.name as site_name',
                'dump_sites.name as dump_site_name',
                DB::raw('COUNT(*) as trips'),
                DB::raw('COALESCE(SUM(hauls.tonnage), 0) as tonnage'),
            ]);
    }

    /**
     * Fuel liters by station for one vehicle in [from, toExclusive).
     *
     * @return Collection<int, object{fuel_station_id: string, station_name: string, liters: string}>
     */
    public function vehicleFuelTotals(string $vehicleId, Carbon $from, Carbon $toExclusive): Collection
    {
        return FuelReceipt::query()
            ->toBase()
            ->join('fuel_stations', 'fuel_stations.id', '=', 'fuel_receipts.fuel_station_id')
            ->where('fuel_receipts.vehicle_id', $vehicleId)
            ->where('fuel_receipts.issued_on', '>=', $from->toDateString())
            ->where('fuel_receipts.issued_on', '<', $toExclusive->toDateString())
            ->whereNull('fuel_receipts.deleted_at')
            ->groupBy('fuel_receipts.fuel_station_id', 'fuel_stations.name')
            ->orderBy('fuel_stations.name')
            ->get([
                'fuel_receipts.fuel_station_id',
                'fuel_stations.name as station_name',
                DB::raw('COALESCE(SUM(fuel_receipts.quantity), 0) as liters'),
            ]);
    }

    /**
     * Live plate-sale totals for one vehicle in [from, toExclusive).
     *
     * @return Collection<int, object{id: string, invoice_no: string, description: string|null, total: string}>
     */
    public function vehicleMaterialTotals(string $vehicleId, Carbon $from, Carbon $toExclusive): Collection
    {
        return Invoice::query()
            ->toBase()
            ->where('vehicle_id', $vehicleId)
            ->where('direction', InvoiceDirection::Sale->value)
            ->where('is_draft', false)
            ->where('issued_on', '>=', $from->toDateString())
            ->where('issued_on', '<', $toExclusive->toDateString())
            ->whereNull('deleted_at')
            ->orderBy('issued_on')
            ->orderBy('invoice_no')
            ->get(['id', 'invoice_no', 'description', 'total']);
    }

    /**
     * Owner-scoped haul totals: GROUP BY vehicle + route in one query.
     *
     * @return Collection<int, object{vehicle_id: string, plate: string, route_id: string, site_name: string, dump_site_name: string, trips: string, tonnage: string}>
     */
    public function ownerHaulTotals(string $partyId, Carbon $from, Carbon $toExclusive): Collection
    {
        return Haul::query()
            ->toBase()
            ->join('vehicles', 'vehicles.id', '=', 'hauls.vehicle_id')
            ->join('sites', 'sites.id', '=', 'hauls.site_id')
            ->join('dump_sites', 'dump_sites.id', '=', 'hauls.dump_site_id')
            ->where('hauls.party_id', $partyId)
            ->where('hauls.hauled_at', '>=', $from)
            ->where('hauls.hauled_at', '<', $toExclusive)
            ->whereNull('hauls.deleted_at')
            ->groupBy(
                'hauls.vehicle_id',
                'vehicles.plate',
                'hauls.route_id',
                'hauls.site_id',
                'hauls.dump_site_id',
                'sites.name',
                'dump_sites.name',
            )
            ->orderBy('vehicles.plate')
            ->orderBy('sites.name')
            ->orderBy('dump_sites.name')
            ->get([
                'hauls.vehicle_id',
                'vehicles.plate',
                'hauls.route_id',
                'sites.name as site_name',
                'dump_sites.name as dump_site_name',
                DB::raw('COUNT(*) as trips'),
                DB::raw('COALESCE(SUM(hauls.tonnage), 0) as tonnage'),
            ]);
    }

    /**
     * Owner-scoped fuel totals: GROUP BY vehicle + station in one query.
     *
     * @return Collection<int, object{vehicle_id: string, plate: string, fuel_station_id: string, station_name: string, liters: string}>
     */
    public function ownerFuelTotals(string $partyId, Carbon $from, Carbon $toExclusive): Collection
    {
        return FuelReceipt::query()
            ->toBase()
            ->join('vehicles', 'vehicles.id', '=', 'fuel_receipts.vehicle_id')
            ->join('fuel_stations', 'fuel_stations.id', '=', 'fuel_receipts.fuel_station_id')
            ->where('fuel_receipts.party_id', $partyId)
            ->where('fuel_receipts.issued_on', '>=', $from->toDateString())
            ->where('fuel_receipts.issued_on', '<', $toExclusive->toDateString())
            ->whereNull('fuel_receipts.deleted_at')
            ->groupBy(
                'fuel_receipts.vehicle_id',
                'vehicles.plate',
                'fuel_receipts.fuel_station_id',
                'fuel_stations.name',
            )
            ->orderBy('vehicles.plate')
            ->orderBy('fuel_stations.name')
            ->get([
                'fuel_receipts.vehicle_id',
                'vehicles.plate',
                'fuel_receipts.fuel_station_id',
                'fuel_stations.name as station_name',
                DB::raw('COALESCE(SUM(fuel_receipts.quantity), 0) as liters'),
            ]);
    }

    /**
     * Owner-scoped live plate-sale totals: one row per invoice.
     *
     * @return Collection<int, object{vehicle_id: string, plate: string, id: string, invoice_no: string, description: string|null, total: string}>
     */
    public function ownerMaterialTotals(string $partyId, Carbon $from, Carbon $toExclusive): Collection
    {
        return Invoice::query()
            ->toBase()
            ->join('vehicles', 'vehicles.id', '=', 'invoices.vehicle_id')
            ->where('invoices.party_id', $partyId)
            ->whereNotNull('invoices.vehicle_id')
            ->where('invoices.direction', InvoiceDirection::Sale->value)
            ->where('invoices.is_draft', false)
            ->where('invoices.issued_on', '>=', $from->toDateString())
            ->where('invoices.issued_on', '<', $toExclusive->toDateString())
            ->whereNull('invoices.deleted_at')
            ->orderBy('vehicles.plate')
            ->orderBy('invoices.issued_on')
            ->orderBy('invoices.invoice_no')
            ->get([
                'invoices.vehicle_id',
                'vehicles.plate',
                'invoices.id',
                'invoices.invoice_no',
                'invoices.description',
                'invoices.total',
            ]);
    }
}
