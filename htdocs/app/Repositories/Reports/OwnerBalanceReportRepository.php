<?php

namespace App\Repositories\Reports;

use App\Models\Party;
use App\Repositories\FuelStationRepository;
use App\Repositories\SiteRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OwnerBalanceReportRepository
{
    public function __construct(
        private SiteRepository $sites,
        private FuelStationRepository $stations,
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
    public function fuelStations(): array
    {
        return $this->stations->options();
    }

    /**
     * Per-owner credit/debit totals in a half-open date window [from, toExclusive).
     * Null bounds mean no date filter (all time). SAHİP = snapshot party_id on each row.
     *
     * @return Collection<int, object{
     *     party_id: string,
     *     name: string,
     *     credit_hauls: string,
     *     credit_machine_works: string,
     *     credit_receivables: string,
     *     debit_payments: string,
     *     debit_fuel: string,
     *     debit_fines: string,
     *     credit_transfers: string,
     *     debit_transfers: string,
     *     debit_owner_payments: string
     * }>
     */
    public function ownerTotals(
        ?Carbon $from,
        ?Carbon $toExclusive,
        ?string $siteId = null,
        ?string $fuelStationId = null,
    ): Collection {
        $haulFrom = $from?->copy();
        $haulTo = $toExclusive?->copy();
        $dateFrom = $from?->toDateString();
        $dateTo = $toExclusive?->toDateString();

        $hauls = DB::table('hauls')
            ->select('party_id', DB::raw('COALESCE(SUM(price), 0) as total'))
            ->whereNull('deleted_at')
            ->whereNotNull('party_id')
            ->when($haulFrom, fn ($q) => $q->where('hauled_at', '>=', $haulFrom))
            ->when($haulTo, fn ($q) => $q->where('hauled_at', '<', $haulTo))
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->groupBy('party_id');

        $machineEarnings = DB::table('machine_works')
            ->select('party_id', 'amount')
            ->whereNull('deleted_at')
            ->when($dateFrom, fn ($q) => $q->where('worked_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('worked_on', '<', $dateTo))
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->unionAll(
                DB::table('machine_works')
                    ->select('operator_id as party_id', 'operator_amount as amount')
                    ->whereNull('deleted_at')
                    ->whereNotNull('operator_amount')
                    ->when($dateFrom, fn ($q) => $q->where('worked_on', '>=', $dateFrom))
                    ->when($dateTo, fn ($q) => $q->where('worked_on', '<', $dateTo))
                    ->when($siteId, fn ($q) => $q->where('site_id', $siteId)),
            );

        $machineWorks = DB::query()
            ->fromSub($machineEarnings, 'earnings')
            ->select('party_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->groupBy('party_id');

        $fuel = DB::table('fuel_receipts')
            ->select('party_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereNull('deleted_at')
            ->whereNotNull('party_id')
            ->when($dateFrom, fn ($q) => $q->where('issued_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('issued_on', '<', $dateTo))
            ->when($fuelStationId, fn ($q) => $q->where('fuel_station_id', $fuelStationId))
            ->groupBy('party_id');

        $payments = DB::table('vehicle_payments')
            ->select('party_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereNull('deleted_at')
            ->whereNotNull('party_id')
            ->when($dateFrom, fn ($q) => $q->where('occurred_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('occurred_on', '<', $dateTo))
            ->groupBy('party_id');

        $receivables = DB::table('vehicle_receivables')
            ->select('party_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereNull('deleted_at')
            ->whereNotNull('party_id')
            ->when($dateFrom, fn ($q) => $q->where('occurred_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('occurred_on', '<', $dateTo))
            ->groupBy('party_id');

        $transfers = DB::table('ledger_entries')
            ->select('party_id', DB::raw('COALESCE(SUM(credit), 0) as credit'), DB::raw('COALESCE(SUM(debit), 0) as debit'))
            ->where('source_type', 'vehicle_ownership')
            ->when($dateFrom, fn ($q) => $q->where('occurred_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('occurred_on', '<', $dateTo))
            ->groupBy('party_id');

        $fines = DB::table('vehicle_fines')
            ->select('party_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereNull('deleted_at')
            ->whereNotNull('party_id')
            ->where('status', 'paid')
            ->when($dateFrom, fn ($q) => $q->where('paid_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('paid_on', '<', $dateTo))
            ->groupBy('party_id');

        $ownerPayments = DB::table('party_payments')
            ->select('party_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereNull('deleted_at')
            ->when($dateFrom, fn ($q) => $q->where('occurred_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('occurred_on', '<', $dateTo))
            ->groupBy('party_id');

        return Party::query()
            ->withTrashed()
            ->toBase()
            ->leftJoinSub($hauls, 'hauls_sum', 'hauls_sum.party_id', '=', 'parties.id')
            ->leftJoinSub($machineWorks, 'machine_sum', 'machine_sum.party_id', '=', 'parties.id')
            ->leftJoinSub($fuel, 'fuel_sum', 'fuel_sum.party_id', '=', 'parties.id')
            ->leftJoinSub($payments, 'payments_sum', 'payments_sum.party_id', '=', 'parties.id')
            ->leftJoinSub($receivables, 'receivables_sum', 'receivables_sum.party_id', '=', 'parties.id')
            ->leftJoinSub($transfers, 'transfers_sum', 'transfers_sum.party_id', '=', 'parties.id')
            ->leftJoinSub($fines, 'fines_sum', 'fines_sum.party_id', '=', 'parties.id')
            ->leftJoinSub($ownerPayments, 'owner_payments_sum', 'owner_payments_sum.party_id', '=', 'parties.id')
            ->select([
                'parties.id as party_id',
                'parties.name',
                DB::raw('COALESCE(hauls_sum.total, 0) as credit_hauls'),
                DB::raw('COALESCE(machine_sum.total, 0) as credit_machine_works'),
                DB::raw('COALESCE(receivables_sum.total, 0) as credit_receivables'),
                DB::raw('COALESCE(payments_sum.total, 0) as debit_payments'),
                DB::raw('COALESCE(fuel_sum.total, 0) as debit_fuel'),
                DB::raw('COALESCE(fines_sum.total, 0) as debit_fines'),
                DB::raw('COALESCE(transfers_sum.credit, 0) as credit_transfers'),
                DB::raw('COALESCE(transfers_sum.debit, 0) as debit_transfers'),
                DB::raw('COALESCE(owner_payments_sum.total, 0) as debit_owner_payments'),
            ])
            ->where(function ($query) use ($siteId, $fuelStationId): void {
                if (filled($siteId) && filled($fuelStationId)) {
                    $query->whereRaw('(COALESCE(hauls_sum.total, 0) + COALESCE(machine_sum.total, 0)) > 0')
                        ->whereRaw('COALESCE(fuel_sum.total, 0) > 0');

                    return;
                }

                if (filled($fuelStationId)) {
                    $query->whereRaw('COALESCE(fuel_sum.total, 0) > 0');

                    return;
                }

                if (filled($siteId)) {
                    $query->whereRaw('(COALESCE(hauls_sum.total, 0) + COALESCE(machine_sum.total, 0)) > 0');

                    return;
                }

                $query->whereRaw('(COALESCE(hauls_sum.total, 0) + COALESCE(machine_sum.total, 0) + COALESCE(receivables_sum.total, 0) + COALESCE(payments_sum.total, 0) + COALESCE(fuel_sum.total, 0) + COALESCE(fines_sum.total, 0) + COALESCE(transfers_sum.credit, 0) + COALESCE(transfers_sum.debit, 0) + COALESCE(owner_payments_sum.total, 0)) <> 0');
            })
            ->orderBy('parties.name')
            ->get();
    }
}
