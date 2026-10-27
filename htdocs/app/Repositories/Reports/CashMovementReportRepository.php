<?php

namespace App\Repositories\Reports;

use App\Enums\PaymentMethod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CashMovementReportRepository
{
    /**
     * Net cash before the period: SUM(giriş − çıkış).
     */
    public function openingBalance(Carbon $from): float
    {
        $total = 0.0;

        // fromInclusive set, toExclusive null → occurred_on < period start
        foreach ($this->branches($from->toDateString(), null) as $branch) {
            $total += (float) DB::query()
                ->fromSub($branch, 'kasa')
                ->selectRaw('COALESCE(SUM(giris - cikis), 0) as total')
                ->value('total');
        }

        return round($total, 2);
    }

    /**
     * Cash lines in [from, toExclusive), ordered for running balance.
     *
     * @return Collection<int, object{
     *     occurred_on: string,
     *     created_at: string,
     *     id: string,
     *     kind_label: string,
     *     description: ?string,
     *     payment_label: string,
     *     giris: string,
     *     cikis: string
     * }>
     */
    public function periodLines(Carbon $from, Carbon $toExclusive): Collection
    {
        $branches = $this->branches($from->toDateString(), $toExclusive->toDateString());

        if ($branches === []) {
            return collect();
        }

        $union = array_shift($branches);
        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        return DB::query()
            ->fromSub($union, 'kasa')
            ->orderBy('occurred_on')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<Builder>
     */
    private function branches(?string $fromInclusive, ?string $toExclusive): array
    {
        $cash = PaymentMethod::Cash->value;
        $branches = [];

        // cash_entries: debit = giriş, credit = çıkış (company cash drawer)
        $cashEntries = DB::table('cash_entries as t')
            ->leftJoin('banks as b', 'b.id', '=', 't.bank_id')
            ->whereNull('t.deleted_at')
            ->when($fromInclusive !== null && $toExclusive !== null, function ($q) use ($fromInclusive, $toExclusive): void {
                $q->where('t.occurred_on', '>=', $fromInclusive)
                    ->where('t.occurred_on', '<', $toExclusive);
            })
            ->when($fromInclusive !== null && $toExclusive === null, fn ($q) => $q->where('t.occurred_on', '<', $fromInclusive))
            ->selectRaw("
                t.occurred_on,
                t.created_at,
                t.id,
                'Kasa Nakit' as kind_label,
                t.description,
                COALESCE(b.name, 'Nakit') as payment_label,
                t.debit as giris,
                t.credit as cikis
            ");
        $branches[] = $cashEntries;

        // site_transactions (no ledger): credit = giriş, debit = çıkış when cash
        $siteTx = DB::table('site_transactions as t')
            ->leftJoin('sites as s', 's.id', '=', 't.site_id')
            ->whereNull('t.deleted_at')
            ->where('t.payment_method', $cash)
            ->when($fromInclusive !== null && $toExclusive !== null, function ($q) use ($fromInclusive, $toExclusive): void {
                $q->where('t.occurred_on', '>=', $fromInclusive)
                    ->where('t.occurred_on', '<', $toExclusive);
            })
            ->when($fromInclusive !== null && $toExclusive === null, fn ($q) => $q->where('t.occurred_on', '<', $fromInclusive))
            ->selectRaw("
                t.occurred_on,
                t.created_at,
                t.id,
                COALESCE(s.name, 'Şantiye') || ' Ödeme' as kind_label,
                t.description,
                'Nakit' as payment_label,
                t.credit as giris,
                t.debit as cikis
            ");
        $branches[] = $siteTx;

        // Ledger cash payments (party perspective: credit = giriş, debit = çıkış)
        // Skip market-linked vehicle_payments (company_transaction_id set) to avoid double count.
        $ledger = DB::table('ledger_entries as le')
            ->leftJoin('parties as p', 'p.id', '=', 'le.party_id')
            ->leftJoin('vehicles as v', 'v.id', '=', 'le.vehicle_id')
            ->leftJoin('vehicle_payments as vp', function ($join): void {
                $join->on('vp.id', '=', 'le.source_id')
                    ->where('le.source_type', '=', 'vehicle_payment');
            })
            ->where('le.payment_method', $cash)
            ->whereIn('le.source_type', [
                'vehicle_payment',
                'vehicle_receivable',
                'party_payment',
                'dump_site_payment',
                'company_transaction',
            ])
            ->where(function ($q): void {
                $q->whereNull('vp.company_transaction_id')
                    ->orWhere('le.source_type', '<>', 'vehicle_payment');
            })
            ->when($fromInclusive !== null && $toExclusive !== null, function ($q) use ($fromInclusive, $toExclusive): void {
                $q->where('le.occurred_on', '>=', $fromInclusive)
                    ->where('le.occurred_on', '<', $toExclusive);
            })
            ->when($fromInclusive !== null && $toExclusive === null, fn ($q) => $q->where('le.occurred_on', '<', $fromInclusive))
            ->selectRaw("
                le.occurred_on,
                le.created_at,
                le.id,
                CASE le.source_type
                    WHEN 'vehicle_payment' THEN COALESCE(v.plate, 'Plaka') || ' Plaka Ödeme'
                    WHEN 'vehicle_receivable' THEN COALESCE(v.plate, 'Plaka') || ' Plaka Alacak'
                    WHEN 'party_payment' THEN COALESCE(p.name, 'Sahip') || ' Sahip Ödeme'
                    WHEN 'dump_site_payment' THEN COALESCE(p.name, 'Döküm') || ' Döküm Ödeme'
                    WHEN 'company_transaction' THEN COALESCE(p.name, 'Firma') || ' Cari'
                    ELSE le.source_type
                END as kind_label,
                le.description,
                'Nakit' as payment_label,
                le.credit as giris,
                le.debit as cikis
            ");
        $branches[] = $ledger;

        // employee_payments (no ledger): cash paid to staff = çıkış
        $employeePayments = DB::table('employee_payments as t')
            ->leftJoin('employees as e', 'e.id', '=', 't.employee_id')
            ->leftJoin('parties as p', 'p.id', '=', 'e.party_id')
            ->whereNull('t.deleted_at')
            ->where('t.payment_method', $cash)
            ->when($fromInclusive !== null && $toExclusive !== null, function ($q) use ($fromInclusive, $toExclusive): void {
                $q->where('t.occurred_on', '>=', $fromInclusive)
                    ->where('t.occurred_on', '<', $toExclusive);
            })
            ->when($fromInclusive !== null && $toExclusive === null, fn ($q) => $q->where('t.occurred_on', '<', $fromInclusive))
            ->selectRaw("
                t.occurred_on,
                t.created_at,
                t.id,
                COALESCE(p.name, 'Personel') || ' Personel Ödeme' as kind_label,
                t.description,
                'Nakit' as payment_label,
                0 as giris,
                t.amount as cikis
            ");
        $branches[] = $employeePayments;

        // other_payments (no ledger morph) — include cash rows if table exists
        if (Schema::hasTable('other_payments')) {
            $other = DB::table('other_payments as t')
                ->whereNull('t.deleted_at')
                ->where('t.payment_method', $cash)
                ->when($fromInclusive !== null && $toExclusive !== null, function ($q) use ($fromInclusive, $toExclusive): void {
                    $q->where('t.occurred_on', '>=', $fromInclusive)
                        ->where('t.occurred_on', '<', $toExclusive);
                })
                ->when($fromInclusive !== null && $toExclusive === null, fn ($q) => $q->where('t.occurred_on', '<', $fromInclusive))
                ->selectRaw("
                    t.occurred_on,
                    t.created_at,
                    t.id,
                    'Diğer Ödeme' as kind_label,
                    t.description,
                    'Nakit' as payment_label,
                    0 as giris,
                    t.amount as cikis
                ");
            $branches[] = $other;
        }

        return $branches;
    }
}
