<?php

namespace App\Repositories\Reports;

use App\Models\DumpSite;
use App\Models\DumpSitePayment;
use App\Models\Haul;
use App\Repositories\DumpSiteRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DumpSitePaymentReportRepository
{
    public function __construct(private DumpSiteRepository $dumpSites) {}

    /**
     * @return array<string, string>
     */
    public function dumpSites(): array
    {
        return $this->dumpSites->options();
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
     * Dump fees (credit) and payments (debit) for this dump site before the period start.
     *
     * @return array{credit: float, debit: float, net: float}
     */
    public function openingTotals(string $dumpSiteId, Carbon $from): array
    {
        return $this->totalsBefore($dumpSiteId, $from);
    }

    /**
     * Dump fees and payments for this dump site through the end of the period (exclusive upper bound).
     *
     * @return array{credit: float, debit: float, net: float}
     */
    public function closingTotals(string $dumpSiteId, Carbon $toExclusive): array
    {
        return $this->totalsBefore($dumpSiteId, $toExclusive);
    }

    /**
     * Not read from the dump operator's ledger: one operator can own several dump sites.
     *
     * @return array{credit: float, debit: float, net: float}
     */
    private function totalsBefore(string $dumpSiteId, Carbon $before): array
    {
        $credit = round((float) Haul::query()
            ->toBase()
            ->where('dump_site_id', $dumpSiteId)
            ->where('hauled_at', '<', $before)
            ->whereNull('deleted_at')
            ->sum('dump_price'), 2);

        $debit = round((float) DumpSitePayment::query()
            ->toBase()
            ->where('dump_site_id', $dumpSiteId)
            ->where('occurred_on', '<', $before->toDateString())
            ->whereNull('deleted_at')
            ->sum('amount'), 2);

        return [
            'credit' => $credit,
            'debit' => $debit,
            'net' => round($credit - $debit, 2),
        ];
    }
}
