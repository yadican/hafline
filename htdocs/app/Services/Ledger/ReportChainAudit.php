<?php

namespace App\Services\Ledger;

use App\Repositories\Reports\CompanyBalanceReportRepository;
use App\Repositories\Reports\EmployeeMonthlyReportRepository;
use App\Services\Reports\CashMovementReportService;
use App\Services\Reports\CompanyLedgerReportService;
use App\Services\Reports\DumpSitePaymentReportService;
use App\Services\Reports\EmployeeAnnualReportService;
use App\Services\Reports\OwnerBalanceReportService;
use App\Services\Reports\OwnerMonthlyReportService;
use App\Services\Reports\SiteExpenseReportService;
use App\Services\Reports\VehicleBalanceReportService;
use App\Services\Reports\VehicleMonthlyReportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Walks every report that shows an opening (devir) month by month, from the first movement to the
 * current month, and checks that each month opens where the previous one closed and that opening,
 * period debit and credit add up to the closing shown.
 */
class ReportChainAudit
{
    public function __construct(
        private VehicleMonthlyReportService $vehicleMonthly,
        private OwnerMonthlyReportService $ownerMonthly,
        private DumpSitePaymentReportService $dumpPayments,
        private CompanyLedgerReportService $companyLedger,
        private CompanyBalanceReportRepository $companyBalance,
        private SiteExpenseReportService $siteExpense,
        private CashMovementReportService $cash,
        private EmployeeMonthlyReportRepository $employeeMonthly,
        private EmployeeAnnualReportService $employeeAnnual,
        private VehicleBalanceReportService $vehicleBalance,
        private OwnerBalanceReportService $ownerBalance,
    ) {}

    /**
     * @return list<array{check: string, checked: int, failures: list<string>}>
     */
    public function run(): array
    {
        $months = $this->months();

        return [
            $this->vehicleMonthly($months),
            $this->ownerMonthly($months),
            $this->dumpSitePayments($months),
            $this->companyLedger($months),
            $this->companyBalance($months),
            $this->balanceReport('Plaka Bakiye: devir + dönem = bakiye', $this->vehicleBalance, 'plate', $months),
            $this->balanceReport('Plaka Sahibi Bakiye: devir + dönem = bakiye', $this->ownerBalance, 'party_id', $months),
            $this->siteExpense($months),
            $this->cashMovements($months),
            $this->employeeMonthly($months),
            $this->employeeAnnual(),
        ];
    }

    /**
     * @param  list<Carbon>  $months
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function vehicleMonthly(array $months): array
    {
        $checks = new ChainChecks;

        foreach (DB::table('vehicles')->pluck('plate', 'id') as $vehicleId => $plate) {
            $previous = null;

            foreach ($months as $month) {
                $report = $this->vehicleMonthly->report(['vehicle' => $vehicleId, 'period' => 'month', 'year' => $month->year, 'month' => $month->month]);
                $label = "{$plate} {$month->format('m.Y')}";
                $carryover = collect($report['rows'])->firstWhere('type', 'carryover');

                $checks->opening($label, $previous, $this->net($carryover));
                $checks->rows($label, $report['rows'], $report['total_debit'], $report['total_credit']);
                $checks->closing($label, $report['total_credit'] - $report['total_debit'], $report['balance']);
                $previous = $report['balance'];
            }
        }

        return $checks->result('Plaka Aylık: devir, satırlar, bakiye');
    }

    /**
     * @param  list<Carbon>  $months
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function ownerMonthly(array $months): array
    {
        $checks = new ChainChecks;

        foreach (DB::table('vehicle_ownerships')->distinct()->pluck('party_id') as $partyId) {
            $name = DB::table('parties')->where('id', $partyId)->value('name');
            $previous = null;

            foreach ($months as $month) {
                $report = $this->ownerMonthly->report(['owner' => $partyId, 'period' => 'month', 'year' => $month->year, 'month' => $month->month]);
                $label = "{$name} {$month->format('m.Y')}";
                $rows = [...collect($report['vehicles'])->flatMap(fn (array $vehicle) => $vehicle['rows'])->all(), ...$report['operator_works'], ...$report['owner_payments'], $report['carryover']];

                $checks->opening($label, $previous, $this->net($report['carryover']));
                $checks->rows($label, $rows, $report['total_debit'], $report['total_credit']);
                $checks->closing($label, $report['total_credit'] - $report['total_debit'], $report['balance']);
                $previous = $report['balance'];
            }
        }

        return $checks->result('Plaka Sahibi Aylık: devir, satırlar, bakiye');
    }

    /**
     * @param  list<Carbon>  $months
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function dumpSitePayments(array $months): array
    {
        $checks = new ChainChecks;

        foreach (DB::table('dump_sites')->whereNotNull('party_id')->pluck('name', 'id') as $dumpSiteId => $name) {
            $previous = null;

            foreach ($months as $month) {
                $report = $this->dumpPayments->report(['dump_site' => $dumpSiteId, 'period' => 'month', 'year' => $month->year, 'month' => $month->month]);
                $label = "{$name} {$month->format('m.Y')}";

                $checks->opening($label, $previous, $report['opening']);
                $checks->closing($label, $report['opening'] + $report['total_dump_amount'] - $report['total_payment_amount'], $report['balance']);
                $checks->closing($label.' (dökümler)', collect($report['dump_rows'])->sum('amount'), $report['total_dump_amount']);
                $checks->closing($label.' (ödemeler)', collect($report['payments'])->sum('amount'), $report['total_payment_amount']);
                $previous = $report['balance'];
            }
        }

        return $checks->result('Döküm Yeri Ödeme: devir, döküm, ödeme, bakiye');
    }

    /**
     * @param  list<Carbon>  $months
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function companyLedger(array $months): array
    {
        $checks = new ChainChecks;

        foreach (DB::table('company_transactions')->whereNull('deleted_at')->select('role', 'party_id')->distinct()->get() as $pair) {
            $name = DB::table('parties')->where('id', $pair->party_id)->value('name');
            $previous = null;

            foreach ($months as $month) {
                $group = $this->companyLedger->report(['period' => 'month', 'year' => $month->year, 'month' => $month->month, 'role' => $pair->role, 'party' => $pair->party_id])['groups'][0] ?? null;
                $label = "{$pair->role} {$name} {$month->format('m.Y')}";
                $opening = $group['opening'] ?? 0.0;
                $closing = $group['closing'] ?? 0.0;

                $checks->opening($label, $previous, $opening);

                if ($group !== null) {
                    $checks->closing($label, $opening + $group['total_debit'] - $group['total_credit'], $closing);
                    $checks->closing($label.' (satırlar)', collect($group['lines'])->sum('debit') - collect($group['lines'])->sum('credit'), $group['total_debit'] - $group['total_credit']);

                    if ($group['lines'] !== []) {
                        $checks->closing($label.' (yürüyen bakiye)', $closing, end($group['lines'])['balance']);
                    }
                }

                $previous = $closing;
            }
        }

        return $checks->result('Firma Cari: devir, fişler, yürüyen bakiye, kapanış');
    }

    /**
     * @param  list<Carbon>  $months
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function companyBalance(array $months): array
    {
        $checks = new ChainChecks;

        foreach (CompanyBalanceReportRepository::reportRoles() as $role) {
            $previous = [];

            foreach ($months as $month) {
                $rows = $this->companyBalance->partyTotals($role, $month, $month->copy()->addMonth())->keyBy('party_id');

                foreach ($rows as $partyId => $row) {
                    $checks->closing(
                        "{$role->value} {$row->name} {$month->format('m.Y')}",
                        ($previous[$partyId] ?? 0.0) + (float) $row->period_debit - (float) $row->period_credit,
                        (float) $row->closing,
                    );
                }

                foreach ($previous as $partyId => $closing) {
                    if (! $rows->has($partyId)) {
                        $checks->closing("{$role->value} {$partyId} {$month->format('m.Y')} (listeden düştü)", $closing, 0.0);
                    }
                }

                $previous = $rows->map(fn (object $row) => round((float) $row->closing, 2))->all();
            }
        }

        return $checks->result('Firma Bakiye: önceki bakiye + borç − alacak = bakiye');
    }

    /**
     * Unfiltered, so every movement counts; a row with a balance must still be listed next month.
     *
     * @param  list<Carbon>  $months
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function balanceReport(string $check, VehicleBalanceReportService|OwnerBalanceReportService $service, string $key, array $months): array
    {
        $checks = new ChainChecks;
        $previous = [];

        foreach ($months as $month) {
            $report = $service->report(['period' => 'month', 'year' => $month->year, 'month' => $month->month, 'site' => '', 'fuel_station' => '']);
            $rows = collect($report['rows'])->keyBy($key);

            foreach ($rows as $name => $row) {
                $label = ($row['name'] ?? $row['plate']).' '.$month->format('m.Y');
                $checks->closing($label.' devir', $previous[$name] ?? 0.0, $row['opening']);
                $checks->closing($label, $row['opening'] + $row['debit'] - $row['credit'] - $row['cash'], $row['balance']);
            }

            foreach ($previous as $name => $balance) {
                if (! $rows->has($name)) {
                    $checks->closing("{$name} {$month->format('m.Y')} (listeden düştü)", $balance, 0.0);
                }
            }

            $previous = $rows->map(fn (array $row) => $row['balance'])->all();
        }

        return $checks->result($check);
    }

    /**
     * @param  list<Carbon>  $months
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function siteExpense(array $months): array
    {
        $checks = new ChainChecks;

        $siteIds = DB::table('site_transactions')->distinct()->pluck('site_id')
            ->merge(DB::table('stock_usages')->whereNotNull('site_id')->distinct()->pluck('site_id'))
            ->unique()
            ->values();

        foreach ($siteIds as $siteId) {
            $name = DB::table('sites')->where('id', $siteId)->value('name');
            $previous = null;

            foreach ($months as $month) {
                $report = $this->siteExpense->report(['site' => $siteId, 'period' => 'month', 'year' => $month->year, 'month' => $month->month]);
                $label = "{$name} {$month->format('m.Y')}";

                $checks->opening($label, $previous, $report['opening']);
                $checks->closing($label.' (satırlar)', collect($report['lines'])->sum('debit') - collect($report['lines'])->sum('credit'), $report['total_debit'] - $report['total_credit']);

                if ($report['lines'] !== []) {
                    $checks->closing($label.' (yürüyen bakiye)', $report['closing'], end($report['lines'])['balance']);
                }

                $previous = $report['closing'];
            }
        }

        return $checks->result('Şantiye Gider: devir, satırlar, yürüyen bakiye');
    }

    /**
     * @param  list<Carbon>  $months
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function cashMovements(array $months): array
    {
        $checks = new ChainChecks;
        $previous = null;

        foreach ($months as $month) {
            $report = $this->cash->report(['period' => 'month', 'year' => $month->year, 'month' => $month->month]);
            $label = 'Kasa '.$month->format('m.Y');

            $checks->opening($label, $previous, $report['opening']);
            $checks->closing($label.' (satırlar)', collect($report['lines'])->sum('giris') - collect($report['lines'])->sum('cikis'), $report['total_giris'] - $report['total_cikis']);

            if ($report['lines'] !== []) {
                $checks->closing($label.' (yürüyen bakiye)', $report['closing'], end($report['lines'])['balance']);
            }

            $previous = $report['closing'];
        }

        return $checks->result('Kasa Hareketleri: devir, giriş, çıkış, yürüyen bakiye');
    }

    /**
     * @param  list<Carbon>  $months
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function employeeMonthly(array $months): array
    {
        $checks = new ChainChecks;
        $previous = [];

        foreach ($months as $month) {
            $rows = $this->employeeMonthly->rows($month->toDateString(), $month->copy()->endOfMonth()->toDateString())->keyBy('id');
            $current = [];

            foreach ($rows as $id => $row) {
                $remaining = round($row->earned + $row->bonus + $row->carry - $row->paid, 2);
                $checks->closing("{$row->name} {$month->format('m.Y')}", $previous[$id] ?? 0.0, $row->carry);
                $current[$id] = $remaining;
            }

            foreach ($previous as $id => $remaining) {
                if (! $rows->has($id)) {
                    $checks->closing("{$id} {$month->format('m.Y')} (listeden düştü)", $remaining, 0.0);
                }
            }

            $previous = $current;
        }

        return $checks->result('Personel Aylık: önceki kalan = devir');
    }

    /**
     * @return array{check: string, checked: int, failures: list<string>}
     */
    private function employeeAnnual(): array
    {
        $checks = new ChainChecks;
        $first = (int) substr((string) DB::table('employee_salaries')->min('period'), 0, 4) ?: (int) now()->year;

        foreach (DB::table('employees')->pluck('id') as $employeeId) {
            $previous = null;

            for ($year = $first; $year <= (int) now()->year; $year++) {
                $report = $this->employeeAnnual->report(['employee_id' => $employeeId, 'year' => $year]);

                if ($report === null) {
                    continue;
                }

                $label = "{$report['employee']['name']} {$year}";
                $checks->opening($label, $previous, $report['opening']);
                $checks->closing($label, $report['opening'] + $report['totals']['salary'] + $report['totals']['bonus'] - $report['totals']['payment'], $report['totals']['closing']);
                $previous = $report['totals']['closing'];
            }
        }

        return $checks->result('Personel Yıllık: geçmiş yıllar devri, maaş, prim, ödeme, kapanış');
    }

    /**
     * @param  array{debit: float|null, credit: float|null}|null  $row
     */
    private function net(?array $row): float
    {
        return (float) ($row['credit'] ?? 0) - (float) ($row['debit'] ?? 0);
    }

    /**
     * From the month of the first movement anywhere to the current month.
     *
     * @return list<Carbon>
     */
    private function months(): array
    {
        $first = collect([
            DB::table('hauls')->min('hauled_at'),
            DB::table('ledger_entries')->min('occurred_on'),
            DB::table('cash_entries')->min('occurred_on'),
            DB::table('site_transactions')->min('occurred_on'),
            DB::table('stock_usages')->min('used_on'),
            DB::table('employee_payments')->min('occurred_on'),
            DB::table('employee_salaries')->min('period'),
        ])->filter()->map(fn ($date) => Carbon::parse($date))->min() ?? now();

        $months = [];

        for ($month = $first->copy()->startOfMonth(); $month->lte(now()->startOfMonth()); $month->addMonth()) {
            $months[] = $month->copy();
        }

        return $months;
    }
}
