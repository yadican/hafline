<?php

namespace App\Services\Reports;

use App\Repositories\Reports\PeriodicMovementReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerPeriodicReportService extends Service
{
    public function __construct(private PeriodicMovementReportRepository $reports) {}

    /**
     * @return array{owners: array<string, string>, periods: array<string, string>, years: array<int, string>, months: array<int, string>}
     */
    public function formOptions(): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            'owners' => $this->reports->ownerOptions(),
            'periods' => VehicleBalanceReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{owner: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     owner_name: string,
     *     period: 'month'|'range',
     *     period_label: string,
     *     year: ?int,
     *     month: ?int,
     *     from: string,
     *     to: string,
     *     vehicles: list<array{
     *         plate: string,
     *         rows: list<array{type: 'haul'|'fuel', description: string, trips: ?int, liters: ?float, tonnage: ?float}>,
     *         total_trips: int,
     *         total_liters: float,
     *         total_tonnage: float
     *     }>,
     *     total_trips: int,
     *     total_liters: float,
     *     total_tonnage: float
     * }|null
     */
    public function report(array $filters): ?array
    {
        $owner = $this->reports->findOwner($filters['owner']);

        if ($owner === null) {
            return null;
        }

        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);

        $vehicles = [];

        foreach ($this->reports->ownerHaulTotals($owner->id, $from, $toExclusive) as $row) {
            $vehicleId = (string) $row->vehicle_id;

            if (! isset($vehicles[$vehicleId])) {
                $vehicles[$vehicleId] = [
                    'plate' => (string) $row->plate,
                    'rows' => [],
                    'total_trips' => 0,
                    'total_liters' => 0.0,
                    'total_tonnage' => 0.0,
                ];
            }

            $trips = (int) $row->trips;
            $tonnage = round((float) $row->tonnage, 2);
            $vehicles[$vehicleId]['rows'][] = [
                'type' => 'haul',
                'description' => trim($row->site_name.' - '.$row->dump_site_name),
                'trips' => $trips,
                'liters' => null,
                'tonnage' => $tonnage,
            ];
            $vehicles[$vehicleId]['total_trips'] += $trips;
            $vehicles[$vehicleId]['total_tonnage'] = round($vehicles[$vehicleId]['total_tonnage'] + $tonnage, 2);
        }

        foreach ($this->reports->ownerFuelTotals($owner->id, $from, $toExclusive) as $row) {
            $vehicleId = (string) $row->vehicle_id;

            if (! isset($vehicles[$vehicleId])) {
                $vehicles[$vehicleId] = [
                    'plate' => (string) $row->plate,
                    'rows' => [],
                    'total_trips' => 0,
                    'total_liters' => 0.0,
                    'total_tonnage' => 0.0,
                ];
            }

            $liters = round((float) $row->liters, 2);
            $vehicles[$vehicleId]['rows'][] = [
                'type' => 'fuel',
                'description' => (string) $row->station_name,
                'trips' => null,
                'liters' => $liters,
                'tonnage' => null,
            ];
            $vehicles[$vehicleId]['total_liters'] = round($vehicles[$vehicleId]['total_liters'] + $liters, 2);
        }

        uasort($vehicles, fn (array $a, array $b): int => strcasecmp($a['plate'], $b['plate']));

        $list = array_values($vehicles);
        $totalTrips = 0;
        $totalLiters = 0.0;
        $totalTonnage = 0.0;

        foreach ($list as $vehicle) {
            $totalTrips += $vehicle['total_trips'];
            $totalLiters += $vehicle['total_liters'];
            $totalTonnage += $vehicle['total_tonnage'];
        }

        return [
            'title' => 'Plaka Sahibi Dönemsel Raporu',
            'subtitle' => $owner->name.' · '.$meta['label'].' İş Raporu',
            'owner_name' => $owner->name,
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'vehicles' => $list,
            'total_trips' => $totalTrips,
            'total_liters' => round($totalLiters, 2),
            'total_tonnage' => round($totalTonnage, 2),
        ];
    }

    /**
     * @param  array{owner: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);

        abort_if($report === null, 404);

        $filename = $report['period'] === 'month'
            ? sprintf('plaka-sahibi-donemsel-%04d-%02d.csv', $report['year'], $report['month'])
            : sprintf('plaka-sahibi-donemsel-%s_%s.csv', $report['from'], $report['to']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Plaka', 'Sıra', 'Açıklama', 'Döküm Sayısı', 'Toplam Lt', 'Toplam Ton'], ';');

            foreach ($report['vehicles'] as $vehicle) {
                foreach ($vehicle['rows'] as $index => $row) {
                    fputcsv($handle, [
                        $vehicle['plate'],
                        $index + 1,
                        $row['description'],
                        $row['trips'] ?? '',
                        $row['liters'] !== null ? number_format($row['liters'], 2, ',', '.') : '',
                        $row['tonnage'] !== null ? number_format($row['tonnage'], 2, ',', '.') : '',
                    ], ';');
                }

                fputcsv($handle, [
                    $vehicle['plate'],
                    '',
                    'Toplam',
                    $vehicle['total_trips'],
                    number_format($vehicle['total_liters'], 2, ',', '.'),
                    number_format($vehicle['total_tonnage'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($handle, [
                '',
                '',
                'Genel Toplam',
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
