<?php

namespace App\Services\Reports;

use App\Enums\PaymentMethod;
use App\Repositories\Reports\SiteExpenseReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiteExpenseReportService extends Service
{
    public function __construct(private SiteExpenseReportRepository $reports) {}

    /**
     * @return array{sites: array<string, string>, periods: array<string, string>, years: array<int, string>, months: array<int, string>}
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
            'periods' => VehicleBalanceReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, site: string}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     site_name: string,
     *     period: 'month'|'range',
     *     period_label: string,
     *     year: ?int,
     *     month: ?int,
     *     month_label: ?string,
     *     from: string,
     *     to: string,
     *     opening: float,
     *     lines: list<array{
     *         occurred_on: string,
     *         description: ?string,
     *         payment_method: ?string,
     *         debit: float,
     *         credit: float,
     *         balance: float
     *     }>,
     *     total_debit: float,
     *     total_credit: float,
     *     closing: float
     * }
     */
    public function report(array $filters): array
    {
        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);
        $siteId = $filters['site'];
        $siteName = $this->reports->siteName($siteId) ?? 'Şantiye';
        $opening = $this->reports->openingBalance($siteId, $from);
        $running = $opening;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $lines = [];

        foreach ($this->reports->periodLines($siteId, $from, $toExclusive) as $row) {
            $debit = round((float) $row->debit, 2);
            $credit = round((float) $row->credit, 2);
            $running = round($running + $debit - $credit, 2);
            $totalDebit += $debit;
            $totalCredit += $credit;

            $method = $row->payment_method !== null
                ? PaymentMethod::tryFrom((string) $row->payment_method)?->label()
                : null;

            $lines[] = [
                'occurred_on' => (string) $row->occurred_on,
                'description' => $row->description,
                'payment_method' => $method,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running,
            ];
        }

        return [
            'title' => 'Şantiye Gelir / Gider',
            'subtitle' => $siteName.' · '.$meta['label'],
            'site_name' => $siteName,
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'opening' => $opening,
            'lines' => $lines,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'closing' => round($opening + $totalDebit - $totalCredit, 2),
        ];
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, site: string}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);

        $filename = $report['period'] === 'month'
            ? sprintf('santiye-gider-%04d-%02d.csv', $report['year'], $report['month'])
            : sprintf('santiye-gider-%s_%s.csv', $report['from'], $report['to']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Tarih', 'Açıklama', 'Ödeme Tipi', 'Borç', 'Alacak', 'Bakiye'], ';');

            fputcsv($handle, [
                '',
                'Geçmiş Dönemler (Devir)',
                '',
                $report['opening'] > 0 ? number_format($report['opening'], 2, ',', '.') : '',
                $report['opening'] < 0 ? number_format(abs($report['opening']), 2, ',', '.') : '',
                number_format($report['opening'], 2, ',', '.'),
            ], ';');

            foreach ($report['lines'] as $line) {
                fputcsv($handle, [
                    Carbon::parse($line['occurred_on'])->format('d.m.Y'),
                    $line['description'] ?? '',
                    $line['payment_method'] ?? '',
                    $line['debit'] > 0 ? number_format($line['debit'], 2, ',', '.') : '',
                    $line['credit'] > 0 ? number_format($line['credit'], 2, ',', '.') : '',
                    number_format($line['balance'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($handle, [
                '',
                'Toplam / Kapanış',
                '',
                number_format($report['total_debit'], 2, ',', '.'),
                number_format($report['total_credit'], 2, ',', '.'),
                number_format($report['closing'], 2, ',', '.'),
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
