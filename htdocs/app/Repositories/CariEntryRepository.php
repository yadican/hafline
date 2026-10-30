<?php

namespace App\Repositories;

use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CariEntryRepository
{
    /**
     * @param  array<string, string>  $filters
     */
    public function search(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $union = null;

        foreach ($this->branches($filters) as $branch) {
            $union = $union ? $union->unionAll($branch) : $branch;
        }

        return DB::query()
            ->fromSub($union, 'entries')
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Newest first, each change of the entry that moved a balance: the lines it posted before
     * (cari, amount, side) and the lines it posts now. The first posting is not a change.
     *
     * @return Collection<int, array{changed_at: Carbon, user: ?User, before: Collection<int, LedgerEntry>, after: Collection<int, LedgerEntry>}>
     */
    public function corrections(Model $entry): Collection
    {
        return LedgerEntry::query()
            ->with(['party', 'creator', 'reversed.party'])
            ->where('source_type', $entry->getMorphClass())
            ->where('source_id', $entry->getKey())
            ->whereNotNull('revision_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('revision_id')
            ->filter(fn (Collection $rows) => $rows->whereNotNull('reversal_of')->isNotEmpty())
            ->map(fn (Collection $rows): array => [
                'changed_at' => $rows->last()->created_at,
                'user' => $rows->first()->creator,
                'before' => $rows->whereNotNull('reversal_of')->map(fn (LedgerEntry $reversal) => $reversal->reversed)->values(),
                'after' => $rows->whereNull('reversal_of')->values(),
            ])
            ->reverse()
            ->values();
    }

    /**
     * @param  array<string, string>  $filters
     * @return list<QueryBuilder>
     */
    private function branches(array $filters): array
    {
        $kind = $filters['kind'] ?? null;
        $branches = [];

        $keep = function (string ...$kinds) use ($kind, &$branches): bool {
            return $kind === null || in_array($kind, $kinds, true);
        };

        if ($keep('supplier', 'customer', 'fuel', 'market')) {
            $branches[] = $this->dated(DB::table('company_transactions as t')
                ->leftJoin('parties as p', 'p.id', '=', 't.party_id')
                ->whereNull('t.deleted_at')
                ->when($kind, fn (QueryBuilder $query) => $query->where('t.role', $kind))
                ->when($filters['party'] ?? null, fn (QueryBuilder $query, string $party) => $query->where('t.party_id', $party))
                ->selectRaw('t.id, t.role as kind, p.name as label, t.debit, t.credit, t.occurred_on, t.description, t.payment_method'), $filters);
        }

        if ($keep('vehicle_payment')) {
            $branches[] = $this->money('vehicle_payments', 'vehicle_payment', 'v.plate', 'vehicles as v', 'v.id', 't.vehicle_id', 't.party_id', true, $filters);
        }

        if ($keep('vehicle_receivable')) {
            $branches[] = $this->money('vehicle_receivables', 'vehicle_receivable', 'v.plate', 'vehicles as v', 'v.id', 't.vehicle_id', 't.party_id', false, $filters);
        }

        if ($keep('owner_payment')) {
            $branches[] = $this->money('party_payments', 'owner_payment', 'p.name', 'parties as p', 'p.id', 't.party_id', 't.party_id', true, $filters);
        }

        if ($keep('dump_payment')) {
            $branches[] = $this->money('dump_site_payments', 'dump_payment', 'd.name', 'dump_sites as d', 'd.id', 't.dump_site_id', 'd.party_id', true, $filters);
        }

        if ($keep('site')) {
            $branches[] = $this->dated(DB::table('site_transactions as t')
                ->leftJoin('sites as s', 's.id', '=', 't.site_id')
                ->whereNull('t.deleted_at')
                ->leftJoin('site_parties as sp', 'sp.site_id', '=', 't.site_id')
                ->when($filters['party'] ?? null, fn (QueryBuilder $query, string $party) => $query->where('sp.party_id', $party))
                ->selectRaw("t.id, 'site' as kind, s.name as label, t.debit, t.credit, t.occurred_on, t.description, t.payment_method"), $filters);
        }

        if ($keep('site_bill')) {
            $branches[] = $this->dated(DB::table('site_bills as t')
                ->leftJoin('sites as s', 's.id', '=', 't.site_id')
                ->whereNull('t.deleted_at')
                ->when($filters['party'] ?? null, fn (QueryBuilder $query, string $party) => $query->where('t.party_id', $party))
                ->selectRaw("t.id, 'site_bill' as kind, s.name as label, t.amount as debit, cast(0 as numeric(14,2)) as credit, t.occurred_on, t.description, cast(null as varchar(20)) as payment_method"), $filters);
        }

        if ($keep('opening')) {
            $branches[] = $this->dated(DB::table('opening_balances as t')
                ->leftJoin('parties as p', 'p.id', '=', 't.party_id')
                ->whereNull('t.deleted_at')
                ->when($filters['party'] ?? null, fn (QueryBuilder $query, string $party) => $query->where('t.party_id', $party))
                ->selectRaw("t.id, 'opening' as kind, p.name as label, t.debit, t.credit, t.occurred_on, t.description, cast(null as varchar(20)) as payment_method"), $filters);
        }

        if ($keep('party_transfer')) {
            $branches[] = $this->dated(DB::table('party_transfers as t')
                ->leftJoin('parties as dp', 'dp.id', '=', 't.debit_party_id')
                ->leftJoin('parties as cp', 'cp.id', '=', 't.credit_party_id')
                ->whereNull('t.deleted_at')
                ->when($filters['party'] ?? null, fn (QueryBuilder $query, string $party) => $query->where(fn (QueryBuilder $query) => $query
                    ->where('t.debit_party_id', $party)
                    ->orWhere('t.credit_party_id', $party)))
                ->selectRaw("t.id, 'party_transfer' as kind, dp.name || ' → ' || cp.name as label, t.amount as debit, t.amount as credit, t.occurred_on, t.description, cast(null as varchar(20)) as payment_method"), $filters);
        }

        if ($keep('cash')) {
            $branches[] = $this->dated(DB::table('cash_entries as t')
                ->whereNull('t.deleted_at')
                ->selectRaw("t.id, 'cash' as kind, 'Kasa' as label, t.debit, t.credit, t.occurred_on, t.description, cast(null as varchar(20)) as payment_method"), $filters);
        }

        if ($keep('account_transfer') && empty($filters['party'])) {
            $branches[] = $this->dated(DB::table('account_transfers as t')
                ->leftJoin('banks as fb', 'fb.id', '=', 't.from_bank_id')
                ->leftJoin('banks as tb', 'tb.id', '=', 't.to_bank_id')
                ->whereNull('t.deleted_at')
                ->selectRaw("t.id, 'account_transfer' as kind, fb.name || ' → ' || tb.name as label, t.amount as debit, t.amount as credit, t.occurred_on, t.description, cast(null as varchar(20)) as payment_method"), $filters);
        }

        return $branches;
    }

    private function money(string $table, string $kind, string $label, string $join, string $joinKey, string $foreign, ?string $partyColumn, bool $debit, array $filters): QueryBuilder
    {
        $amount = 'cast(t.amount as numeric(14,2))';
        $zero = 'cast(0 as numeric(14,2))';

        return $this->dated(DB::table($table.' as t')
            ->leftJoin($join, $joinKey, '=', $foreign)
            ->whereNull('t.deleted_at')
            ->when($filters['party'] ?? null, fn (QueryBuilder $query, string $party) => $query->where($partyColumn, $party))
            ->selectRaw("t.id, '{$kind}' as kind, {$label} as label, ".($debit ? $amount : $zero).' as debit, '.($debit ? $zero : $amount).' as credit, t.occurred_on, t.description, t.payment_method'), $filters);
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function dated(QueryBuilder $query, array $filters): QueryBuilder
    {
        return $query
            ->when($filters['from'] ?? null, fn (QueryBuilder $query, string $from) => $query->whereDate('t.occurred_on', '>=', $from))
            ->when($filters['to'] ?? null, fn (QueryBuilder $query, string $to) => $query->whereDate('t.occurred_on', '<=', $to));
    }
}
