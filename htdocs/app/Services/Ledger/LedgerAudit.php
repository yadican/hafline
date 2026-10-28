<?php

namespace App\Services\Ledger;

use App\Models\VehicleOwnership;
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
        union all select 'shipment', cast(id as text), cast(driver_party_id as text), substr(cast(shipped_on as text), 1, 10), 0, driver_amount from shipments where deleted_at is null and driver_party_id is not null and driver_amount <> 0
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
            $this->transfersCloseThePreviousOwner(),
            $this->ownerBalanceReportMatchesCari(),
            $this->plateBalanceReportMatchesCari(),
            $this->cashDrawerMatchesCashMovements(),
        ];
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
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function transfersCloseThePreviousOwner(): array
    {
        $ownerships = VehicleOwnership::query()->whereNotNull('carried_from_party_id')->with(['vehicle', 'carriedFrom'])->get();
        $failures = [];

        foreach ($ownerships as $ownership) {
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
              and le.source_type not in ('company_transaction', 'dump_site_payment', 'employee_salary', 'employee_bonus', 'employee_payment', 'opening_balance', 'party_transfer', 'site_bill', 'site_transaction', 'fuel_tank_fill', 'shipment')
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

        $expected = round(
            (float) DB::table('cash_entries')->whereNull('deleted_at')->where('occurred_on', '<=', $today->toDateString())->sum(DB::raw('debit - credit'))
            + (float) DB::table('site_transactions')->whereNull('deleted_at')->where('payment_method', 'cash')->where('occurred_on', '<=', $today->toDateString())->sum(DB::raw('credit - debit'))
            + (float) DB::table('ledger_entries')->where('payment_method', 'cash')->where('occurred_on', '<=', $today->toDateString())
                ->whereIn('source_type', ['vehicle_payment', 'vehicle_receivable', 'party_payment', 'dump_site_payment', 'company_transaction'])
                ->sum(DB::raw('credit - debit'))
            - (float) DB::table('employee_payments')->whereNull('deleted_at')->where('payment_method', 'cash')->where('occurred_on', '<=', $today->toDateString())->sum('amount')
            - (float) DB::table('other_payments')->whereNull('deleted_at')->where('payment_method', 'cash')->where('occurred_on', '<=', $today->toDateString())->sum('amount'),
            2,
        );

        $failures = abs($report['closing'] - $expected) >= 0.005
            ? ["Kasa raporu {$report['closing']}, nakit hareketler {$expected}"]
            : [];

        return $this->result('Kasa kapanışı = tüm nakit hareketler', 1, $failures);
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
