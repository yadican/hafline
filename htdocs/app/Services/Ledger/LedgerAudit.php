<?php

namespace App\Services\Ledger;

use App\Enums\AccountType;
use App\Models\Bank;
use App\Models\FinancialInstrument;
use App\Models\VehicleOwnership;
use App\Repositories\BankRepository;
use App\Repositories\Reports\OwnerBalanceReportRepository;
use App\Repositories\Reports\VehicleBalanceReportRepository;
use App\Repositories\VehicleAccountRepository;
use App\Services\Reports\CashMovementReportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cross-checks the ledger (cari) against the source documents and the statement reports, so a
 * leak between what a party is owed on the cari screen and on a report shows up as a failed check.
 */
class LedgerAudit
{
    /**
     * Live postings each source document must have: type, document, party, day, debit, credit.
     */
    private const EXPECTED = <<<'SQL'
        select 'haul' st, cast(id as text) sid, cast(party_id as text) p, substr(cast(hauled_at as text), 1, 10) d, 0 dr, price cr from hauls where deleted_at is null and price <> 0
        union all select 'haul', cast(id as text), cast(dump_party_id as text), substr(cast(hauled_at as text), 1, 10), 0, dump_price from hauls where deleted_at is null and dump_price <> 0
        union all select 'haul', cast(id as text), cast(site_party_id as text), substr(cast(hauled_at as text), 1, 10), site_price, 0 from hauls where deleted_at is null and site_party_id is not null and site_price <> 0
        union all select 'site_bill', cast(id as text), cast(party_id as text), substr(cast(occurred_on as text), 1, 10), amount, 0 from site_bills where deleted_at is null
        union all select 'site_transaction', cast(id as text), cast(party_id as text), substr(cast(occurred_on as text), 1, 10), 0, credit from site_transactions where deleted_at is null and party_id is not null and credit <> 0
        union all select 'fuel_receipt', cast(id as text), cast(party_id as text), substr(cast(issued_on as text), 1, 10), amount, 0 from fuel_receipts where deleted_at is null and amount <> 0
        union all select 'fuel_receipt', cast(id as text), cast(fuel_party_id as text), substr(cast(issued_on as text), 1, 10), 0, purchase_amount from fuel_receipts where deleted_at is null and fuel_party_id is not null and purchase_amount <> 0
        union all select 'fuel_tank_fill', cast(id as text), cast(party_id as text), substr(cast(filled_on as text), 1, 10), 0, amount from fuel_tank_fills where deleted_at is null
        union all select 'shipment', cast(id as text), cast(customer_party_id as text), substr(cast(shipped_on as text), 1, 10), customer_amount, 0 from shipments where deleted_at is null and customer_amount <> 0
        union all select 'shipment', cast(id as text), cast(party_id as text), substr(cast(shipped_on as text), 1, 10), 0, carrier_net_amount from shipments where deleted_at is null and carrier_net_amount <> 0
        union all select 'shipment', cast(id as text), cast(party_id as text), substr(cast(shipped_on as text), 1, 10), 0, driver_amount from shipments where deleted_at is null and driver_amount <> 0
        union all select 'shipment', cast(id as text), cast(commission_party_id as text), substr(cast(shipped_on as text), 1, 10), 0, commission_amount from shipments where deleted_at is null and commission_party_id is not null and commission_amount <> 0
        union all select 'machine_work', cast(id as text), cast(party_id as text), substr(cast(worked_on as text), 1, 10), 0, amount from machine_works where deleted_at is null and amount <> 0
        union all select 'machine_work', cast(id as text), cast(operator_id as text), substr(cast(worked_on as text), 1, 10), 0, operator_amount from machine_works where deleted_at is null and operator_amount is not null and operator_amount <> 0
        union all select 'vehicle_payment', cast(id as text), cast(party_id as text), substr(cast(occurred_on as text), 1, 10), amount, 0 from vehicle_payments where deleted_at is null
        union all select 'vehicle_receivable', cast(id as text), cast(party_id as text), substr(cast(occurred_on as text), 1, 10), 0, amount from vehicle_receivables where deleted_at is null
        union all select 'party_payment', cast(id as text), cast(party_id as text), substr(cast(occurred_on as text), 1, 10), amount, 0 from party_payments where deleted_at is null
        union all select 'dump_site_payment', cast(id as text), '', substr(cast(occurred_on as text), 1, 10), amount, 0 from dump_site_payments where deleted_at is null
        union all select 'company_transaction', cast(id as text), cast(party_id as text), substr(cast(occurred_on as text), 1, 10), debit, credit from company_transactions where deleted_at is null and (debit <> 0 or credit <> 0)
        union all select 'vehicle_ownership', cast(id as text), cast(carried_from_party_id as text), substr(cast(started_on as text), 1, 10), case when carried_balance > 0 then carried_balance else 0 end, case when carried_balance < 0 then -carried_balance else 0 end from vehicle_ownerships where carried_balance <> 0
        union all select 'vehicle_ownership', cast(id as text), cast(party_id as text), substr(cast(started_on as text), 1, 10), case when carried_balance < 0 then -carried_balance else 0 end, case when carried_balance > 0 then carried_balance else 0 end from vehicle_ownerships where carried_balance <> 0
        union all select 'employee_salary', cast(s.id as text), cast(e.party_id as text), %s, 0, s.earned_salary from employee_salaries s join employees e on e.id = s.employee_id where s.deleted_at is null and s.earned_salary <> 0
        union all select 'employee_bonus', cast(b.id as text), cast(e.party_id as text), substr(cast(b.occurred_on as text), 1, 10), 0, b.amount from employee_bonuses b join employees e on e.id = b.employee_id where b.deleted_at is null and b.amount <> 0
        union all select 'employee_payment', cast(t.id as text), cast(e.party_id as text), substr(cast(t.occurred_on as text), 1, 10), t.amount, 0 from employee_payments t join employees e on e.id = t.employee_id where t.deleted_at is null and t.amount <> 0
        union all select 'opening_balance', cast(id as text), cast(party_id as text), substr(cast(occurred_on as text), 1, 10), debit, credit from opening_balances where deleted_at is null
        union all select 'party_transfer', cast(id as text), cast(debit_party_id as text), substr(cast(occurred_on as text), 1, 10), amount, 0 from party_transfers where deleted_at is null
        union all select 'party_transfer', cast(id as text), cast(credit_party_id as text), substr(cast(occurred_on as text), 1, 10), 0, amount from party_transfers where deleted_at is null
        union all select 'financial_instrument_movement', cast(m.id as text), cast(m.party_id as text), substr(cast(m.occurred_on as text), 1, 10), i.amount, 0 from financial_instrument_movements m join financial_instruments i on i.id = m.financial_instrument_id where m.deleted_at is null and m.type = 'endorse'
        union all select 'financial_instrument_movement', cast(m.id as text), cast(i.party_id as text), substr(cast(m.occurred_on as text), 1, 10), case when i.direction = 'received' then i.amount else 0 end, case when i.direction = 'given' then i.amount else 0 end from financial_instrument_movements m join financial_instruments i on i.id = m.financial_instrument_id where m.deleted_at is null and m.type = 'bounce'
        union all select 'financial_instrument_movement', cast(m.id as text), cast(m.party_id as text), substr(cast(m.occurred_on as text), 1, 10), 0, i.amount from financial_instrument_movements m join financial_instruments i on i.id = m.financial_instrument_id where m.deleted_at is null and m.type = 'bounce' and m.party_id is not null
    SQL;

    /**
     * A dump payment stays on the operator it was paid to, so its party is not derived from the dump site.
     */
    private const LIVE = <<<'SQL'
        select source_type st, cast(source_id as text) sid, case when source_type = 'dump_site_payment' then '' else cast(party_id as text) end p,
               substr(cast(occurred_on as text), 1, 10) d, debit dr, credit cr
        from ledger_entries le
        where reversal_of is null and not exists (select 1 from ledger_entries r where r.reversal_of = le.id)
    SQL;

    public function __construct(
        private VehicleAccountRepository $accounts,
        private OwnerBalanceReportRepository $ownerBalances,
        private VehicleBalanceReportRepository $vehicleBalances,
        private CashMovementReportService $cash,
        private BankRepository $banks,
    ) {}

    /**
     * @return list<array{check: string, checked: int, failures: list<string>}>
     */
    public function run(): array
    {
        return [
            $this->postingsMatchDocuments(),
            $this->postingsCarryTheirModuleAndSite(),
            $this->workSitsWithTheOwnerOfItsDate(),
            $this->platePaymentsSitWithAnOwner(),
            $this->transfersCloseThePreviousOwner(),
            $this->ownerBalanceReportMatchesCari(),
            $this->plateBalanceReportMatchesCari(),
            $this->cashDrawerMatchesCashMovements(),
            $this->cashDrawerMatchesCashAccounts(),
            $this->instrumentsMatchTheirEntries(),
        ];
    }

    /**
     * A cheque or note in the portfolio must be the one its cari entry says: same amount and kind,
     * on the party the entry is posted to, with the entry still there, and a status that follows its last step.
     *
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function instrumentsMatchTheirEntries(): array
    {
        $rows = DB::select(<<<'SQL'
            select cast(i.id as text) id, i.number, i.amount, i.payment_method, cast(i.party_id as text) party,
                   s.sid, s.amount source_amount, s.method source_method, s.deleted_at source_deleted, lp.party posted_party
            from financial_instruments i
            left join (
                select 'company_transaction' st, cast(id as text) sid, debit + credit amount, payment_method method, deleted_at from company_transactions
                union all select 'vehicle_payment', cast(id as text), amount, payment_method, deleted_at from vehicle_payments
                union all select 'party_payment', cast(id as text), amount, payment_method, deleted_at from party_payments
                union all select 'dump_site_payment', cast(id as text), amount, payment_method, deleted_at from dump_site_payments
                union all select 'site_transaction', cast(id as text), debit + credit, payment_method, deleted_at from site_transactions
            ) s on s.st = i.source_type and s.sid = cast(i.source_id as text)
            left join (
                select le.source_type, cast(le.source_id as text) sid, min(cast(le.party_id as text)) party
                from ledger_entries le
                where le.reversal_of is null and not exists (select 1 from ledger_entries r where r.reversal_of = le.id)
                group by le.source_type, cast(le.source_id as text)
            ) lp on lp.source_type = i.source_type and lp.sid = cast(i.source_id as text)
            where i.deleted_at is null
              and (s.sid is null or s.deleted_at is not null or s.amount <> i.amount or s.method <> i.payment_method
                   or coalesce(lp.party, '') <> coalesce(cast(i.party_id as text), ''))
        SQL);

        $failures = array_map(fn (object $row): string => sprintf(
            'Çek/senet %s: tutar %s / belge %s, tür %s / belge %s, cari %s / belge %s%s',
            $row->number ?: $row->id,
            $row->amount,
            $row->source_amount ?? 'yok',
            $row->payment_method,
            $row->source_method ?? 'yok',
            $row->party ?? '—',
            $row->posted_party ?? '—',
            $row->sid === null || $row->source_deleted !== null ? ' (cari hareketi silinmiş)' : '',
        ), $rows);

        $statuses = FinancialInstrument::query()->with('movements')->get();

        foreach ($statuses as $instrument) {
            $expected = $instrument->movements->last()?->type->resultingStatus() ?? $instrument->direction->initialStatus();

            if ($instrument->status !== $expected) {
                $failures[] = sprintf('Çek/senet %s: durum %s, son adıma göre %s olmalı', $instrument->number ?: $instrument->id, $instrument->status->label(), $expected->label());
            }
        }

        return $this->result('Çek / senet portföyü cari hareketleriyle aynı', $statuses->count(), $failures);
    }

    /**
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function postingsMatchDocuments(): array
    {
        $grouped = fn (string $sql): string => "select st, sid, p, d, sum(dr) dr, sum(cr) cr from ({$sql}) x group by st, sid, p, d";

        $rows = DB::select(sprintf(
            'select coalesce(e.st, l.st) st, coalesce(e.sid, l.sid) sid, coalesce(e.p, l.p) p, coalesce(e.d, l.d) d, e.dr edr, e.cr ecr, l.dr ldr, l.cr lcr
             from (%s) e full outer join (%s) l on e.st = l.st and e.sid = l.sid and e.p = l.p and e.d = l.d
             where e.st is null or l.st is null or e.dr <> l.dr or e.cr <> l.cr',
            $grouped(sprintf(self::EXPECTED, $this->monthEnd('s.period'))),
            $grouped(self::LIVE),
        ));

        $failures = array_map(fn (object $row): string => sprintf(
            '%s %s cari %s %s: belge %s/%s, cari %s/%s',
            $row->st,
            $row->sid,
            $row->p ?: '—',
            $row->d,
            $row->edr ?? 'yok',
            $row->ecr ?? 'yok',
            $row->ldr ?? 'yok',
            $row->lcr ?? 'yok',
        ), $rows);

        return $this->result('Cari satırları kaynak belgelerle birebir', (int) DB::scalar('select count(*) from ledger_entries'), $failures);
    }

    /**
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function postingsCarryTheirModuleAndSite(): array
    {
        $rows = DB::select(<<<'SQL'
            select le.source_type st, cast(le.source_id as text) sid, le.module, cast(le.site_id as text) site, cast(le.bank_id as text) bank,
                   x.module expected_module, x.site expected_site, x.bank expected_bank
            from ledger_entries le
            join (
                select 'haul' st, cast(id as text) sid, 'excavation' module, cast(site_id as text) site, null bank from hauls
                union all select 'machine_work', cast(id as text), 'excavation', cast(site_id as text), null from machine_works
                union all select 'fuel_receipt', cast(id as text), 'fuel', cast(site_id as text), null from fuel_receipts
                union all select 'fuel_tank_fill', cast(id as text), 'fuel', null, null from fuel_tank_fills
                union all select 'shipment', cast(id as text), 'transport', null, null from shipments
                union all select 'company_transaction', cast(id as text), case when role = 'fuel' then 'fuel' else 'general' end, cast(site_id as text), cast(bank_id as text) from company_transactions
                union all select 'dump_site_payment', cast(id as text), 'excavation', null, cast(bank_id as text) from dump_site_payments
                union all select 'vehicle_payment', cast(id as text), 'general', null, cast(bank_id as text) from vehicle_payments
                union all select 'vehicle_receivable', cast(id as text), 'general', null, cast(bank_id as text) from vehicle_receivables
                union all select 'party_payment', cast(id as text), 'general', null, cast(bank_id as text) from party_payments
                union all select 'vehicle_ownership', cast(id as text), 'general', null, null from vehicle_ownerships
                union all select 'employee_salary', cast(id as text), 'general', null, null from employee_salaries
                union all select 'employee_bonus', cast(id as text), 'general', null, null from employee_bonuses
                union all select 'employee_payment', cast(id as text), 'general', null, cast(bank_id as text) from employee_payments
                union all select 'opening_balance', cast(id as text), 'general', null, null from opening_balances
                union all select 'party_transfer', cast(id as text), 'general', null, null from party_transfers
                union all select 'site_bill', cast(id as text), 'excavation', cast(site_id as text), null from site_bills
                union all select 'site_transaction', cast(id as text), 'excavation', cast(site_id as text), cast(bank_id as text) from site_transactions
                union all select 'financial_instrument_movement', cast(id as text), 'general', null, null from financial_instrument_movements
            ) x on x.st = le.source_type and x.sid = cast(le.source_id as text)
            where le.reversal_of is null and not exists (select 1 from ledger_entries r where r.reversal_of = le.id)
              and (le.module <> x.module
                   or coalesce(cast(le.site_id as text), '') <> coalesce(x.site, '')
                   or coalesce(cast(le.bank_id as text), '') <> coalesce(x.bank, ''))
        SQL);

        $failures = array_map(fn (object $row): string => sprintf(
            '%s %s: cari %s/%s/%s, belge %s/%s/%s',
            $row->st,
            $row->sid,
            $row->module,
            $row->site ?? '—',
            $row->bank ?? '—',
            $row->expected_module,
            $row->expected_site ?? '—',
            $row->expected_bank ?? '—',
        ), $rows);

        return $this->result('Cari satırlarının modülü, şantiyesi ve hesabı belgeyle aynı', (int) DB::scalar('select count(*) from ledger_entries'), $failures);
    }

    /**
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function workSitsWithTheOwnerOfItsDate(): array
    {
        $failures = [];
        $checked = 0;

        foreach (['hauls' => 'hauled_at', 'shipments' => 'shipped_on', 'fuel_receipts' => 'issued_on', 'machine_works' => 'worked_on', 'vehicle_receivables' => 'occurred_on'] as $table => $column) {
            $day = "substr(cast(x.{$column} as text), 1, 10)";
            $checked += (int) DB::table($table)->whereNull('deleted_at')->count();

            foreach (DB::select(<<<SQL
                select x.id from {$table} x where x.deleted_at is null and not exists (
                    select 1 from vehicle_ownerships o where o.vehicle_id = x.vehicle_id and o.party_id = x.party_id
                      and substr(cast(o.started_on as text), 1, 10) <= {$day}
                      and (o.ended_on is null or substr(cast(o.ended_on as text), 1, 10) >= {$day}))
            SQL) as $row) {
                $failures[] = "{$table} {$row->id}: o tarihteki araç sahibinin carisinde değil";
            }
        }

        return $this->result('İş belgeleri o tarihteki araç sahibinde', $checked, $failures);
    }

    /**
     * A plate payment stays with whoever was paid, even after a back-dated transfer, but that party
     * must have owned the plate at some point; otherwise the money is invisible on every plate report.
     *
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function platePaymentsSitWithAnOwner(): array
    {
        $rows = DB::select(<<<'SQL'
            select cast(x.id as text) id, v.plate, p.name from vehicle_payments x
            join vehicles v on v.id = x.vehicle_id
            join parties p on p.id = x.party_id
            where x.deleted_at is null
              and not exists (select 1 from vehicle_ownerships o where o.vehicle_id = x.vehicle_id and o.party_id = x.party_id)
        SQL);

        return $this->result(
            'Plaka ödemeleri plakanın sahiplerinden birinde',
            (int) DB::table('vehicle_payments')->whereNull('deleted_at')->count(),
            array_map(fn (object $row): string => "{$row->plate}: ödeme {$row->id}, plakanın hiç sahibi olmamış {$row->name} carisinde", $rows),
        );
    }

    /**
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function transfersCloseThePreviousOwner(): array
    {
        $ownerships = VehicleOwnership::query()->whereNotNull('carried_from_party_id')->with(['vehicle', 'carriedFrom'])->get();
        $failures = [];

        foreach ($ownerships as $ownership) {
            // An owner who takes the plate back holds its balance again; that period is checked at its own transfer.
            $ownsItAgain = VehicleOwnership::query()
                ->where('vehicle_id', $ownership->vehicle_id)
                ->where('party_id', $ownership->carried_from_party_id)
                ->where('started_on', '>', $ownership->started_on)
                ->exists();

            if ($ownsItAgain) {
                continue;
            }

            $left = $this->accounts->balance($ownership->vehicle_id, $ownership->carried_from_party_id);

            if ($left !== 0.0) {
                $failures[] = sprintf('%s: eski sahip %s plakada hâlâ %s TL', $ownership->vehicle->plate, $ownership->carriedFrom->name, number_format($left, 2, ',', '.'));
            }
        }

        return $this->result('Devirde eski sahibin plaka bakiyesi kapalı', $ownerships->count(), $failures);
    }

    /**
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function ownerBalanceReportMatchesCari(): array
    {
        $cari = collect(DB::select(<<<'SQL'
            select le.party_id, sum(le.credit - le.debit) net from ledger_entries le
            where le.party_id in (select party_id from vehicle_ownerships)
              and le.source_type not in ('company_transaction', 'dump_site_payment', 'employee_salary', 'employee_bonus', 'employee_payment', 'opening_balance', 'party_transfer', 'site_bill', 'site_transaction', 'fuel_tank_fill', 'shipment', 'financial_instrument_movement')
              and not (le.source_type = 'haul' and not exists (select 1 from hauls h where h.id = le.source_id and h.party_id = le.party_id))
              and not (le.source_type = 'fuel_receipt' and le.vehicle_id is null)
            group by le.party_id
        SQL))->mapWithKeys(fn (object $row) => [$row->party_id => round((float) $row->net, 2)]);

        $failures = [];
        $report = $this->ownerBalances->ownerTotals(null, null)->keyBy('party_id');

        foreach (DB::table('vehicle_ownerships')->distinct()->pluck('party_id') as $partyId) {
            $row = $report->get($partyId);
            $balance = $row ? round(
                $row->credit_hauls + $row->credit_machine_works + $row->credit_receivables + $row->credit_transfers
                - $row->debit_payments - $row->debit_fuel - $row->debit_fines - $row->debit_transfers - $row->debit_owner_payments,
                2,
            ) : 0.0;
            $expected = $cari->get($partyId, 0.0);

            if (abs($balance - $expected) >= 0.005) {
                $failures[] = sprintf('%s: rapor %s, cari %s', DB::table('parties')->where('id', $partyId)->value('name'), $balance, $expected);
            }
        }

        return $this->result('Plaka Sahibi Bakiye = cari ekstre', DB::table('vehicle_ownerships')->distinct()->count('party_id'), $failures);
    }

    /**
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function plateBalanceReportMatchesCari(): array
    {
        // An owner who is also the paid operator of their own machine gets the operator share on the
        // same account and plate; that share is the operator's labour, not the plate's, so it is taken out.
        $cari = collect(DB::select(<<<'SQL'
            select x.vehicle_id, sum(x.net) net from (
                select le.vehicle_id, le.credit - le.debit net from ledger_entries le
                where le.vehicle_id is not null and (
                    (le.source_type = 'haul' and exists (select 1 from hauls h where h.id = le.source_id and h.party_id = le.party_id))
                    or (le.source_type = 'machine_work' and exists (select 1 from machine_works m where m.id = le.source_id and m.party_id = le.party_id))
                    or le.source_type in ('fuel_receipt', 'vehicle_payment', 'vehicle_receivable', 'vehicle_ownership'))
                union all
                select vehicle_id, -operator_amount from machine_works
                where deleted_at is null and operator_amount is not null and operator_id = party_id
            ) x
            group by x.vehicle_id
        SQL))->mapWithKeys(fn (object $row) => [$row->vehicle_id => round((float) $row->net, 2)]);

        $failures = [];
        $report = $this->vehicleBalances->vehicleTotals(null, null)->keyBy('vehicle_id');

        foreach (DB::table('vehicles')->pluck('plate', 'id') as $vehicleId => $plate) {
            $row = $report->get($vehicleId);
            $balance = $row ? round(
                $row->credit_hauls + $row->credit_machine_works + $row->credit_receivables + $row->credit_transfers
                - $row->debit_payments - $row->debit_fuel - $row->debit_fines - $row->debit_transfers,
                2,
            ) : 0.0;
            $expected = $cari->get($vehicleId, 0.0);

            if (abs($balance - $expected) >= 0.005) {
                $failures[] = "{$plate}: rapor {$balance}, cari {$expected}";
            }
        }

        return $this->result('Plaka Bakiye = carideki araç hareketleri', DB::table('vehicles')->count(), $failures);
    }

    /**
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function cashDrawerMatchesCashMovements(): array
    {
        $today = Carbon::today();
        $report = $this->cash->report(['period' => 'range', 'from' => '2000-01-01', 'to' => $today->toDateString()]);
        $transfers = fn (string $side): float => (float) DB::table('account_transfers as t')
            ->join('banks as b', 'b.id', '=', "t.{$side}")
            ->whereNull('t.deleted_at')
            ->where('b.type', AccountType::Cash->value)
            ->where('t.occurred_on', '<=', $today->toDateString())
            ->sum('t.amount');

        $expected = round(
            (float) DB::table('cash_entries as t')
                ->leftJoin('banks as b', 'b.id', '=', 't.bank_id')
                ->whereNull('t.deleted_at')
                ->where(fn ($query) => $query->whereNull('t.bank_id')->orWhere('b.type', AccountType::Cash->value))
                ->where('t.occurred_on', '<=', $today->toDateString())
                ->sum(DB::raw('t.debit - t.credit'))
            + (float) DB::table('banks')
                ->whereNull('deleted_at')
                ->where('type', AccountType::Cash->value)
                ->where(fn ($query) => $query->whereNull('opened_on')->orWhere('opened_on', '<=', $today->toDateString()))
                ->sum('opening_balance')
            + $transfers('to_bank_id') - $transfers('from_bank_id')
            + (float) DB::table('site_transactions')->whereNull('deleted_at')->where('payment_method', 'cash')->where('occurred_on', '<=', $today->toDateString())->sum(DB::raw('credit - debit'))
            + (float) DB::table('ledger_entries')->where('payment_method', 'cash')->where('occurred_on', '<=', $today->toDateString())
                ->whereIn('source_type', ['vehicle_payment', 'vehicle_receivable', 'party_payment', 'dump_site_payment', 'company_transaction'])
                ->sum(DB::raw('credit - debit'))
            - (float) DB::table('employee_payments')->whereNull('deleted_at')->where('payment_method', 'cash')->where('occurred_on', '<=', $today->toDateString())->sum('amount')
            - (float) DB::table('other_payments')->whereNull('deleted_at')->where('payment_method', 'cash')->where('occurred_on', '<=', $today->toDateString())->sum('amount')
            + (float) DB::table('financial_instrument_movements as m')
                ->join('financial_instruments as i', 'i.id', '=', 'm.financial_instrument_id')
                ->join('banks as b', 'b.id', '=', 'm.bank_id')
                ->whereNull('m.deleted_at')
                ->where('b.type', 'cash')
                ->where('m.occurred_on', '<=', $today->toDateString())
                ->sum(DB::raw("case when m.type = 'collect' then i.amount when m.type = 'pay' then -i.amount else 0 end")),
            2,
        );

        $failures = abs($report['closing'] - $expected) >= 0.005
            ? ["Kasa raporu {$report['closing']}, nakit hareketler {$expected}"]
            : [];

        return $this->result('Kasa kapanışı = tüm nakit hareketler', 1, $failures);
    }

    /**
     * The cash report reads money by its payment method, the account balances by the account it
     * names. Both must land on the same drawer: cash that names no account (older records) is the
     * only allowed difference; a cash payment on a bank account or a transfer payment on a cash box
     * is listed by its document.
     *
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function cashDrawerMatchesCashAccounts(): array
    {
        $today = Carbon::today();
        $day = $today->toDateString();
        $cash = AccountType::Cash->value;
        $report = $this->cash->report(['period' => 'range', 'from' => '2000-01-01', 'to' => $day])['closing'];

        $boxes = Bank::withTrashed()->where('type', $cash)->pluck('id')->all();
        $accounts = array_sum($this->banks->balances($boxes, $today));

        $unassigned = (float) DB::table('cash_entries')->whereNull('deleted_at')->whereNull('bank_id')->where('occurred_on', '<=', $day)->sum(DB::raw('debit - credit'))
            + (float) DB::table('site_transactions')->whereNull('deleted_at')->whereNull('bank_id')->where('payment_method', 'cash')->where('occurred_on', '<=', $day)->sum(DB::raw('credit - debit'))
            + (float) DB::table('ledger_entries')->whereNull('bank_id')->where('payment_method', 'cash')->where('occurred_on', '<=', $day)
                ->whereIn('source_type', ['vehicle_payment', 'vehicle_receivable', 'party_payment', 'dump_site_payment', 'company_transaction'])
                ->sum(DB::raw('credit - debit'))
            - (float) DB::table('employee_payments')->whereNull('deleted_at')->whereNull('bank_id')->where('payment_method', 'cash')->where('occurred_on', '<=', $day)->sum('amount')
            - (float) DB::table('other_payments')->whereNull('deleted_at')->where('payment_method', 'cash')->where('occurred_on', '<=', $day)->sum('amount');

        $expected = round($accounts + $unassigned, 2);
        $failures = [];

        if (abs($report - $expected) >= 0.005) {
            $failures[] = sprintf(
                'Kasa raporu %s, kasa hesapları %s + hesapsız nakit %s',
                number_format($report, 2, ',', '.'),
                number_format($accounts, 2, ',', '.'),
                number_format($unassigned, 2, ',', '.'),
            );
        }

        $mismatched = DB::select(<<<'SQL'
            select x.st, x.sid, x.method, b.name account, b.type from (
                select source_type st, cast(source_id as text) sid, payment_method method, bank_id from ledger_entries le
                where le.bank_id is not null and le.source_type <> 'site_transaction'
                  and le.reversal_of is null and not exists (select 1 from ledger_entries r where r.reversal_of = le.id)
                union all select 'site_transaction', cast(id as text), payment_method, bank_id from site_transactions where deleted_at is null and bank_id is not null
            ) x
            join banks b on b.id = x.bank_id
            where (coalesce(x.method, '') = 'cash') <> (b.type = 'cash')
        SQL);

        foreach ($mismatched as $row) {
            $failures[] = sprintf('%s %s: ödeme tipi %s, hesap %s (%s)', $row->st, $row->sid, $row->method ?? 'yok', $row->account, AccountType::from($row->type)->label());
        }

        return $this->result('Kasa raporu = kasa hesaplarının bakiyesi', count($boxes), $failures);
    }

    /**
     * A salary is owed on the last day of its period month.
     */
    private function monthEnd(string $column): string
    {
        return DB::getDriverName() === 'pgsql'
            ? "to_char(date_trunc('month', {$column}) + interval '1 month - 1 day', 'YYYY-MM-DD')"
            : "date({$column}, 'start of month', '+1 month', '-1 day')";
    }

    /**
     * @param  list<string>  $failures
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function result(string $check, int $checked, array $failures): array
    {
        return ['check' => $check, 'checked' => $checked, 'failures' => $failures];
    }
}
