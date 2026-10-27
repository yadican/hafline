<?php

namespace App\Services\Reports;

use App\Enums\CariEntryKind;
use App\Enums\PartyRole;
use App\Repositories\Reports\CompanyBalanceReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyBalanceReportService extends Service
{
    public function __construct(private CompanyBalanceReportRepository $reports) {}

    /**
     * @return array{roles: array<string, string>, periods: array<string, string>, years: array<int, string>, months: array<int, string>}
     */
    public function formOptions(): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        $roles = [];
        foreach (CompanyBalanceReportRepository::reportRoles() as $role) {
            $roles[$role->value] = CariEntryKind::from($role->value)->label();
        }

        return [
            'roles' => $roles,
            'periods' => VehicleBalanceReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, role: string}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     role: string,
     *     role_label: string,
     *     period: 'month'|'range',
     *     period_label: string,
     *     year: ?int,
     *     month: ?int,
     *     month_label: ?string,
     *     from: string,
     *     to: string,
     *     rows: list<array{name: string, opening: float, credit: float, debit: float, balance: float}>,
     *     total_opening: float,
     *     total_credit: float,
     *     total_debit: float,
     *     total_balance: float,
     * }
     */
    public function report(array $filters): array
    {
        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);
        $role = PartyRole::from($filters['role']);
        $roleLabel = CariEntryKind::from($role->value)->label();

        $rows = [];
        $totalOpening = 0.0;
        $totalCredit = 0.0;
        $totalDebit = 0.0;
        $totalBalance = 0.0;

        foreach ($this->reports->partyTotals($role, $from, $toExclusive) as $row) {
            $opening = round((float) $row->opening, 2);
            $credit = round((float) $row->period_credit, 2);
            $debit = round((float) $row->period_debit, 2);
            $balance = round((float) $row->closing, 2);

            $rows[] = [
                'name' => (string) $row->name,
                'opening' => $opening,
                'credit' => $credit,
                'debit' => $debit,
                'balance' => $balance,
            ];

            $totalOpening += $opening;
            $totalCredit += $credit;
            $totalDebit += $debit;
            $totalBalance += $balance;
        }

        return [
            'title' => 'Firma Bakiye Raporu',
            'subtitle' => $roleLabel.' · '.$meta['label'],
            'role' => $role->value,
            'role_label' => $roleLabel,
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'rows' => $rows,
            'total_opening' => round($totalOpening, 2),
            'total_credit' => round($totalCredit, 2),
            'total_debit' => round($totalDebit, 2),
            'total_balance' => round($totalBalance, 2),
        ];
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, role: string}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);

        $filename = $report['period'] === 'month'
            ? sprintf('firma-bakiye-%s-%04d-%02d.csv', $report['role'], $report['year'], $report['month'])
            : sprintf('firma-bakiye-%s-%s_%s.csv', $report['role'], $report['from'], $report['to']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Firma',
                'Devir',
                'Alacak',
                'Borç',
                'Bakiye',
            ], ';');

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['name'],
                    number_format($row['opening'], 2, ',', '.'),
                    number_format($row['credit'], 2, ',', '.'),
                    number_format($row['debit'], 2, ',', '.'),
                    number_format($row['balance'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($handle, [
                'Toplam',
                number_format($report['total_opening'], 2, ',', '.'),
                number_format($report['total_credit'], 2, ',', '.'),
                number_format($report['total_debit'], 2, ',', '.'),
                number_format($report['total_balance'], 2, ',', '.'),
            ], ';');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     * @return array{0: Carbon, 1: Carbon, 2: array{period: 'month'|'range', label: string, year: ?int, month: ?int, month_label: ?string, from: string, to: string}}
     */
    private function resolvePeriod(array $filters): array
    {
        if (($filters['period'] ?? 'month') === 'range') {
            $from = Carbon::parse($filters['from'])->startOfDay();
            $toInclusive = Carbon::parse($filters['to'])->startOfDay();

            return [
                $from,
                $toInclusive->copy()->addDay(),
                [
                    'period' => 'range',
                    'label' => $from->format('d.m.Y').' – '.$toInclusive->format('d.m.Y'),
                    'year' => null,
                    'month' => null,
                    'month_label' => null,
                    'from' => $from->toDateString(),
                    'to' => $toInclusive->toDateString(),
                ],
            ];
        }

        $year = (int) $filters['year'];
        $month = (int) $filters['month'];
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $monthLabel = SiteExitReportService::MONTHS[$month];

        return [
            $from,
            $from->copy()->addMonth(),
            [
                'period' => 'month',
                'label' => "{$year} {$monthLabel}",
                'year' => $year,
                'month' => $month,
                'month_label' => $monthLabel,
                'from' => $from->toDateString(),
                'to' => $from->copy()->endOfMonth()->toDateString(),
            ],
        ];
    }
}
