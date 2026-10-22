<?php

namespace App\Repositories\Reports;

use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SiteExpenseReportRepository
{
    /**
     * @return array<string, string>
     */
    public function sites(): array
    {
        return Site::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function siteName(string $siteId): ?string
    {
        return Site::query()->withTrashed()->whereKey($siteId)->value('name');
    }

    public function openingBalance(string $siteId, Carbon $from): float
    {
        $total = DB::table('site_transactions')
            ->whereNull('deleted_at')
            ->where('site_id', $siteId)
            ->where('occurred_on', '<', $from->toDateString())
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as total')
            ->value('total');

        return round((float) $total, 2);
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
        return DB::table('site_transactions')
            ->whereNull('deleted_at')
            ->where('site_id', $siteId)
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $toExclusive->toDateString())
            ->orderBy('occurred_on')
            ->orderBy('created_at')
            ->orderBy('id')
            ->select([
                'id',
                'occurred_on',
                'description',
                'payment_method',
                'debit',
                'credit',
            ])
            ->get();
    }
}
