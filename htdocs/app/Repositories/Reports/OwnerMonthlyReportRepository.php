<?php

namespace App\Repositories\Reports;

use App\Enums\PartyRole;
use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\PartyPayment;
use App\Models\Vehicle;
use App\Models\VehiclePayment;
use App\Models\VehicleReceivable;
use App\Repositories\PartyRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OwnerMonthlyReportRepository
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

    /**
     * Vehicles that had owner-scoped activity in the month, plate-sorted.
     *
     * @return Collection<int, Vehicle>
     */
    public function activeVehicles(string $partyId, Carbon $from, Carbon $to): Collection
    {
        $ids = $this->activeVehicleIds($partyId, $from, $to);

        if ($ids->isEmpty()) {
            return collect();
        }

        return Vehicle::withTrashed()
            ->whereIn('id', $ids)
            ->orderBy('plate')
            ->get();
    }

    /**
     * @return Collection<int, object{route_id: string, site_name: string, dump_site_name: string, trips: string, amount: string}>
     */
    public function haulTotals(string $partyId, string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return Haul::query()
            ->toBase()
            ->join('sites', 'sites.id', '=', 'hauls.site_id')
            ->join('dump_sites', 'dump_sites.id', '=', 'hauls.dump_site_id')
            ->where('hauls.party_id', $partyId)
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
     * @return Collection<int, object{fuel_station_id: string, station_name: string, liters: string, amount: string, receipts: string}>
     */
    public function fuelTotals(string $partyId, string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return FuelReceipt::query()
            ->toBase()
            ->join('fuel_stations', 'fuel_stations.id', '=', 'fuel_receipts.fuel_station_id')
            ->where('fuel_receipts.party_id', $partyId)
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
    public function payments(string $partyId, string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return VehiclePayment::query()
            ->toBase()
            ->where('party_id', $partyId)
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
    public function receivables(string $partyId, string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return VehicleReceivable::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('vehicle_id', $vehicleId)
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $to->toDateString())
            ->whereNull('deleted_at')
            ->orderBy('occurred_on')
            ->orderBy('description')
            ->get(['id', 'occurred_on', 'description', 'amount']);
    }

    /**
     * @return Collection<int, object{id: string, paid_on: string, description: string|null, amount: string}>
     */
    public function paidFines(string $partyId, string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return DB::table('vehicle_fines')
            ->where('party_id', $partyId)
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
     * @return Collection<int, object{id: string, occurred_on: string, description: string|null, amount: string}>
     */
    public function ownerPayments(string $partyId, Carbon $from, Carbon $to): Collection
    {
        return PartyPayment::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $to->toDateString())
            ->whereNull('deleted_at')
            ->orderBy('occurred_on')
            ->orderBy('description')
            ->get(['id', 'occurred_on', 'description', 'amount']);
    }

    /**
     * Owner carryover: SAHİP vehicle movements plus sahip ödemeleri (party_payment).
     */
    public function carryoverBalance(string $partyId, Carbon $before): float
    {
        $balance = LedgerEntry::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('occurred_on', '<', $before->toDateString())
            ->whereIn('source_type', [
                'haul',
                'fuel_receipt',
                'vehicle_payment',
                'vehicle_receivable',
                'party_payment',
            ])
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->value('balance');

        return round((float) $balance, 2);
    }

    /**
     * @return array{trips: int, liters: float, receipts: int, haul_tickets: int}
     */
    public function vehicleStats(string $partyId, string $vehicleId, Carbon $from, Carbon $to): array
    {
        $trips = (int) Haul::query()
            ->where('party_id', $partyId)
            ->where('vehicle_id', $vehicleId)
            ->where('hauled_at', '>=', $from)
            ->where('hauled_at', '<', $to)
            ->count();

        $fuel = FuelReceipt::query()
            ->toBase()
            ->where('party_id', $partyId)
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

    /**
     * @return Collection<int, string>
     */
    private function activeVehicleIds(string $partyId, Carbon $from, Carbon $to): Collection
    {
        $haulIds = Haul::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('hauled_at', '>=', $from)
            ->where('hauled_at', '<', $to)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('vehicle_id');

        $fuelIds = FuelReceipt::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('issued_on', '>=', $from->toDateString())
            ->where('issued_on', '<', $to->toDateString())
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('vehicle_id');

        $paymentIds = VehiclePayment::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $to->toDateString())
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('vehicle_id');

        $receivableIds = VehicleReceivable::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $to->toDateString())
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('vehicle_id');

        $fineIds = DB::table('vehicle_fines')
            ->where('party_id', $partyId)
            ->where('status', 'paid')
            ->where('paid_on', '>=', $from->toDateString())
            ->where('paid_on', '<', $to->toDateString())
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('vehicle_id');

        return $haulIds
            ->merge($fuelIds)
            ->merge($paymentIds)
            ->merge($receivableIds)
            ->merge($fineIds)
            ->unique()
            ->values();
    }
}
