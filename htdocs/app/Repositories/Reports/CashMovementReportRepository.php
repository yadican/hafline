<?php

namespace App\Repositories\Reports;

use App\Enums\AccountType;
use App\Enums\InstrumentMovementType;
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

        $kasa = AccountType::Cash->value;

        // Opening balance of each cash box: on its opening day, or before every period when it has none.
        $branches[] = DB::table('banks as b')
            ->whereNull('b.deleted_at')
            ->where('b.type', $kasa)
            ->where('b.opening_balance', '<>', 0)
            ->when($fromInclusive !== null && $toExclusive !== null, function ($q) use ($fromInclusive, $toExclusive): void {
                $q->whereNotNull('b.opened_on')
                    ->where('b.opened_on', '>=', $fromInclusive)
                    ->where('b.opened_on', '<', $toExclusive);
            })
            ->when($fromInclusive !== null && $toExclusive === null, fn ($q) => $q->where(fn ($opened) => $opened
                ->whereNull('b.opened_on')
                ->orWhere('b.opened_on', '<', $fromInclusive)))
            ->selectRaw("
                b.opened_on as occurred_on,
                b.created_at,
                b.id,
                'Açılış Bakiyesi' as kind_label,
                b.name as description,
                b.name as payment_label,
                CASE WHEN b.opening_balance > 0 THEN b.opening_balance ELSE 0 END as giris,
                CASE WHEN b.opening_balance < 0 THEN -b.opening_balance ELSE 0 END as cikis
            ");

        // Transfers between our own accounts: the leg on a cash box moves the drawer.
        foreach (['from_bank_id' => 'cikis', 'to_bank_id' => 'giris'] as $side => $column) {
            $branches[] = DB::table('account_transfers as t')
                ->join('banks as fb', 'fb.id', '=', 't.from_bank_id')
                ->join('banks as tb', 'tb.id', '=', 't.to_bank_id')
                ->whereNull('t.deleted_at')
                ->where($side === 'from_bank_id' ? 'fb.type' : 'tb.type', $kasa)
                ->when($fromInclusive !== null && $toExclusive !== null, function ($q) use ($fromInclusive, $toExclusive): void {
                    $q->where('t.occurred_on', '>=', $fromInclusive)
                        ->where('t.occurred_on', '<', $toExclusive);
                })
                ->when($fromInclusive !== null && $toExclusive === null, fn ($q) => $q->where('t.occurred_on', '<', $fromInclusive))
                ->selectRaw("
                    t.occurred_on,
                    t.created_at,
                    t.id,
                    'Hesaplar Arası Virman' as kind_label,
                    t.description,
                    fb.name || ' → ' || tb.name as payment_label,
                    ".($column === 'giris' ? 't.amount' : '0').' as giris,
                    '.($column === 'cikis' ? 't.amount' : '0').' as cikis
                ');
        }

        // cash_entries on a cash box (or on no account): debit = giriş, credit = çıkış. Kasa Nakit on a
        // bank, POS or card account moves that account, not the drawer.
        $cashEntries = DB::table('cash_entries as t')
            ->leftJoin('banks as b', 'b.id', '=', 't.bank_id')
            ->whereNull('t.deleted_at')
            ->where(fn ($q) => $q->whereNull('t.bank_id')->orWhere('b.type', $kasa))
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

        // Cheques and notes collected into or paid from a cash box (no ledger: the cari was settled when the paper changed hands)
        $instruments = DB::table('financial_instrument_movements as t')
            ->join('financial_instruments as i', 'i.id', '=', 't.financial_instrument_id')
            ->join('banks as b', 'b.id', '=', 't.bank_id')
            ->leftJoin('parties as p', 'p.id', '=', 'i.party_id')
            ->whereNull('t.deleted_at')
            ->where('b.type', AccountType::Cash->value)
            ->whereIn('t.type', [InstrumentMovementType::Collect->value, InstrumentMovementType::Pay->value])
            ->when($fromInclusive !== null && $toExclusive !== null, function ($q) use ($fromInclusive, $toExclusive): void {
                $q->where('t.occurred_on', '>=', $fromInclusive)
                    ->where('t.occurred_on', '<', $toExclusive);
            })
            ->when($fromInclusive !== null && $toExclusive === null, fn ($q) => $q->where('t.occurred_on', '<', $fromInclusive))
            ->selectRaw("
                t.occurred_on,
                t.created_at,
                t.id,
                COALESCE(p.name, 'Çek / Senet') || CASE WHEN t.type = 'collect' THEN ' Tahsilat' ELSE ' Ödeme' END as kind_label,
                COALESCE(t.description, i.number) as description,
                CASE WHEN i.payment_method = 'cheque' THEN 'Çek' ELSE 'Senet' END as payment_label,
                CASE WHEN t.type = 'collect' THEN i.amount ELSE 0 END as giris,
                CASE WHEN t.type = 'pay' THEN i.amount ELSE 0 END as cikis
            ");
        $branches[] = $instruments;

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
