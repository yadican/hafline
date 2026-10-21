<?php

namespace App\Services\Reports;

use App\Repositories\Reports\FuelStationReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FuelStationReportService extends Service
{
    public function __construct(private FuelStationReportRepository $reports) {}

    /**
     * @return array{stations: array<string, string>, periods: array<string, string>, years: array<int, string>, months: array<int, string>, classes: array<string, string>}
     */
    public function formOptions(): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            'stations' => $this->reports->fuelStations(),
            'periods' => SiteExitReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
            'classes' => $this->reports->vehicleClasses(),
        ];
    }

    /**
     * @param  array{fuel_station: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, class?: string|null}  $filters
     * @return array{
     *     title: string,
     *     station_name: string,
     *     period: 'month'|'range',
     *     year: ?int,
     *     month: ?int,
     *     month_label: ?string,
     *     from: string,
     *     to: string,
     *     rows: list<array{plate: string, liters: float, unit_price: float, amount: float}>,
     *     total_liters: float,
     *     total_amount: float,
     * }|null
     */
    public function report(array $filters): ?array
    {
        $station = $this->reports->findFuelStation($filters['fuel_station']);

        if ($station === null) {
            return null;
        }

        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);
        $classId = $filters['class'] ?? null;

        $totals = $this->reports->vehicleTotals($station->id, $from, $toExclusive, $classId !== '' ? $classId : null);

        $rows = [];
        $totalLiters = 0.0;
        $totalAmount = 0.0;

        foreach ($totals as $row) {
            $liters = round((float) $row->liters, 2);
            $amount = round((float) $row->amount, 2);
            $unitPrice = $liters > 0 ? round($amount / $liters, 4) : 0.0;

            $rows[] = [
                'plate' => (string) $row->plate,
                'liters' => $liters,
                'unit_price' => $unitPrice,
                'amount' => $amount,
            ];

            $totalLiters += $liters;
            $totalAmount += $amount;
        }

        return [
            'title' => $station->name.' Yakıt Yeri Raporu',
            'station_name' => $station->name,
            'period' => $meta['period'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'rows' => $rows,
            'total_liters' => round($totalLiters, 2),
            'total_amount' => round($totalAmount, 2),
        ];
    }

    /**
     * @param  array{fuel_station: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, class?: string|null}  $filters
     */
    public function exportCsv(array $filters): ?StreamedResponse
    {
        $report = $this->report($filters);

        if ($report === null) {
            return null;
        }

        $filename = $report['period'] === 'month'
            ? sprintf(
                'yakit-yeri-%s-%04d-%02d.csv',
                str($report['station_name'])->slug('_'),
                $report['year'],
                $report['month'],
            )
            : sprintf(
                'yakit-yeri-%s-%s_%s.csv',
                str($report['station_name'])->slug('_'),
                $report['from'],
                $report['to'],
            );

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Sıra', 'Plaka / Araç', 'Litre', 'Birim Fiyat', 'Tutar'], ';');

            foreach ($report['rows'] as $index => $row) {
                fputcsv($handle, [
                    $index + 1,
                    $row['plate'],
                    number_format($row['liters'], 2, ',', '.'),
                    number_format($row['unit_price'], 2, ',', '.'),
                    number_format($row['amount'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($handle, [
                '',
                'Toplam',
                number_format($report['total_liters'], 2, ',', '.'),
                '',
                number_format($report['total_amount'], 2, ',', '.'),
            ], ';');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     * @return array{0: Carbon, 1: Carbon, 2: array{period: 'month'|'range', year: ?int, month: ?int, month_label: ?string, from: string, to: string}}
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

        return [
            $from,
            $from->copy()->addMonth(),
            [
                'period' => 'month',
                'year' => $year,
                'month' => $month,
                'month_label' => SiteExitReportService::MONTHS[$month],
                'from' => $from->toDateString(),
                'to' => $from->copy()->endOfMonth()->toDateString(),
            ],
        ];
    }
}
