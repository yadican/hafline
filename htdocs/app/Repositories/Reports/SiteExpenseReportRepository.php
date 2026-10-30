<?php

namespace App\Repositories\Reports;

use App\Models\Site;
use App\Repositories\SiteRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SiteExpenseReportRepository
{
    public function __construct(private SiteRepository $sites) {}

    /**
     * @return array<string, string>
     */
    public function sites(): array
    {
        return $this->sites->options();
    }

    public function siteName(string $siteId): ?string
    {
        return Site::query()->withTrashed()->whereKey($siteId)->value('name');
    }

    public function openingBalance(string $siteId, Carbon $from): float
    {
        $transactions = DB::table('site_transactions')
            ->whereNull('deleted_at')
            ->where('site_id', $siteId)
            ->where('occurred_on', '<', $from->toDateString())
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as total')
            ->value('total');

        $usages = DB::table('stock_usages')
            ->whereNull('deleted_at')
            ->where('site_id', $siteId)
            ->where('used_on', '<', $from->toDateString())
            ->sum('amount');

        return round((float) $transactions + (float) $usages, 2);
    }

    /**
     * @return Collection<int, object{
     *     id: string,
     *     occurred_on: string,
     *     description: ?string,
     *     payment_method: ?string,
     *     debit: string,
     *     credit: string
     * }>
     */
    public function periodLines(string $siteId, Carbon $from, Carbon $toExclusive): Collection
    {
        $transactions = DB::table('site_transactions')
            ->whereNull('deleted_at')
            ->where('site_id', $siteId)
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $toExclusive->toDateString())
            ->select([
                'id',
                'occurred_on',
                'description',
                'payment_method',
                'debit',
                'credit',
                'created_at',
            ])
            ->get();

        $usages = DB::table('stock_usages')
            ->whereNull('deleted_at')
            ->where('site_id', $siteId)
            ->where('used_on', '>=', $from->toDateString())
            ->where('used_on', '<', $toExclusive->toDateString())
            ->select([
                'id',
                'used_on as occurred_on',
                'description',
                DB::raw('null as payment_method'),
                'amount as debit',
                DB::raw('0 as credit'),
                'created_at',
            ])
            ->get();

        return $transactions
            ->concat($usages)
            ->sortBy([
                fn (object $a, object $b): int => [(string) $a->occurred_on, (string) $a->created_at, (string) $a->id]
                    <=> [(string) $b->occurred_on, (string) $b->created_at, (string) $b->id],
            ])
            ->values();
    }
}
