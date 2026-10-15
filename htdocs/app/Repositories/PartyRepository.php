<?php

namespace App\Repositories;

use App\Enums\PartyRole;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class PartyRepository extends Repository
{
    public function __construct(Party $model)
    {
        parent::__construct($model);
    }

    /**
     * Every word must match somewhere, so "ali yılmaz" finds the party across name and contact fields.
     *
     * @param  array{q?: string, type?: string, role?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['roleAssignments', 'person', 'company'])
            ->withCount(['ownerships as vehicles_count' => fn (Builder $query) => $query->whereNull('ended_on')])
            ->addSelect(['balance' => $this->balanceQuery()])
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->withRole(PartyRole::from($role)))
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        foreach (preg_split('/\s+/u', trim($filters['q'] ?? ''), flags: PREG_SPLIT_NO_EMPTY) as $word) {
            $like = $this->likePattern($word);

            $query->where(fn (Builder $query) => $query
                ->whereLike('name', $like)
                ->orWhereLike('phone', $like)
                ->orWhereLike('mobile', $like)
                ->orWhereHas('person', fn (Builder $person) => $person->whereLike('national_id', $like))
                ->orWhereHas('company', fn (Builder $company) => $company->whereLike('tax_number', $like)));
        }

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  string|array<int, string>|null  $include
     * @return array<string, string>
     */
    public function options(?PartyRole $role = null, string|array|null $include = null): array
    {
        $include = array_filter((array) $include);

        return Party::withTrashed()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereNull('deleted_at')
                    ->where('active', true)
                    ->when($role, fn (Builder $query, PartyRole $role) => $query->withRole($role)))
                ->when($include, fn (Builder $query, array $ids) => $query->orWhereIn('id', $ids)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Newest first; `running_balance` is the balance after each row, computed over the whole account.
     */
    public function statement(Party $party, int $perPage = 25): LengthAwarePaginator
    {
        $rows = LedgerEntry::query()
            ->select('ledger_entries.*')
            ->selectRaw('SUM(debit - credit) OVER (ORDER BY occurred_on, created_at, id) AS running_balance')
            ->where('party_id', $party->id);

        return LedgerEntry::query()
            ->fromSub($rows, 'ledger_entries')
            ->with(['source', 'vehicle'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, pageName: 'hareket')
            ->withQueryString();
    }

    /**
     * @return array{debit: float, credit: float, balance: float}
     */
    public function totals(Party $party): array
    {
        $row = DB::table('ledger_entries')
            ->where('party_id', $party->id)
            ->selectRaw('COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit')
            ->first();

        return [
            'debit' => (float) $row->debit,
            'credit' => (float) $row->credit,
            'balance' => (float) $row->debit - (float) $row->credit,
        ];
    }

    /**
     * Roles that records already depend on and therefore cannot be removed by hand.
     *
     * @return list<PartyRole>
     */
    public function lockedRoles(Party $party): array
    {
        $roles = DB::table('company_transactions')
            ->where('party_id', $party->id)
            ->distinct()
            ->pluck('role')
            ->map(fn (string $role) => PartyRole::from($role))
            ->all();

        if ($party->ownerships()->exists()) {
            $roles[] = PartyRole::VehicleOwner;
        }

        if ($party->dumpSites()->withTrashed()->exists()) {
            $roles[] = PartyRole::DumpOperator;
        }

        if ($party->fuelStations()->withTrashed()->exists()) {
            $roles[] = PartyRole::Fuel;
        }

        foreach (DB::table('site_parties')->where('party_id', $party->id)->distinct()->pluck('role') as $role) {
            $roles[] = PartyRole::from($role);
        }

        return collect($roles)->unique(fn (PartyRole $role) => $role->value)->values()->all();
    }

    public function isReferenced(Party $party): bool
    {
        return $party->ownerships()->exists()
            || $party->ledgerEntries()->exists()
            || $party->dumpSites()->withTrashed()->exists()
            || $party->fuelStations()->withTrashed()->exists()
            || DB::table('site_parties')->where('party_id', $party->id)->exists()
            || DB::table('employees')->where('party_id', $party->id)->exists()
            || DB::table('company_transactions')->where('party_id', $party->id)->exists();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy('name');
    }

    protected function sortColumns(): array
    {
        return [
            'name' => 'name',
            'vehicles' => 'vehicles_count',
            'balance' => 'balance',
        ];
    }

    private function balanceQuery(): QueryBuilder
    {
        return DB::table('ledger_entries')
            ->selectRaw('COALESCE(SUM(debit - credit), 0)')
            ->whereColumn('ledger_entries.party_id', 'parties.id');
    }
}
