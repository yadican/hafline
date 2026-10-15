<?php

namespace App\Repositories;

use App\Models\Bank;
use App\Models\DumpSite;
use App\Models\FuelStation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
     * @return array<string, string>
     */
    public function stationOptions(?string $include = null): array
    {
        return FuelStation::withTrashed()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereNull('deleted_at')->where('active', true))
                ->when($include, fn (Builder $query, string $id) => $query->orWhere('id', $id)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function dumpSiteOptions(?string $include = null): array
    {
        return DumpSite::withTrashed()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereNull('deleted_at')->where('active', true))
                ->when($include, fn (Builder $query, string $id) => $query->orWhere('id', $id)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function bankOptions(?string $include = null): array
    {
        return Bank::withTrashed()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereNull('deleted_at')->where('active', true))
                ->when($include, fn (Builder $query, string $id) => $query->orWhere('id', $id)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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
                ->selectRaw('t.id, t.role as kind, p.name as label, t.debit, t.credit, t.occurred_on, t.description, t.payment_method'), $filters);
        }

        if ($keep('vehicle_payment')) {
            $branches[] = $this->money('vehicle_payments', 'vehicle_payment', 'v.plate', 'vehicles as v', 'v.id', 't.vehicle_id', true, $filters);
        }

        if ($keep('vehicle_receivable')) {
            $branches[] = $this->money('vehicle_receivables', 'vehicle_receivable', 'v.plate', 'vehicles as v', 'v.id', 't.vehicle_id', false, $filters);
        }

        if ($keep('owner_payment')) {
            $branches[] = $this->money('party_payments', 'owner_payment', 'p.name', 'parties as p', 'p.id', 't.party_id', true, $filters);
        }

        if ($keep('dump_payment')) {
            $branches[] = $this->money('dump_site_payments', 'dump_payment', 'd.name', 'dump_sites as d', 'd.id', 't.dump_site_id', true, $filters);
        }

        if ($keep('site')) {
            $branches[] = $this->dated(DB::table('site_transactions as t')
                ->leftJoin('sites as s', 's.id', '=', 't.site_id')
                ->whereNull('t.deleted_at')
                ->selectRaw("t.id, 'site' as kind, s.name as label, t.debit, t.credit, t.occurred_on, t.description, t.payment_method"), $filters);
        }

        if ($keep('cash')) {
            $branches[] = $this->dated(DB::table('cash_entries as t')
                ->whereNull('t.deleted_at')
                ->selectRaw("t.id, 'cash' as kind, 'Kasa' as label, t.debit, t.credit, t.occurred_on, t.description, cast(null as varchar(20)) as payment_method"), $filters);
        }

        return $branches;
    }

    private function money(string $table, string $kind, string $label, string $join, string $joinKey, string $foreign, bool $debit, array $filters): QueryBuilder
    {
        $amount = 'cast(t.amount as numeric(14,2))';
        $zero = 'cast(0 as numeric(14,2))';

        return $this->dated(DB::table($table.' as t')
            ->leftJoin($join, $joinKey, '=', $foreign)
            ->whereNull('t.deleted_at')
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
