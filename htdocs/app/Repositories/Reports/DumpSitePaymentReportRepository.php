<?php

namespace App\Repositories\Reports;

use App\Models\DumpSite;
use App\Models\DumpSitePayment;
use App\Models\Haul;
use App\Models\LedgerEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DumpSitePaymentReportRepository
{
    /**
     * @return array<string, string>
     */
    public function dumpSites(): array
    {
        return DumpSite::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function findDumpSite(string $dumpSiteId): ?DumpSite
    {
        return DumpSite::withTrashed()->find($dumpSiteId);
    }

    /**
     * Period dump totals by vehicle class for one dump site.
     *
     * @return Collection<int, object{vehicle_class_id: ?string, class_name: ?string, trips: string, tonnage: string, amount: string}>
     */
    public function dumpByClass(string $dumpSiteId, Carbon $from, Carbon $toExclusive): Collection
    {
        return Haul::query()
            ->toBase()
            ->join('vehicles', 'vehicles.id', '=', 'hauls.vehicle_id')
            ->leftJoin('vehicle_classes', 'vehicle_classes.id', '=', 'vehicles.vehicle_class_id')
            ->where('hauls.dump_site_id', $dumpSiteId)
            ->where('hauls.hauled_at', '>=', $from)
            ->where('hauls.hauled_at', '<', $toExclusive)
            ->whereNull('hauls.deleted_at')
            ->groupBy('vehicles.vehicle_class_id', 'vehicle_classes.name')
            ->orderByRaw('vehicle_classes.name IS NULL')
            ->orderBy('vehicle_classes.name')
            ->get([
                'vehicles.vehicle_class_id',
                'vehicle_classes.name as class_name',
                DB::raw('COUNT(*) as trips'),
                DB::raw('COALESCE(SUM(hauls.tonnage), 0) as tonnage'),
                DB::raw('COALESCE(SUM(hauls.dump_price), 0) as amount'),
            ]);
    }

    /**
     * @return Collection<int, object{
     *     id: string,
     *     occurred_on: string,
     *     description: ?string,
     *     payment_method: ?string,
     *     cheque_no: ?string,
     *     amount: string
     * }>
     */
    public function payments(string $dumpSiteId, Carbon $from, Carbon $toExclusive): Collection
    {
        return DumpSitePayment::query()
            ->toBase()
            ->where('dump_site_id', $dumpSiteId)
            ->whereNull('deleted_at')
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $toExclusive->toDateString())
            ->orderBy('occurred_on')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get([
                'id',
                'occurred_on',
                'description',
                'payment_method',
                'cheque_no',
                'amount',
            ]);
    }

    /**
     * Ledger opening for the dump operator party: credit (dump fees) − debit (payments).
     *
     * @return array{credit: float, debit: float, net: float}
     */
    public function openingLedger(string $partyId, Carbon $from): array
    {
        return $this->ledgerTotals($partyId, null, $from);
    }

    /**
     * Ledger closing through end of period (exclusive upper bound).
     *
     * @return array{credit: float, debit: float, net: float}
     */
    public function closingLedger(string $partyId, Carbon $toExclusive): array
    {
        return $this->ledgerTotals($partyId, null, $toExclusive);
    }

    /**
     * @return array{credit: float, debit: float, net: float}
     */
    private function ledgerTotals(string $partyId, ?Carbon $fromInclusive, Carbon $toExclusive): array
    {
        $query = LedgerEntry::query()
            ->where('party_id', $partyId)
            ->where('occurred_on', '<', $toExclusive->toDateString());

        if ($fromInclusive !== null) {
            $query->where('occurred_on', '>=', $fromInclusive->toDateString());
        }

        $row = $query
            ->toBase()
            ->selectRaw('COALESCE(SUM(credit), 0) as credit_total, COALESCE(SUM(debit), 0) as debit_total')
            ->first();

        $credit = round((float) ($row->credit_total ?? 0), 2);
        $debit = round((float) ($row->debit_total ?? 0), 2);

        return [
            'credit' => $credit,
            'debit' => $debit,
            'net' => round($credit - $debit, 2),
        ];
    }
}
