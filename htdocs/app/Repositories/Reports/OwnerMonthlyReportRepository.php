<?php

namespace App\Repositories\Reports;

use App\Enums\InvoiceDirection;
use App\Enums\PartyRole;
use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\MachineWork;
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
     * The owner's machine earnings (hours × hourly rate) for one vehicle, one row per site.
     *
     * @return Collection<int, object{site_id: string, site_name: string, tickets: string, hours: string, amount: string}>
     */
    public function machineTotals(string $partyId, string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return MachineWork::query()
            ->toBase()
            ->join('sites', 'sites.id', '=', 'machine_works.site_id')
            ->where('machine_works.party_id', $partyId)
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
     * What the party earned as a paid operator on any machine, one row per machine and site.
     *
     * @return Collection<int, object{plate: string, site_name: string, tickets: string, hours: string, amount: string}>
     */
    public function operatorTotals(string $partyId, Carbon $from, Carbon $to): Collection
    {
        return MachineWork::query()
            ->toBase()
            ->join('vehicles', 'vehicles.id', '=', 'machine_works.vehicle_id')
            ->join('sites', 'sites.id', '=', 'machine_works.site_id')
            ->where('machine_works.operator_id', $partyId)
            ->whereNotNull('machine_works.operator_amount')
            ->where('machine_works.worked_on', '>=', $from->toDateString())
            ->where('machine_works.worked_on', '<', $to->toDateString())
            ->whereNull('machine_works.deleted_at')
            ->groupBy('vehicles.plate', 'sites.name')
            ->orderBy('vehicles.plate')
            ->orderBy('sites.name')
            ->get([
                'vehicles.plate',
                'sites.name as site_name',
                DB::raw('COUNT(*) as tickets'),
                DB::raw('SUM(machine_works.hours) as hours'),
                DB::raw('SUM(machine_works.operator_amount) as amount'),
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
     * Live plate-sale invoices for one owner snapshot on one vehicle in [from, to).
     *
     * @return Collection<int, object{id: string, issued_on: string, invoice_no: string, description: string|null, total: string}>
     */
    public function materialTotals(string $partyId, string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return Invoice::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('vehicle_id', $vehicleId)
            ->where('direction', InvoiceDirection::Sale->value)
            ->where('is_draft', false)
            ->where('issued_on', '>=', $from->toDateString())
            ->where('issued_on', '<', $to->toDateString())
            ->whereNull('deleted_at')
            ->orderBy('issued_on')
            ->orderBy('invoice_no')
            ->get(['id', 'issued_on', 'invoice_no', 'description', 'total']);
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
     * Transfer carry-over lines on this owner's plate in [from, to): the previous owner's closing and the new owner's opening.
     *
     * @return Collection<int, object{occurred_on: string, description: string, debit: string, credit: string}>
     */
    public function transferLines(string $partyId, string $vehicleId, Carbon $from, Carbon $to): Collection
    {
        return LedgerEntry::query()
            ->toBase()
            ->where('source_type', 'vehicle_ownership')
            ->where('party_id', $partyId)
            ->where('vehicle_id', $vehicleId)
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $to->toDateString())
            ->orderBy('occurred_on')
            ->orderBy('created_at')
            ->get(['occurred_on', 'description', 'debit', 'credit']);
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
     * Owner carryover (debit − credit) before the period start, from the same sources as the period rows.
     *
     * Not read from the ledger: paid fines are never posted there, and an owner that is also
     * a dump operator or fuel party would pull in haul / fuel lines that are not owner movements.
     */
    public function carryoverBalance(string $partyId, Carbon $before): float
    {
        $beforeDate = $before->toDateString();

        $hauls = (float) Haul::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('hauled_at', '<', $before)
            ->whereNull('deleted_at')
            ->sum('price');

        $machineWorks = (float) MachineWork::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('worked_on', '<', $beforeDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $operatorWorks = (float) MachineWork::query()
            ->toBase()
            ->where('operator_id', $partyId)
            ->where('worked_on', '<', $beforeDate)
            ->whereNull('deleted_at')
            ->sum('operator_amount');

        $receivables = (float) VehicleReceivable::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('occurred_on', '<', $beforeDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $fuel = (float) FuelReceipt::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('issued_on', '<', $beforeDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $material = (float) Invoice::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->whereNotNull('vehicle_id')
            ->where('direction', InvoiceDirection::Sale->value)
            ->where('is_draft', false)
            ->where('issued_on', '<', $beforeDate)
            ->whereNull('deleted_at')
            ->sum('total');

        $payments = (float) VehiclePayment::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('occurred_on', '<', $beforeDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $fines = (float) DB::table('vehicle_fines')
            ->where('party_id', $partyId)
            ->where('status', 'paid')
            ->where('paid_on', '<', $beforeDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $ownerPayments = (float) PartyPayment::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('occurred_on', '<', $beforeDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $transfers = (float) LedgerEntry::query()
            ->toBase()
            ->where('source_type', 'vehicle_ownership')
            ->where('party_id', $partyId)
            ->where('occurred_on', '<', $beforeDate)
            ->sum(DB::raw('debit - credit'));

        return round($fuel + $material + $payments + $fines + $ownerPayments + $transfers - $hauls - $machineWorks - $operatorWorks - $receivables, 2);
    }

    /**
     * @return array{trips: int, liters: float, receipts: int, haul_tickets: int, machine_hours: float, machine_tickets: int}
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

        $machine = MachineWork::query()
            ->toBase()
            ->where('party_id', $partyId)
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

        $machineIds = MachineWork::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->where('worked_on', '>=', $from->toDateString())
            ->where('worked_on', '<', $to->toDateString())
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

        $materialIds = Invoice::query()
            ->toBase()
            ->where('party_id', $partyId)
            ->whereNotNull('vehicle_id')
            ->where('direction', InvoiceDirection::Sale->value)
            ->where('is_draft', false)
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

        $transferIds = LedgerEntry::query()
            ->toBase()
            ->where('source_type', 'vehicle_ownership')
            ->where('party_id', $partyId)
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $to->toDateString())
            ->distinct()
            ->pluck('vehicle_id');

        return $haulIds
            ->merge($machineIds)
            ->merge($transferIds)
            ->merge($fuelIds)
            ->merge($materialIds)
            ->merge($paymentIds)
            ->merge($receivableIds)
            ->merge($fineIds)
            ->unique()
            ->values();
    }
}
