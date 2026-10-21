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
     * @return array{stations: array<string, string>, years: array<int, string>, months: array<int, string>, classes: array<string, string>}
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
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
            'classes' => $this->reports->vehicleClasses(),
        ];
    }

    /**
     * @param  array{fuel_station: string, year: int, month: int, class?: string|null}  $filters
     * @return array{
     *     title: string,
     *     station_name: string,
     *     year: int,
     *     month: int,
     *     month_label: string,
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

        $year = (int) $filters['year'];
        $month = (int) $filters['month'];
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->addMonth();
        $classId = $filters['class'] ?? null;

        $totals = $this->reports->vehicleTotals($station->id, $from, $to, $classId !== '' ? $classId : null);

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
            'year' => $year,
            'month' => $month,
            'month_label' => SiteExitReportService::MONTHS[$month],
            'rows' => $rows,
            'total_liters' => round($totalLiters, 2),
            'total_amount' => round($totalAmount, 2),
        ];
    }

    /**
     * @param  array{fuel_station: string, year: int, month: int, class?: string|null}  $filters
     */
    public function exportCsv(array $filters): ?StreamedResponse
    {
        $report = $this->report($filters);

        if ($report === null) {
            return null;
        }

        $filename = sprintf(
            'yakit-yeri-%s-%04d-%02d.csv',
            str($report['station_name'])->slug('_'),
            $report['year'],
            $report['month'],
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
}
