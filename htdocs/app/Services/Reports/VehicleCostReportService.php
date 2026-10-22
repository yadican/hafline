<?php

namespace App\Services\Reports;

use App\Repositories\Reports\VehicleCostReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleCostReportService extends Service
{
    public function __construct(private VehicleCostReportRepository $reports) {}

    /**
     * @return array{owners: array<string, string>, periods: array<string, string>, years: array<int, string>, months: array<int, string>}
     */
    public function formOptions(?string $includeOwner = null): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            'owners' => $this->reports->ownerOptions($includeOwner),
            'periods' => SiteExitReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{owner: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     owner: string,
     *     owner_name: string,
     *     period: 'month'|'range',
     *     period_label: string,
     *     year: ?int,
     *     month: ?int,
     *     month_label: ?string,
     *     from: string,
     *     to: string,
     *     rows: list<array{plate: string, gelir: float, yakit: float, piyasa: float, durum: float}>,
     *     vehicle_count: int,
     *     total_gelir: float,
     *     total_yakit: float,
     *     total_piyasa: float,
     *     total_durum: float,
     * }|null
     */
    public function vehicleReport(array $filters): ?array
    {
        $owner = $this->reports->findOwner($filters['owner']);

        if ($owner === null) {
            return null;
        }

        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);

        $rows = [];
        $totalGelir = 0.0;
        $totalYakit = 0.0;
        $totalPiyasa = 0.0;
        $totalDurum = 0.0;

        foreach ($this->reports->vehicleTotals($owner->id, $from, $toExclusive) as $row) {
            $gelir = round((float) $row->gelir, 2);
            $yakit = round((float) $row->yakit, 2);
            $piyasa = round((float) $row->piyasa, 2);
            $durum = round($gelir - ($yakit + $piyasa), 2);

            $rows[] = [
                'plate' => (string) $row->plate,
                'gelir' => $gelir,
                'yakit' => $yakit,
                'piyasa' => $piyasa,
                'durum' => $durum,
            ];

            $totalGelir += $gelir;
            $totalYakit += $yakit;
            $totalPiyasa += $piyasa;
            $totalDurum += $durum;
        }

        return [
            'title' => 'Araç Maliyet Raporu',
            'subtitle' => $owner->name.' · '.$meta['label'],
            'owner' => $owner->id,
            'owner_name' => $owner->name,
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'rows' => $rows,
            'vehicle_count' => count($rows),
            'total_gelir' => round($totalGelir, 2),
            'total_yakit' => round($totalYakit, 2),
            'total_piyasa' => round($totalPiyasa, 2),
            'total_durum' => round($totalDurum, 2),
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
     *     rows: list<array{owner: string, owner_name: string, gelir: float, yakit: float, piyasa: float, durum: float}>,
     *     owner_count: int,
     *     total_gelir: float,
     *     total_yakit: float,
     *     total_piyasa: float,
     *     total_durum: float,
     * }
     */
    public function ownerSummary(array $filters): array
    {
        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);

        $rows = [];
        $totalGelir = 0.0;
        $totalYakit = 0.0;
        $totalPiyasa = 0.0;
        $totalDurum = 0.0;

        foreach ($this->reports->ownerTotals($from, $toExclusive) as $row) {
            $gelir = round((float) $row->gelir, 2);
            $yakit = round((float) $row->yakit, 2);
            $piyasa = round((float) $row->piyasa, 2);
            $durum = round($gelir - ($yakit + $piyasa), 2);

            $rows[] = [
                'owner' => (string) $row->owner_id,
                'owner_name' => (string) $row->owner_name,
                'gelir' => $gelir,
                'yakit' => $yakit,
                'piyasa' => $piyasa,
                'durum' => $durum,
            ];

            $totalGelir += $gelir;
            $totalYakit += $yakit;
            $totalPiyasa += $piyasa;
            $totalDurum += $durum;
        }

        return [
            'title' => 'Plaka Sahibi Özet',
            'subtitle' => $meta['label'],
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'rows' => $rows,
            'owner_count' => count($rows),
            'total_gelir' => round($totalGelir, 2),
            'total_yakit' => round($totalYakit, 2),
            'total_piyasa' => round($totalPiyasa, 2),
            'total_durum' => round($totalDurum, 2),
        ];
    }

    /**
     * @param  array{owner: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     */
    public function exportVehicleCsv(array $filters): ?StreamedResponse
    {
        $report = $this->vehicleReport($filters);

        if ($report === null) {
            return null;
        }

        $filename = $report['period'] === 'month'
            ? sprintf('arac-maliyet-%04d-%02d.csv', $report['year'], $report['month'])
            : sprintf('arac-maliyet-%s_%s.csv', $report['from'], $report['to']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Plaka', 'Gelir', 'Yakıt', 'Piyasa', 'Durum'], ';');

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['plate'],
                    number_format($row['gelir'], 2, ',', '.'),
                    number_format($row['yakit'], 2, ',', '.'),
                    number_format($row['piyasa'], 2, ',', '.'),
                    number_format($row['durum'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($handle, [
                $report['vehicle_count'].' araç',
                number_format($report['total_gelir'], 2, ',', '.'),
                number_format($report['total_yakit'], 2, ',', '.'),
                number_format($report['total_piyasa'], 2, ',', '.'),
                number_format($report['total_durum'], 2, ',', '.'),
            ], ';');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     */
    public function exportOwnerSummaryCsv(array $filters): StreamedResponse
    {
        $report = $this->ownerSummary($filters);

        $filename = $report['period'] === 'month'
            ? sprintf('arac-maliyet-sahip-%04d-%02d.csv', $report['year'], $report['month'])
            : sprintf('arac-maliyet-sahip-%s_%s.csv', $report['from'], $report['to']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Plaka Sahibi', 'Gelir', 'Yakıt', 'Piyasa', 'Durum'], ';');

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['owner_name'],
                    number_format($row['gelir'], 2, ',', '.'),
                    number_format($row['yakit'], 2, ',', '.'),
                    number_format($row['piyasa'], 2, ',', '.'),
                    number_format($row['durum'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($handle, [
                $report['owner_count'].' sahip',
                number_format($report['total_gelir'], 2, ',', '.'),
                number_format($report['total_yakit'], 2, ',', '.'),
                number_format($report['total_piyasa'], 2, ',', '.'),
                number_format($report['total_durum'], 2, ',', '.'),
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
