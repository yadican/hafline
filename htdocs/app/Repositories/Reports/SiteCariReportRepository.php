<?php

namespace App\Repositories\Reports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SiteCariReportRepository
{
    /**
     * Per-site operating totals for the half-open window.
     * Hauls use hauled_at; fuel uses issued_on; site_transactions use occurred_on.
     *
     * @return Collection<int, object{
     *     site_id: string,
     *     name: string,
     *     dump_total: string,
     *     haul_total: string,
     *     haul_count: string,
     *     fuel_total: string,
     *     fuel_count: string,
     *     other_total: string,
     *     earned_total: string
     * }>
     */
    public function siteTotals(Carbon $from, Carbon $toExclusive): Collection
    {
        $haulFrom = $from->copy();
        $haulTo = $toExclusive->copy();
        $dateFrom = $from->toDateString();
        $dateTo = $toExclusive->toDateString();

        $hauls = DB::table('hauls')
            ->select([
                'site_id',
                DB::raw('COALESCE(SUM(dump_price), 0) as dump_total'),
                DB::raw('COALESCE(SUM(price), 0) as haul_total'),
                DB::raw('COUNT(*) as haul_count'),
            ])
            ->whereNull('deleted_at')
            ->where('hauled_at', '>=', $haulFrom)
            ->where('hauled_at', '<', $haulTo)
            ->groupBy('site_id');

        $fuel = DB::table('fuel_receipts')
            ->select([
                'site_id',
                DB::raw('COALESCE(SUM(amount), 0) as fuel_total'),
                DB::raw('COUNT(*) as fuel_count'),
            ])
            ->whereNull('deleted_at')
            ->whereNotNull('site_id')
            ->where('issued_on', '>=', $dateFrom)
            ->where('issued_on', '<', $dateTo)
            ->groupBy('site_id');

        $siteTx = DB::table('site_transactions')
            ->select([
                'site_id',
                DB::raw('COALESCE(SUM(debit), 0) as other_total'),
                DB::raw('COALESCE(SUM(credit), 0) as earned_total'),
            ])
            ->whereNull('deleted_at')
            ->where('occurred_on', '>=', $dateFrom)
            ->where('occurred_on', '<', $dateTo)
            ->groupBy('site_id');

        return DB::table('sites as s')
            ->leftJoinSub($hauls, 'h', 'h.site_id', '=', 's.id')
            ->leftJoinSub($fuel, 'f', 'f.site_id', '=', 's.id')
            ->leftJoinSub($siteTx, 't', 't.site_id', '=', 's.id')
            ->select([
                's.id as site_id',
                's.name',
                DB::raw('COALESCE(h.dump_total, 0) as dump_total'),
                DB::raw('COALESCE(h.haul_total, 0) as haul_total'),
                DB::raw('COALESCE(h.haul_count, 0) as haul_count'),
                DB::raw('COALESCE(f.fuel_total, 0) as fuel_total'),
                DB::raw('COALESCE(f.fuel_count, 0) as fuel_count'),
                DB::raw('COALESCE(t.other_total, 0) as other_total'),
                DB::raw('COALESCE(t.earned_total, 0) as earned_total'),
            ])
            ->where(function ($query): void {
                $query->whereRaw('COALESCE(h.haul_count, 0) > 0')
                    ->orWhereRaw('COALESCE(f.fuel_count, 0) > 0')
                    ->orWhereRaw('(COALESCE(t.other_total, 0) + COALESCE(t.earned_total, 0)) <> 0');
            })
            ->orderBy('s.name')
            ->get();
    }
}
