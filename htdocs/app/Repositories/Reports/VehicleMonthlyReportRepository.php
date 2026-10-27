<?php

namespace App\Repositories\Reports;

use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\LedgerEntry;
use App\Models\MachineWork;
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
     * Haul aggregates for one vehicle in a half-open date window [from, to), one row per route.
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
     * Machine work aggregates for one vehicle in a half-open date window [from, to), one row per site.
     * Only the owner's earning (hours × hourly rate); an operator's share is the operator's own account.
     *
     * @return Collection<int, object{site_id: string, site_name: string, tickets: string, hours: string, amount: string}>
     */
    public function machineTotals(string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return MachineWork::query()
            ->toBase()
            ->join('sites', 'sites.id', '=', 'machine_works.site_id')
            ->where('machine_works.vehicle_id', $vehicleId)
            ->where('machine_works.worked_on', '>=', $from->toDateString())
            ->where('machine_works.worked_on', '<', $to->toDateString())
            ->whereNull('machine_works.deleted_at')
            ->groupBy('machine_works.site_id', 'sites.name')
            ->orderBy('sites.name')
            ->get([
                'machine_works.site_id',
                'sites.name as site_name',
                DB::raw('COUNT(*) as tickets'),
                DB::raw('SUM(machine_works.hours) as hours'),
                DB::raw('SUM(machine_works.amount) as amount'),
            ]);
    }

    /**
     * Fuel aggregates for one vehicle in a half-open date window [from, to), one row per station.
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
     * Transfer carry-over lines on this plate in [from, to): the previous owner's closing and the new owner's opening.
     *
     * @return Collection<int, object{occurred_on: string, description: string, debit: string, credit: string}>
     */
    public function transferLines(string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return LedgerEntry::query()
            ->toBase()
            ->where('source_type', 'vehicle_ownership')
            ->where('vehicle_id', $vehicleId)
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $to->toDateString())
            ->orderBy('occurred_on')
            ->orderBy('created_at')
            ->get(['occurred_on', 'description', 'debit', 'credit']);
    }

    /**
     * Paid fines posted on paid_on within the half-open date window [from, to).
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
     * Net balance (debit − credit) before the period start, from the same sources as the period rows.
     *
     * Not read from the ledger: dump fees and market trips carry this vehicle_id but post to other parties.
     */
    public function carryoverBalance(string $vehicleId, Carbon $before): float
    {
        $beforeDate = $before->toDateString();

        $hauls = (float) Haul::query()
            ->toBase()
            ->where('vehicle_id', $vehicleId)
            ->where('hauled_at', '<', $before)
            ->whereNull('deleted_at')
            ->sum('price');

        $machineWorks = (float) MachineWork::query()
            ->toBase()
            ->where('vehicle_id', $vehicleId)
            ->where('worked_on', '<', $beforeDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $receivables = (float) VehicleReceivable::query()
            ->toBase()
            ->where('vehicle_id', $vehicleId)
            ->where('occurred_on', '<', $beforeDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $fuel = (float) FuelReceipt::query()
            ->toBase()
            ->where('vehicle_id', $vehicleId)
            ->where('issued_on', '<', $beforeDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $payments = (float) VehiclePayment::query()
            ->toBase()
            ->where('vehicle_id', $vehicleId)
            ->where('occurred_on', '<', $beforeDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $fines = (float) DB::table('vehicle_fines')
            ->where('vehicle_id', $vehicleId)
            ->where('status', 'paid')
            ->where('paid_on', '<', $beforeDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $transfers = (float) LedgerEntry::query()
            ->toBase()
            ->where('source_type', 'vehicle_ownership')
            ->where('vehicle_id', $vehicleId)
            ->where('occurred_on', '<', $beforeDate)
            ->sum(DB::raw('debit - credit'));

        return round($fuel + $payments + $fines + $transfers - $hauls - $machineWorks - $receivables, 2);
    }

    /**
     * @return array{trips: int, liters: float, receipts: int, haul_tickets: int, machine_hours: float, machine_tickets: int}
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

        $machine = MachineWork::query()
            ->toBase()
            ->where('vehicle_id', $vehicleId)
            ->where('worked_on', '>=', $from->toDateString())
            ->where('worked_on', '<', $to->toDateString())
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(hours), 0) as hours, COUNT(*) as tickets')
            ->first();

        return [
            'trips' => $trips,
            'liters' => round((float) ($fuel->liters ?? 0), 2),
            'receipts' => (int) ($fuel->receipts ?? 0),
            'haul_tickets' => (int) ($fuel->haul_tickets ?? 0),
            'machine_hours' => round((float) ($machine->hours ?? 0), 2),
            'machine_tickets' => (int) ($machine->tickets ?? 0),
        ];
    }
}
