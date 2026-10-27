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
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, site: string, fuel_station: string}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     period: 'month'|'range',
     *     period_label: string,
     *     year: ?int,
     *     month: ?int,
     *     month_label: ?string,
     *     from: string,
     *     to: string,
     *     filtered: bool,
     *     balance_label: string,
     *     rows: list<array{plate: string, opening: float, credit: float, cash: float, debit: float, balance: float}>,
     *     total_opening: float,
     *     total_credit: float,
     *     total_cash: float,
     *     total_debit: float,
     *     total_balance: float,
     * }
     */
    public function report(array $filters): array
    {
        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);
        $siteId = $filters['site'];
        $fuelStationId = $filters['fuel_station'];
        $filtered = filled($siteId) || filled($fuelStationId);

        // Listed: rows the filters select in the period, plus any row carrying a balance in. Amounts
        // come from every filtered movement, so a listed row never loses part of its period or devir.
        $listed = $this->reports->vehicleTotals($from, $toExclusive, $siteId, $fuelStationId)->keyBy('vehicle_id');
        $period = $this->reports->vehicleTotals($from, $toExclusive, $siteId, $fuelStationId, false)->keyBy('vehicle_id');
        $before = $this->reports->vehicleTotals(null, $from, $siteId, $fuelStationId, false)->keyBy('vehicle_id');
        $carried = $before->filter(fn (object $row) => abs($this->amounts($row)['net']) >= 0.005);

        $rows = [];
        $totalOpening = 0.0;
        $totalCredit = 0.0;
        $totalCash = 0.0;
        $totalDebit = 0.0;
        $totalBalance = 0.0;

        foreach ($listed->union($carried)->sortBy('plate') as $id => $row) {
            $opening = $before->has($id) ? $this->amounts($before[$id])['net'] : 0.0;
            $amounts = $period->has($id) ? $this->amounts($period[$id]) : ['credit' => 0.0, 'cash' => 0.0, 'debit' => 0.0, 'net' => 0.0];
            $balance = round($opening + $amounts['net'], 2);

            $rows[] = [
                'plate' => (string) $row->plate,
                'opening' => $opening,
                'credit' => $amounts['credit'],
                'cash' => $amounts['cash'],
                'debit' => $amounts['debit'],
                'balance' => $balance,
            ];

            $totalOpening += $opening;
            $totalCredit += $amounts['credit'];
            $totalCash += $amounts['cash'];
            $totalDebit += $amounts['debit'];
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
            'balance_label' => $filtered ? 'Filtreli bakiye' : 'Bakiye',
            'rows' => $rows,
            'total_opening' => round($totalOpening, 2),
            'total_credit' => round($totalCredit, 2),
            'total_cash' => round($totalCash, 2),
            'total_debit' => round($totalDebit, 2),
            'total_balance' => round($totalBalance, 2),
        ];
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, site: string, fuel_station: string}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);

        $filename = $report['period'] === 'month'
            ? sprintf('plaka-bakiye-%04d-%02d.csv', $report['year'], $report['month'])
            : sprintf('plaka-bakiye-%s_%s.csv', $report['from'], $report['to']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Plaka',
                'Devir',
                'Alacak',
                'Elden',
                'Borç',
                $report['balance_label'],
            ], ';');

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['plate'],
                    number_format($row['opening'], 2, ',', '.'),
                    number_format($row['credit'], 2, ',', '.'),
                    number_format($row['cash'], 2, ',', '.'),
                    number_format($row['debit'], 2, ',', '.'),
                    number_format($row['balance'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($handle, [
                'Toplam',
                number_format($report['total_opening'], 2, ',', '.'),
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
     * Alacak (work + carried-in), elden and borç of one row, and its net (debit − credit; positive: they owe us).
     *
     * @return array{credit: float, cash: float, debit: float, net: float}
     */
    private function amounts(object $row): array
    {
        $credit = round((float) $row->credit_hauls + (float) $row->credit_machine_works + (float) $row->credit_transfers, 2);
        $cash = round((float) $row->credit_receivables, 2);
        $debit = round((float) $row->debit_payments + (float) $row->debit_fuel + (float) $row->debit_fines + (float) $row->debit_transfers, 2);

        return ['credit' => $credit, 'cash' => $cash, 'debit' => $debit, 'net' => round($debit - $credit - $cash, 2)];
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
