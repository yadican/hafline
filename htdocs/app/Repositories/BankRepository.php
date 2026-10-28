<?php

namespace App\Repositories;

use App\Cache\FormOptionsCache;
use App\Models\Bank;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BankRepository extends Repository
{
    public function __construct(Bank $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, type?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query(), $filters['q'] ?? null, ['name', 'branch', 'representative', 'phone', 'account_no', 'iban'])
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  string|array<int, string>|null  $include
     * @return array<string, string>
     */
    public function options(bool $activeOnly = true, string|array|null $include = null): array
    {
        $options = FormOptionsCache::remember(
            FormOptionsCache::BANKS,
            $activeOnly ? 'active' : 'all',
            fn (): array => $this->query()
                ->when($activeOnly, fn (Builder $query) => $query->where('active', true))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        );

        return FormOptionsCache::withIncludes(
            $options,
            $include,
            fn (array $ids): array => Bank::withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->all(),
        );
    }

    /**
     * Active accounts labelled with their kind ("Kasa · Merkez Kasa"), plus any already on a record.
     *
     * @param  string|array<int, string>|null  $include
     * @return array<string, string>
     */
    public function accountOptions(string|array|null $include = null): array
    {
        $label = fn (Bank $bank): string => $bank->type->label().' · '.$bank->name;

        $options = FormOptionsCache::remember(
            FormOptionsCache::BANKS,
            'accounts',
            fn (): array => $this->query()
                ->where('active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Bank $bank) => [$bank->id => $label($bank)])
                ->all(),
        );

        return FormOptionsCache::withIncludes(
            $options,
            $include,
            fn (array $ids): array => Bank::withTrashed()->whereIn('id', $ids)->get()->mapWithKeys(fn (Bank $bank) => [$bank->id => $label($bank)])->all(),
        );
    }

    /**
     * Balance of each account: opening balance plus the money that went in and out through it.
     * Cari lines are read from the party's side (credit = the party paid us = money in).
     *
     * @param  list<string>  $ids
     * @return array<string, float>
     */
    public function balances(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $sum = fn (string $table, string $in, string $out, bool $live = false) => DB::table($table)
            ->whereIn('bank_id', $ids)
            ->when(! $live, fn ($query) => $query->whereNull('deleted_at'))
            ->when($live, fn ($query) => $query->whereNull('reversal_of')->whereNotExists(fn ($reversal) => $reversal
                ->from('ledger_entries as reversals')
                ->whereColumn('reversals.reversal_of', 'ledger_entries.id')))
            ->groupBy('bank_id')
            ->selectRaw("bank_id, COALESCE(SUM({$in} - {$out}), 0) as total")
            ->pluck('total', 'bank_id');

        $ledger = $sum('ledger_entries', 'credit', 'debit', live: true);
        $cash = $sum('cash_entries', 'debit', 'credit');
        $sites = $sum('site_transactions', 'credit', 'debit');
        $openings = Bank::withTrashed()->whereIn('id', $ids)->pluck('opening_balance', 'id');

        $balances = [];

        foreach ($ids as $id) {
            $balances[$id] = round(
                (float) ($openings[$id] ?? 0) + (float) ($ledger[$id] ?? 0) + (float) ($cash[$id] ?? 0) + (float) ($sites[$id] ?? 0),
                2,
            );
        }

        return $balances;
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy('name');
    }

    protected function sortColumns(): array
    {
        return [
            'type' => 'type',
            'name' => 'name',
            'representative' => 'representative',
        ];
    }
}
