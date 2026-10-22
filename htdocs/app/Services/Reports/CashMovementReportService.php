<?php

namespace App\Services\Reports;

use App\Repositories\Reports\CashMovementReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashMovementReportService extends Service
{
    public function __construct(private CashMovementReportRepository $reports) {}

    /**
     * @return array{periods: array<string, string>, years: array<int, string>, months: array<int, string>}
     */
    public function formOptions(): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            'periods' => VehicleBalanceReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
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
     *     opening: float,
     *     lines: list<array{
     *         occurred_on: string,
     *         kind_label: string,
     *         description: ?string,
     *         payment_label: string,
     *         giris: float,
     *         cikis: float,
     *         balance: float
     *     }>,
     *     total_giris: float,
     *     total_cikis: float,
     *     closing: float
     * }
     */
    public function report(array $filters): array
    {
        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);
        $opening = $this->reports->openingBalance($from);
        $running = $opening;
        $totalGiris = 0.0;
        $totalCikis = 0.0;
        $lines = [];

        foreach ($this->reports->periodLines($from, $toExclusive) as $row) {
            $giris = round((float) $row->giris, 2);
            $cikis = round((float) $row->cikis, 2);
            $running = round($running + $giris - $cikis, 2);
            $totalGiris += $giris;
            $totalCikis += $cikis;

            $lines[] = [
                'occurred_on' => (string) $row->occurred_on,
                'kind_label' => (string) $row->kind_label,
                'description' => $row->description,
                'payment_label' => (string) $row->payment_label,
                'giris' => $giris,
                'cikis' => $cikis,
                'balance' => $running,
            ];
        }

        return [
            'title' => 'Kasa Hareketleri',
            'subtitle' => $meta['label'],
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'opening' => $opening,
            'lines' => $lines,
            'total_giris' => round($totalGiris, 2),
            'total_cikis' => round($totalCikis, 2),
            'closing' => round($opening + $totalGiris - $totalCikis, 2),
        ];
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);

        $filename = $report['period'] === 'month'
            ? sprintf('kasa-hareketleri-%04d-%02d.csv', $report['year'], $report['month'])
            : sprintf('kasa-hareketleri-%s_%s.csv', $report['from'], $report['to']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Tarih', 'İşlem Tipi', 'Açıklama', 'Ödeme Tipi', 'Giriş', 'Çıkış', 'Bakiye'], ';');

            fputcsv($handle, [
                '',
                'Devir',
                '',
                '',
                $report['opening'] > 0 ? number_format($report['opening'], 2, ',', '.') : '',
                $report['opening'] < 0 ? number_format(abs($report['opening']), 2, ',', '.') : '',
                number_format($report['opening'], 2, ',', '.'),
            ], ';');

            foreach ($report['lines'] as $line) {
                fputcsv($handle, [
                    Carbon::parse($line['occurred_on'])->format('d.m.Y'),
                    $line['kind_label'],
                    $line['description'] ?? '',
                    $line['payment_label'],
                    $line['giris'] > 0 ? number_format($line['giris'], 2, ',', '.') : '',
                    $line['cikis'] > 0 ? number_format($line['cikis'], 2, ',', '.') : '',
                    number_format($line['balance'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($handle, [
                '',
                'Toplam / Kapanış',
                '',
                '',
                number_format($report['total_giris'], 2, ',', '.'),
                number_format($report['total_cikis'], 2, ',', '.'),
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
