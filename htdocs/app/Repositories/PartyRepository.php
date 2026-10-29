<?php

namespace App\Repositories;

use App\Cache\FormOptionsCache;
use App\Enums\Module;
use App\Enums\PartyRole;
use App\Enums\PartyType;
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
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
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
    public function options(?PartyRole $role = null, string|array|null $include = null, ?PartyType $type = null): array
    {
        $options = FormOptionsCache::remember(
            FormOptionsCache::PARTIES,
            ($role?->value ?? 'all').($type ? ':'.$type->value : ''),
            fn (): array => Party::query()
                ->where('active', true)
                ->when($role, fn (Builder $query, PartyRole $role) => $query->withRole($role))
                ->when($type, fn (Builder $query, PartyType $type) => $query->where('type', $type))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        );

        return FormOptionsCache::withIncludes(
            $options,
            $include,
            fn (array $ids): array => Party::withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->all(),
        );
    }

    /**
     * Newest first; `running_balance` is the balance after each row, computed over the listed rows.
     * Cancelled postings and their reversals net to zero and are left out unless asked for, so the
     * listed rows add up to the totals and to the reports.
     */
    public function statement(Party $party, int $perPage = 25, bool $withReversals = false, ?Module $module = null): LengthAwarePaginator
    {
        $rows = LedgerEntry::query()
            ->select('ledger_entries.*')
            ->selectRaw('SUM(debit - credit) OVER (ORDER BY occurred_on, created_at, id) AS running_balance')
            ->where('party_id', $party->id)
            ->when($module, fn (Builder $query, Module $module) => $query->where('module', $module))
            ->unless($withReversals, fn (Builder $query) => $this->live($query));

        return LedgerEntry::query()
            ->fromSub($rows, 'ledger_entries')
            ->with(['source', 'vehicle', 'cashCategory'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, pageName: 'hareket')
            ->withQueryString();
    }

    /**
     * @return array{debit: float, credit: float, balance: float}
     */
    public function totals(Party $party, ?Module $module = null): array
    {
        $row = $this->live(LedgerEntry::query()->toBase()->where('party_id', $party->id))
            ->when($module, fn (QueryBuilder $query, Module $module) => $query->where('module', $module->value))
            ->selectRaw('COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit')
            ->first();

        return [
            'debit' => (float) $row->debit,
            'credit' => (float) $row->credit,
            'balance' => (float) $row->debit - (float) $row->credit,
        ];
    }

    /**
     * The party's one balance split by the module each movement came from; modules without movement are left out.
     *
     * @return array<string, array{module: Module, debit: float, credit: float, balance: float}>
     */
    public function moduleTotals(Party $party): array
    {
        $rows = $this->live(LedgerEntry::query()->toBase()->where('party_id', $party->id))
            ->groupBy('module')
            ->selectRaw('module, COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit')
            ->get()
            ->keyBy('module');

        $totals = [];

        foreach (Module::cases() as $module) {
            if ($row = $rows->get($module->value)) {
                $totals[$module->value] = [
                    'module' => $module,
                    'debit' => (float) $row->debit,
                    'credit' => (float) $row->credit,
                    'balance' => (float) $row->debit - (float) $row->credit,
                ];
            }
        }

        return $totals;
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

        if (DB::table('sites')->where('party_id', $party->id)->exists()) {
            $roles[] = PartyRole::Site;
        }

        if (DB::table('employees')->where('party_id', $party->id)->whereNull('deleted_at')->exists()) {
            $roles[] = PartyRole::Employee;
        }

        if (DB::table('machine_works')->where('operator_id', $party->id)->exists()) {
            $roles[] = PartyRole::MachineOperator;
        }

        if (DB::table('shipments')->where('customer_party_id', $party->id)->exists()) {
            $roles[] = PartyRole::Customer;
        }

        if (DB::table('shipments')->where('commission_party_id', $party->id)->exists()) {
            $roles[] = PartyRole::Commission;
        }

        foreach (DB::table('site_parties')->where('party_id', $party->id)->distinct()->pluck('role') as $role) {
            $roles[] = PartyRole::from($role);
        }

        return collect($roles)->unique(fn (PartyRole $role) => $role->value)->values()->all();
    }

    /**
     * Options a money entry may pick: system caris (Geçici Cari) only take postings through a plate.
     *
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    public function withoutSystem(array $options): array
    {
        return array_diff_key($options, array_flip(Party::query()->whereNotNull('system_key')->pluck('id')->all()));
    }

    public function isReferenced(Party $party): bool
    {
        return $party->ownerships()->exists()
            || $party->ledgerEntries()->exists()
            || $party->dumpSites()->withTrashed()->exists()
            || $party->fuelStations()->withTrashed()->exists()
            || DB::table('site_parties')->where('party_id', $party->id)->exists()
            || DB::table('sites')->where('party_id', $party->id)->exists()
            || DB::table('employees')->where('party_id', $party->id)->exists()
            || DB::table('machine_works')->where('operator_id', $party->id)->exists()
            || DB::table('fuel_receipts')->where(fn ($query) => $query->where('party_id', $party->id)->orWhere('fuel_party_id', $party->id))->exists()
            || DB::table('fuel_tank_fills')->where('party_id', $party->id)->exists()
            || DB::table('shipments')->where(fn ($query) => $query->where('party_id', $party->id)->orWhere('customer_party_id', $party->id)
                ->orWhere('commission_party_id', $party->id))->exists()
            || DB::table('company_transactions')->where('party_id', $party->id)->exists();
    }

    public function hasReversals(Party $party): bool
    {
        return LedgerEntry::query()->where('party_id', $party->id)->whereNotNull('reversal_of')->exists();
    }

    /**
     * Postings that are neither a reversal nor reversed.
     *
     * @template TQuery of Builder|QueryBuilder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function live(Builder|QueryBuilder $query): Builder|QueryBuilder
    {
        return $query
            ->whereNull('ledger_entries.reversal_of')
            ->whereNotExists(fn (QueryBuilder $reversal) => $reversal
                ->from('ledger_entries as reversals')
                ->whereColumn('reversals.reversal_of', 'ledger_entries.id'));
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
