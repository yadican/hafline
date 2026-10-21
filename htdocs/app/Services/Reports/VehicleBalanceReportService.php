<?php

namespace App\Services\Reports;

use App\Repositories\Reports\VehicleBalanceReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleBalanceReportService extends Service
{
    /** @var array<string, string> */
    public const PERIODS = [
        'month' => 'Ay',
        'range' => 'Tarih aralığı',
        'all' => 'Tüm zamanlar',
    ];

    public function __construct(private VehicleBalanceReportRepository $reports) {}

    /**
     * @return array{sites: array<string, string>, stations: array<string, string>, periods: array<string, string>, years: array<int, string>, months: array<int, string>}
     */
    public function formOptions(): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            'sites' => $this->reports->sites(),
            'stations' => $this->reports->fuelStations(),
            'periods' => self::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{period: 'month'|'range'|'all', year?: int, month?: int, from?: string, to?: string, site?: string, fuel_station?: string}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     period: 'month'|'range'|'all',
     *     period_label: string,
     *     year: ?int,
     *     month: ?int,
     *     month_label: ?string,
     *     from: ?string,
     *     to: ?string,
     *     filtered: bool,
     *     balance_label: string,
     *     rows: list<array{plate: string, credit: float, cash: float, debit: float, balance: float}>,
     *     total_credit: float,
     *     total_cash: float,
     *     total_debit: float,
     *     total_balance: float,
     * }
     */
    public function report(array $filters): array
    {
        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);
        $siteId = $filters['site'] ?? null;
        $fuelStationId = $filters['fuel_station'] ?? null;
        $filtered = filled($siteId) || filled($fuelStationId);

        $rows = [];
        $totalCredit = 0.0;
        $totalCash = 0.0;
        $totalDebit = 0.0;
        $totalBalance = 0.0;

        foreach ($this->reports->vehicleTotals($from, $toExclusive, $siteId, $fuelStationId) as $row) {
            $credit = round((float) $row->credit_hauls, 2);
            $cash = round((float) $row->credit_receivables, 2);
            $debit = round(
                (float) $row->debit_payments + (float) $row->debit_fuel + (float) $row->debit_fines,
                2,
            );
            $balance = round($debit - ($credit + $cash), 2);

            $rows[] = [
                'plate' => (string) $row->plate,
                'credit' => $credit,
                'cash' => $cash,
                'debit' => $debit,
                'balance' => $balance,
            ];

            $totalCredit += $credit;
            $totalCash += $cash;
            $totalDebit += $debit;
            $totalBalance += $balance;
        }

        return [
            'title' => 'Plaka Bakiye Raporu',
            'subtitle' => $meta['label'],
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'filtered' => $filtered,
            'balance_label' => $filtered ? 'Filtreli net' : 'Bakiye',
            'rows' => $rows,
            'total_credit' => round($totalCredit, 2),
            'total_cash' => round($totalCash, 2),
            'total_debit' => round($totalDebit, 2),
            'total_balance' => round($totalBalance, 2),
        ];
    }

    /**
     * @param  array{period: 'month'|'range'|'all', year?: int, month?: int, from?: string, to?: string, site?: string, fuel_station?: string}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);

        $filename = match ($report['period']) {
            'month' => sprintf('plaka-bakiye-%04d-%02d.csv', $report['year'], $report['month']),
            'range' => sprintf('plaka-bakiye-%s_%s.csv', $report['from'], $report['to']),
            default => 'plaka-bakiye-tum-zamanlar.csv',
        };

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Plaka',
                'Alacak',
                'Elden',
                'Borç',
                $report['balance_label'],
            ], ';');

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['plate'],
                    number_format($row['credit'], 2, ',', '.'),
                    number_format($row['cash'], 2, ',', '.'),
                    number_format($row['debit'], 2, ',', '.'),
                    number_format($row['balance'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($handle, [
                'Toplam',
                number_format($report['total_credit'], 2, ',', '.'),
                number_format($report['total_cash'], 2, ',', '.'),
                number_format($report['total_debit'], 2, ',', '.'),
                number_format($report['total_balance'], 2, ',', '.'),
            ], ';');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{period: 'month'|'range'|'all', year?: int, month?: int, from?: string, to?: string}  $filters
     * @return array{0: ?Carbon, 1: ?Carbon, 2: array{period: 'month'|'range'|'all', label: string, year: ?int, month: ?int, month_label: ?string, from: ?string, to: ?string}}
     */
    private function resolvePeriod(array $filters): array
    {
        $period = $filters['period'] ?? 'month';

        if ($period === 'all') {
            return [
                null,
                null,
                [
                    'period' => 'all',
                    'label' => 'Tüm zamanlar',
                    'year' => null,
                    'month' => null,
                    'month_label' => null,
                    'from' => null,
                    'to' => null,
                ],
            ];
        }

        if ($period === 'range') {
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
