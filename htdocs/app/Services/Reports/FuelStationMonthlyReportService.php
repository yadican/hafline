<?php

namespace App\Services\Reports;

use App\Repositories\Reports\FuelStationMonthlyReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FuelStationMonthlyReportService extends Service
{
    public function __construct(private FuelStationMonthlyReportRepository $reports) {}

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
     * @param  array{fuel_station: string, year: int, month: int, day_from?: int, day_to?: int, class?: string|null}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     station_name: string,
     *     year: int,
     *     month: int,
     *     month_label: string,
     *     from: string,
     *     to: string,
     *     days: list<int>,
     *     rows: list<array{plate: string, liters: array<int, float>, total: float}>,
     *     day_totals: array<int, float>,
     *     grand_total: float
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
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $dayFrom = max(1, min($daysInMonth, (int) ($filters['day_from'] ?? 1)));
        $dayTo = max($dayFrom, min($daysInMonth, (int) ($filters['day_to'] ?? $daysInMonth)));

        $from = Carbon::create($year, $month, $dayFrom)->startOfDay();
        $toExclusive = Carbon::create($year, $month, $dayTo)->startOfDay()->addDay();
        $classId = filled($filters['class'] ?? null) ? (string) $filters['class'] : null;

        $days = range($dayFrom, $dayTo);
        $rowsByVehicle = [];

        foreach ($this->reports->dayLiters($station->id, $from, $toExclusive, $classId) as $row) {
            $vehicleId = (string) $row->vehicle_id;
            $day = (int) Carbon::parse((string) $row->issued_on)->day;
            $liters = round((float) $row->liters, 2);

            if (! isset($rowsByVehicle[$vehicleId])) {
                $rowsByVehicle[$vehicleId] = [
                    'plate' => (string) $row->plate,
                    'liters' => [],
                    'total' => 0.0,
                ];
            }

            $rowsByVehicle[$vehicleId]['liters'][$day] = round(
                ($rowsByVehicle[$vehicleId]['liters'][$day] ?? 0) + $liters,
                2,
            );
            $rowsByVehicle[$vehicleId]['total'] = round($rowsByVehicle[$vehicleId]['total'] + $liters, 2);
        }

        $rows = array_values($rowsByVehicle);
        usort($rows, fn (array $a, array $b): int => strcasecmp($a['plate'], $b['plate']));

        $dayTotals = [];
        $grandTotal = 0.0;

        foreach ($days as $day) {
            $sum = 0.0;

            foreach ($rows as $row) {
                $sum += $row['liters'][$day] ?? 0;
            }

            $dayTotals[$day] = round($sum, 2);
            $grandTotal += $sum;
        }

        $monthLabel = SiteExitReportService::MONTHS[$month];

        return [
            'title' => 'Yakıt Yeri Aylık',
            'subtitle' => "{$station->name} · {$year} {$monthLabel} · {$dayFrom}–{$dayTo}",
            'station_name' => $station->name,
            'year' => $year,
            'month' => $month,
            'month_label' => $monthLabel,
            'from' => $from->toDateString(),
            'to' => Carbon::create($year, $month, $dayTo)->toDateString(),
            'days' => $days,
            'rows' => $rows,
            'day_totals' => $dayTotals,
            'grand_total' => round($grandTotal, 2),
        ];
    }

    /**
     * @param  array{fuel_station: string, year: int, month: int, day_from?: int, day_to?: int, class?: string|null}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);

        abort_if($report === null, 404);

        $filename = sprintf(
            'yakit-yeri-aylik-%s-%04d-%02d.csv',
            str($report['station_name'])->slug('_'),
            $report['year'],
            $report['month'],
        );

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            $header = ['Plaka', ...array_map(fn (int $day): string => (string) $day, $report['days']), 'Toplam'];
            fputcsv($handle, $header, ';');

            foreach ($report['rows'] as $row) {
                $line = [$row['plate']];

                foreach ($report['days'] as $day) {
                    $liters = $row['liters'][$day] ?? 0;
                    $line[] = $liters > 0 ? number_format($liters, 2, ',', '.') : '-';
                }

                $line[] = number_format($row['total'], 2, ',', '.');
                fputcsv($handle, $line, ';');
            }

            $footer = ['Toplam'];

            foreach ($report['days'] as $day) {
                $footer[] = number_format($report['day_totals'][$day] ?? 0, 2, ',', '.');
            }

            $footer[] = number_format($report['grand_total'], 2, ',', '.');
            fputcsv($handle, $footer, ';');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
