<?php

namespace App\Services\Reports;

use App\Repositories\Reports\SiteCariReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiteCariReportService extends Service
{
    public function __construct(private SiteCariReportRepository $reports) {}

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
     *     rows: list<array{
     *         name: string,
     *         dump: float,
     *         haul: float,
     *         haul_count: int,
     *         fuel: float,
     *         fuel_count: int,
     *         other: float,
     *         earned: float,
     *         status: float
     *     }>,
     *     totals: array{
     *         dump: float,
     *         haul: float,
     *         haul_count: int,
     *         fuel: float,
     *         fuel_count: int,
     *         other: float,
     *         earned: float,
     *         status: float
     *     }
     * }
     */
    public function report(array $filters): array
    {
        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);

        $rows = [];
        $totals = [
            'dump' => 0.0,
            'haul' => 0.0,
            'haul_count' => 0,
            'fuel' => 0.0,
            'fuel_count' => 0,
            'other' => 0.0,
            'earned' => 0.0,
            'status' => 0.0,
        ];

        foreach ($this->reports->siteTotals($from, $toExclusive) as $row) {
            $dump = round((float) $row->dump_total, 2);
            $haul = round((float) $row->haul_total, 2);
            $haulCount = (int) $row->haul_count;
            $fuel = round((float) $row->fuel_total, 2);
            $fuelCount = (int) $row->fuel_count;
            $other = round((float) $row->other_total, 2);
            $earned = round((float) $row->earned_total, 2);
            $status = round($earned - ($dump + $haul + $fuel + $other), 2);

            $rows[] = [
                'name' => (string) $row->name,
                'dump' => $dump,
                'haul' => $haul,
                'haul_count' => $haulCount,
                'fuel' => $fuel,
                'fuel_count' => $fuelCount,
                'other' => $other,
                'earned' => $earned,
                'status' => $status,
            ];

            $totals['dump'] += $dump;
            $totals['haul'] += $haul;
            $totals['haul_count'] += $haulCount;
            $totals['fuel'] += $fuel;
            $totals['fuel_count'] += $fuelCount;
            $totals['other'] += $other;
            $totals['earned'] += $earned;
            $totals['status'] += $status;
        }

        foreach (['dump', 'haul', 'fuel', 'other', 'earned', 'status'] as $key) {
            $totals[$key] = round($totals[$key], 2);
        }

        return [
            'title' => 'Şantiye Cari Raporu',
            'subtitle' => $meta['label'],
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);

        $filename = $report['period'] === 'month'
            ? sprintf('santiye-cari-%04d-%02d.csv', $report['year'], $report['month'])
            : sprintf('santiye-cari-%s_%s.csv', $report['from'], $report['to']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Şantiye',
                'Döküm',
                'Nakliye',
                'Nak. Fiş',
                'Yakıt',
                'Yak. Fiş',
                'Diğer',
                'Hakediş',
                'Durum',
            ], ';');

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['name'],
                    number_format($row['dump'], 2, ',', '.'),
                    number_format($row['haul'], 2, ',', '.'),
                    $row['haul_count'],
                    number_format($row['fuel'], 2, ',', '.'),
                    $row['fuel_count'],
                    number_format($row['other'], 2, ',', '.'),
                    number_format($row['earned'], 2, ',', '.'),
                    number_format($row['status'], 2, ',', '.'),
                ], ';');
            }

            $t = $report['totals'];
            fputcsv($handle, [
                'Toplam',
                number_format($t['dump'], 2, ',', '.'),
                number_format($t['haul'], 2, ',', '.'),
                $t['haul_count'],
                number_format($t['fuel'], 2, ',', '.'),
                $t['fuel_count'],
                number_format($t['other'], 2, ',', '.'),
                number_format($t['earned'], 2, ',', '.'),
                number_format($t['status'], 2, ',', '.'),
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
