<?php

namespace App\Services\Reports;

use App\Repositories\Reports\EmployeeMonthlyReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeMonthlyReportService extends Service
{
    public function __construct(private EmployeeMonthlyReportRepository $reports) {}

    /**
     * @return array{years: array<int, string>, months: array<int, string>}
     */
    public function formOptions(): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{year: int, month: int}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     year: int,
     *     month: int,
     *     month_label: string,
     *     rows: list<array{name: string, earned: float, bonus: float, carry: float, paid: float, remaining: float}>,
     *     totals: array{earned: float, bonus: float, carry: float, paid: float, remaining: float}
     * }
     */
    public function report(array $filters): array
    {
        $period = Carbon::create($filters['year'], $filters['month'], 1)->startOfMonth();
        $periodDate = $period->toDateString();
        $periodEnd = $period->copy()->endOfMonth()->toDateString();
        $monthLabel = SiteExitReportService::MONTHS[$filters['month']];

        $rows = [];
        $totals = ['earned' => 0.0, 'bonus' => 0.0, 'carry' => 0.0, 'paid' => 0.0, 'remaining' => 0.0];

        foreach ($this->reports->rows($periodDate, $periodEnd) as $row) {
            $remaining = round($row->earned + $row->bonus + $row->carry - $row->paid, 2);

            $rows[] = [
                'name' => $row->name,
                'earned' => $row->earned,
                'bonus' => $row->bonus,
                'carry' => $row->carry,
                'paid' => $row->paid,
                'remaining' => $remaining,
            ];

            $totals['earned'] += $row->earned;
            $totals['bonus'] += $row->bonus;
            $totals['carry'] += $row->carry;
            $totals['paid'] += $row->paid;
            $totals['remaining'] += $remaining;
        }

        return [
            'title' => 'Personel Aylık Döküm',
            'subtitle' => "{$filters['year']} {$monthLabel}",
            'year' => $filters['year'],
            'month' => $filters['month'],
            'month_label' => $monthLabel,
            'rows' => $rows,
            'totals' => [
                'earned' => round($totals['earned'], 2),
                'bonus' => round($totals['bonus'], 2),
                'carry' => round($totals['carry'], 2),
                'paid' => round($totals['paid'], 2),
                'remaining' => round($totals['remaining'], 2),
            ],
        ];
    }

    /**
     * @param  array{year: int, month: int}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);
        $filename = sprintf('personel-aylik-%04d-%02d.csv', $report['year'], $report['month']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Personel', 'Dönem Maaşı', 'Prim', 'Önceki Devir', 'Ödenen', 'Kalan'], ';');

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['name'],
                    number_format($row['earned'], 2, ',', '.'),
                    number_format($row['bonus'], 2, ',', '.'),
                    number_format($row['carry'], 2, ',', '.'),
                    number_format($row['paid'], 2, ',', '.'),
                    number_format($row['remaining'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($handle, [
                'Toplam',
                number_format($report['totals']['earned'], 2, ',', '.'),
                number_format($report['totals']['bonus'], 2, ',', '.'),
                number_format($report['totals']['carry'], 2, ',', '.'),
                number_format($report['totals']['paid'], 2, ',', '.'),
                number_format($report['totals']['remaining'], 2, ',', '.'),
            ], ';');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
