<?php

namespace App\Repositories\Reports;

use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\LedgerEntry;
use App\Models\Vehicle;
use App\Models\VehiclePayment;
use App\Models\VehicleReceivable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VehicleMonthlyReportRepository
{
    public function findVehicle(string $vehicleId): ?Vehicle
    {
        return Vehicle::withTrashed()->find($vehicleId);
    }

    /**
     * Haul aggregates for one vehicle in a month, one row per route.
     *
     * @return Collection<int, object{route_id: string, site_name: string, dump_site_name: string, trips: string, amount: string}>
     */
    public function haulTotals(string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return Haul::query()
            ->toBase()
            ->join('sites', 'sites.id', '=', 'hauls.site_id')
            ->join('dump_sites', 'dump_sites.id', '=', 'hauls.dump_site_id')
            ->where('hauls.vehicle_id', $vehicleId)
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
     * Fuel aggregates for one vehicle in a month, one row per station.
     *
     * @return Collection<int, object{fuel_station_id: string, station_name: string, liters: string, amount: string, receipts: string}>
     */
    public function fuelTotals(string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return FuelReceipt::query()
            ->toBase()
            ->join('fuel_stations', 'fuel_stations.id', '=', 'fuel_receipts.fuel_station_id')
            ->where('fuel_receipts.vehicle_id', $vehicleId)
            ->where('fuel_receipts.issued_on', '>=', $from->toDateString())
            ->where('fuel_receipts.issued_on', '<', $to->toDateString())
            ->whereNull('fuel_receipts.deleted_at')
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
     * @return Collection<int, object{id: string, occurred_on: string, description: string|null, amount: string}>
     */
    public function payments(string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return VehiclePayment::query()
            ->toBase()
            ->where('vehicle_id', $vehicleId)
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $to->toDateString())
            ->whereNull('deleted_at')
            ->orderBy('occurred_on')
            ->orderBy('description')
            ->get(['id', 'occurred_on', 'description', 'amount']);
    }

    /**
     * @return Collection<int, object{id: string, occurred_on: string, description: string|null, amount: string}>
     */
    public function receivables(string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return VehicleReceivable::query()
            ->toBase()
            ->where('vehicle_id', $vehicleId)
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $to->toDateString())
            ->whereNull('deleted_at')
            ->orderBy('occurred_on')
            ->orderBy('description')
            ->get(['id', 'occurred_on', 'description', 'amount']);
    }

    /**
     * Paid fines posted on paid_on within the month.
     *
     * @return Collection<int, object{id: string, paid_on: string, description: string|null, amount: string}>
     */
    public function paidFines(string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return DB::table('vehicle_fines')
            ->where('vehicle_id', $vehicleId)
            ->where('status', 'paid')
            ->where('paid_on', '>=', $from->toDateString())
            ->where('paid_on', '<', $to->toDateString())
            ->whereNull('deleted_at')
            ->orderBy('paid_on')
            ->orderBy('description')
            ->get(['id', 'paid_on', 'description', 'amount']);
    }

    /**
     * Net ledger balance (debit − credit) before the month start for this vehicle.
     */
    public function carryoverBalance(string $vehicleId, Carbon $before): float
    {
        $balance = LedgerEntry::query()
            ->toBase()
            ->where('vehicle_id', $vehicleId)
            ->where('occurred_on', '<', $before->toDateString())
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->value('balance');

        return round((float) $balance, 2);
    }

    /**
     * @return array{trips: int, liters: float, receipts: int, haul_tickets: int}
     */
    public function footerStats(string $vehicleId, Carbon $from, Carbon $to): array
    {
        $trips = (int) Haul::query()
            ->where('vehicle_id', $vehicleId)
            ->where('hauled_at', '>=', $from)
            ->where('hauled_at', '<', $to)
            ->count();

        $fuel = FuelReceipt::query()
            ->toBase()
            ->where('vehicle_id', $vehicleId)
            ->where('issued_on', '>=', $from->toDateString())
            ->where('issued_on', '<', $to->toDateString())
            ->whereNull('deleted_at')
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
