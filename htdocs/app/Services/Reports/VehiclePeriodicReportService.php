<?php

namespace App\Services\Reports;

use App\Repositories\Reports\PeriodicMovementReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehiclePeriodicReportService extends Service
{
    public function __construct(private PeriodicMovementReportRepository $reports) {}

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
     * @param  array{vehicle: string, plate: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     plate: string,
     *     period: 'month'|'range',
     *     period_label: string,
     *     year: ?int,
     *     month: ?int,
     *     from: string,
     *     to: string,
     *     rows: list<array{type: 'haul'|'fuel', description: string, trips: ?int, liters: ?float, tonnage: ?float}>,
     *     total_trips: int,
     *     total_liters: float,
     *     total_tonnage: float
     * }
     */
    public function report(array $filters): array
    {
        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);
        $vehicleId = $filters['vehicle'];
        $plate = $filters['plate'];

        $rows = [];
        $totalTrips = 0;
        $totalTonnage = 0.0;
        $totalLiters = 0.0;

        foreach ($this->reports->vehicleHaulTotals($vehicleId, $from, $toExclusive) as $row) {
            $trips = (int) $row->trips;
            $tonnage = round((float) $row->tonnage, 2);
            $rows[] = [
                'type' => 'haul',
                'description' => trim($row->site_name.' - '.$row->dump_site_name),
                'trips' => $trips,
                'liters' => null,
                'tonnage' => $tonnage,
            ];
            $totalTrips += $trips;
            $totalTonnage += $tonnage;
        }

        foreach ($this->reports->vehicleFuelTotals($vehicleId, $from, $toExclusive) as $row) {
            $liters = round((float) $row->liters, 2);
            $rows[] = [
                'type' => 'fuel',
                'description' => (string) $row->station_name,
                'trips' => null,
                'liters' => $liters,
                'tonnage' => null,
            ];
            $totalLiters += $liters;
        }

        return [
            'title' => 'Plaka Dönemsel Raporu',
            'subtitle' => $plate.' · '.$meta['label'].' İş Raporu',
            'plate' => $plate,
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'rows' => $rows,
            'total_trips' => $totalTrips,
            'total_liters' => round($totalLiters, 2),
            'total_tonnage' => round($totalTonnage, 2),
        ];
    }

    /**
     * @param  array{vehicle: string, plate: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);

        $filename = $report['period'] === 'month'
            ? sprintf('plaka-donemsel-%s-%04d-%02d.csv', str($report['plate'])->slug('_'), $report['year'], $report['month'])
            : sprintf('plaka-donemsel-%s-%s_%s.csv', str($report['plate'])->slug('_'), $report['from'], $report['to']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Sıra', 'Açıklama', 'Döküm Sayısı', 'Toplam Lt', 'Toplam Ton'], ';');

            foreach ($report['rows'] as $index => $row) {
                fputcsv($handle, [
                    $index + 1,
                    $row['description'],
                    $row['trips'] ?? '',
                    $row['liters'] !== null ? number_format($row['liters'], 2, ',', '.') : '',
                    $row['tonnage'] !== null ? number_format($row['tonnage'], 2, ',', '.') : '',
                ], ';');
            }

            fputcsv($handle, [
                '',
                'Toplam',
                $report['total_trips'],
                number_format($report['total_liters'], 2, ',', '.'),
                number_format($report['total_tonnage'], 2, ',', '.'),
            ], ';');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     * @return array{0: Carbon, 1: Carbon, 2: array{period: 'month'|'range', label: string, year: ?int, month: ?int, from: string, to: string}}
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
                'from' => $from->toDateString(),
                'to' => $from->copy()->endOfMonth()->toDateString(),
            ],
        ];
    }
}
