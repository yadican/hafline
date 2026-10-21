<?php

namespace App\Services\Reports;

use App\Repositories\Reports\DumpSiteReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DumpSiteReportService extends Service
{
    public function __construct(private DumpSiteReportRepository $reports) {}

    /**
     * @return array{dumpSites: array<string, string>, periods: array<string, string>, years: array<int, string>, months: array<int, string>, classes: array<string, string>}
     */
    public function formOptions(): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            'dumpSites' => $this->reports->dumpSites(),
            'periods' => SiteExitReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
            'classes' => $this->reports->vehicleClasses(),
        ];
    }

    /**
     * @param  array{dump_site: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, class?: string|null}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     dump_site_name: string,
     *     period: 'month'|'range',
     *     year: ?int,
     *     month: ?int,
     *     month_label: ?string,
     *     from: string,
     *     to: string,
     *     columns: list<array{id: string, label: string}>,
     *     rows: list<array{plate: string, counts: array<string, int>, total: int}>,
     *     column_totals: array<string, int>,
     *     grand_total: int,
     * }|null
     */
    public function matrix(array $filters): ?array
    {
        $dumpSite = $this->reports->findDumpSite($filters['dump_site']);

        if ($dumpSite === null) {
            return null;
        }

        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);
        $classId = $filters['class'] ?? null;

        $counts = $this->reports->tripCounts($dumpSite->id, $from, $toExclusive, $classId !== '' ? $classId : null);

        $columnsByRoute = [];
        $rowsByVehicle = [];

        foreach ($counts as $row) {
            $routeId = (string) $row->route_id;

            if (! isset($columnsByRoute[$routeId])) {
                $columnsByRoute[$routeId] = [
                    'id' => $routeId,
                    'label' => trim($dumpSite->name.' '.$row->site_name),
                    'site_name' => (string) $row->site_name,
                ];
            }

            $vehicleId = (string) $row->vehicle_id;

            if (! isset($rowsByVehicle[$vehicleId])) {
                $rowsByVehicle[$vehicleId] = [
                    'plate' => (string) $row->plate,
                    'counts' => [],
                    'total' => 0,
                ];
            }

            $trips = (int) $row->trips;
            $rowsByVehicle[$vehicleId]['counts'][$routeId] = $trips;
            $rowsByVehicle[$vehicleId]['total'] += $trips;
        }

        uasort($columnsByRoute, fn (array $a, array $b): int => strcasecmp($a['site_name'], $b['site_name']));

        $columns = array_values(array_map(
            fn (array $column): array => ['id' => $column['id'], 'label' => $column['label']],
            $columnsByRoute,
        ));

        $rows = array_values($rowsByVehicle);
        usort($rows, fn (array $a, array $b): int => strcasecmp($a['plate'], $b['plate']));

        $columnTotals = [];
        $grandTotal = 0;

        foreach ($columns as $column) {
            $sum = 0;

            foreach ($rows as $row) {
                $sum += $row['counts'][$column['id']] ?? 0;
            }

            $columnTotals[$column['id']] = $sum;
            $grandTotal += $sum;
        }

        return [
            'title' => 'Dökümyeri Raporu',
            'subtitle' => "{$dumpSite->name} {$meta['label']} Raporu",
            'dump_site_name' => $dumpSite->name,
            'period' => $meta['period'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'columns' => $columns,
            'rows' => $rows,
            'column_totals' => $columnTotals,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * @param  array{dump_site: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, class?: string|null}  $filters
     */
    public function exportCsv(array $filters): ?StreamedResponse
    {
        $matrix = $this->matrix($filters);

        if ($matrix === null) {
            return null;
        }

        $filename = $matrix['period'] === 'month'
            ? sprintf(
                'dokumyeri-%s-%04d-%02d.csv',
                str($matrix['dump_site_name'])->slug('_'),
                $matrix['year'],
                $matrix['month'],
            )
            : sprintf(
                'dokumyeri-%s-%s_%s.csv',
                str($matrix['dump_site_name'])->slug('_'),
                $matrix['from'],
                $matrix['to'],
            );

        return response()->streamDownload(function () use ($matrix): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            $header = ['Plaka', ...array_column($matrix['columns'], 'label'), 'Toplam'];
            fputcsv($handle, $header, ';');

            foreach ($matrix['rows'] as $row) {
                $line = [$row['plate']];

                foreach ($matrix['columns'] as $column) {
                    $count = $row['counts'][$column['id']] ?? 0;
                    $line[] = $count > 0 ? $count : '-';
                }

                $line[] = $row['total'];
                fputcsv($handle, $line, ';');
            }

            $footer = ['Toplam'];

            foreach ($matrix['columns'] as $column) {
                $footer[] = $matrix['column_totals'][$column['id']] ?? 0;
            }

            $footer[] = $matrix['grand_total'];
            fputcsv($handle, $footer, ';');

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
