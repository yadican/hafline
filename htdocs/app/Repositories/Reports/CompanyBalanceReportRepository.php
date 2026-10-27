<?php

namespace App\Repositories\Reports;

use App\Enums\PartyRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompanyBalanceReportRepository
{
    /**
     * Firm roles selectable on the company balance / ledger reports.
     *
     * @return list<PartyRole>
     */
    public static function reportRoles(): array
    {
        return [
            PartyRole::Supplier,
            PartyRole::Customer,
            PartyRole::Fuel,
            PartyRole::Market,
        ];
    }

    /**
     * Per-party opening (devir), period alacak/borç and closing balance for one firm role.
     * Window is half-open [from, toExclusive) on occurred_on (DATE).
     *
     * @return Collection<int, object{party_id: string, name: string, opening: string, period_credit: string, period_debit: string, closing: string}>
     */
    public function partyTotals(PartyRole $role, Carbon $from, Carbon $toExclusive): Collection
    {
        $dateFrom = $from->toDateString();
        $dateTo = $toExclusive->toDateString();

        return DB::table('company_transactions as ct')
            ->join('parties as p', 'p.id', '=', 'ct.party_id')
            ->whereNull('ct.deleted_at')
            ->where('ct.role', $role->value)
            ->groupBy('p.id', 'p.name')
            ->selectRaw(
                'p.id as party_id,
                p.name,
                COALESCE(SUM(CASE WHEN ct.occurred_on < ? THEN ct.debit - ct.credit ELSE 0 END), 0) as opening,
                COALESCE(SUM(CASE WHEN ct.occurred_on >= ? AND ct.occurred_on < ? THEN ct.credit ELSE 0 END), 0) as period_credit,
                COALESCE(SUM(CASE WHEN ct.occurred_on >= ? AND ct.occurred_on < ? THEN ct.debit ELSE 0 END), 0) as period_debit,
                COALESCE(SUM(CASE WHEN ct.occurred_on < ? THEN ct.debit - ct.credit ELSE 0 END), 0) as closing',
                [$dateFrom, $dateFrom, $dateTo, $dateFrom, $dateTo, $dateTo],
            )
            ->havingRaw(
                '(COALESCE(SUM(CASE WHEN ct.occurred_on >= ? AND ct.occurred_on < ? THEN ct.credit ELSE 0 END), 0) <> 0
                OR COALESCE(SUM(CASE WHEN ct.occurred_on >= ? AND ct.occurred_on < ? THEN ct.debit ELSE 0 END), 0) <> 0
                OR COALESCE(SUM(CASE WHEN ct.occurred_on < ? THEN ct.debit - ct.credit ELSE 0 END), 0) <> 0)',
                [$dateFrom, $dateTo, $dateFrom, $dateTo, $dateTo],
            )
            ->orderBy('p.name')
            ->get();
    }
}
