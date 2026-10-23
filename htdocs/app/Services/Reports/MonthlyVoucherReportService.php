<?php

namespace App\Services\Reports;

use App\Repositories\Reports\MonthlyVoucherReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyVoucherReportService extends Service
{
    public const ORIENTATIONS = [
        'site_dump' => 'Şantiye – Döküm yeri',
        'dump_site' => 'Döküm yeri – Şantiye',
    ];

    public function __construct(private MonthlyVoucherReportRepository $reports) {}

    /**
     * @return array{
     *     routes: array<string, string>,
     *     routesByOrientation: array{site_dump: array<string, string>, dump_site: array<string, string>},
     *     orientations: array<string, string>,
     *     years: array<int, string>,
     *     months: array<int, string>,
     *     classes: array<string, string>
     * }
     */
    public function formOptions(?string $orientation = null): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        $orientation = $orientation ?? 'site_dump';
        $routesByOrientation = [
            'site_dump' => $this->reports->routes(),
            'dump_site' => $this->reports->routesDumpFirst(),
        ];

        return [
            'routes' => $routesByOrientation[$orientation] ?? $routesByOrientation['site_dump'],
            'routesByOrientation' => $routesByOrientation,
            'orientations' => self::ORIENTATIONS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
            'classes' => $this->reports->vehicleClasses(),
        ];
    }

    /**
     * @param  array{route: string, year: int, month: int, day_from?: int, day_to?: int, class?: string|null, orientation: 'site_dump'|'dump_site'}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     route_label: string,
     *     orientation: string,
     *     show_class: bool,
     *     year: int,
     *     month: int,
     *     month_label: string,
     *     from: string,
     *     to: string,
     *     days: list<int>,
     *     rows: list<array{plate: string, class_name: ?string, counts: array<int, int>, total: int}>,
     *     day_totals: array<int, int>,
     *     grand_total: int
     * }|null
     */
    public function report(array $filters): ?array
    {
        $route = $this->reports->findRoute($filters['route']);

        if ($route === null) {
            return null;
        }

        $orientation = $filters['orientation'] ?? 'site_dump';
        $showClass = $orientation === 'dump_site';
        $year = (int) $filters['year'];
        $month = (int) $filters['month'];
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $dayFrom = max(1, min($daysInMonth, (int) ($filters['day_from'] ?? 1)));
        $dayTo = max($dayFrom, min($daysInMonth, (int) ($filters['day_to'] ?? $daysInMonth)));

        $from = Carbon::create($year, $month, $dayFrom)->startOfDay();
        $toExclusive = Carbon::create($year, $month, $dayTo)->startOfDay()->addDay();
        $classId = filled($filters['class'] ?? null) ? (string) $filters['class'] : null;

        $siteName = $route->site?->name ?? '—';
        $dumpName = $route->dumpSite?->name ?? '—';
        $routeLabel = $orientation === 'dump_site'
            ? "{$dumpName} – {$siteName}"
            : "{$siteName} – {$dumpName}";

        $days = range($dayFrom, $dayTo);
        $rowsByVehicle = [];

        foreach ($this->reports->dayCounts($route->id, $from, $toExclusive, $classId) as $row) {
            $vehicleId = (string) $row->vehicle_id;
            $day = (int) Carbon::parse((string) $row->haul_day)->day;
            $trips = (int) $row->trips;

            if (! isset($rowsByVehicle[$vehicleId])) {
                $rowsByVehicle[$vehicleId] = [
                    'plate' => (string) $row->plate,
                    'class_name' => filled($row->class_name) ? (string) $row->class_name : '—',
                    'counts' => [],
                    'total' => 0,
                ];
            }

            $rowsByVehicle[$vehicleId]['counts'][$day] = ($rowsByVehicle[$vehicleId]['counts'][$day] ?? 0) + $trips;
            $rowsByVehicle[$vehicleId]['total'] += $trips;
        }

        $rows = array_values($rowsByVehicle);

        if ($showClass) {
            usort($rows, function (array $a, array $b): int {
                $classCmp = strcasecmp($a['class_name'] ?? '', $b['class_name'] ?? '');

                return $classCmp !== 0 ? $classCmp : strcasecmp($a['plate'], $b['plate']);
            });
        } else {
            usort($rows, fn (array $a, array $b): int => strcasecmp($a['plate'], $b['plate']));
        }

        $dayTotals = [];
        $grandTotal = 0;

        foreach ($days as $day) {
            $sum = 0;

            foreach ($rows as $row) {
                $sum += $row['counts'][$day] ?? 0;
            }

            $dayTotals[$day] = $sum;
            $grandTotal += $sum;
        }

        $monthLabel = SiteExitReportService::MONTHS[$month];

        return [
            'title' => 'Aylık Koçanlar',
            'subtitle' => "{$routeLabel} · {$year} {$monthLabel} · {$dayFrom}–{$dayTo}",
            'route_label' => $routeLabel,
            'orientation' => $orientation,
            'show_class' => $showClass,
            'year' => $year,
            'month' => $month,
            'month_label' => $monthLabel,
            'from' => $from->toDateString(),
            'to' => Carbon::create($year, $month, $dayTo)->toDateString(),
            'days' => $days,
            'rows' => $rows,
            'day_totals' => $dayTotals,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * @param  array{route: string, year: int, month: int, day_from?: int, day_to?: int, class?: string|null, orientation: 'site_dump'|'dump_site'}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);

        abort_if($report === null, 404);

        $filename = sprintf(
            'aylik-kocanlar-%04d-%02d.csv',
            $report['year'],
            $report['month'],
        );

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            $header = ['Plaka'];

            if ($report['show_class']) {
                $header[] = 'Araç Sınıfı';
            }

            foreach ($report['days'] as $day) {
                $header[] = (string) $day;
            }

            $header[] = 'Toplam';
            fputcsv($handle, $header, ';');

            foreach ($report['rows'] as $row) {
                $line = [$row['plate']];

                if ($report['show_class']) {
                    $line[] = $row['class_name'] ?? '—';
                }

                foreach ($report['days'] as $day) {
                    $count = $row['counts'][$day] ?? 0;
                    $line[] = $count > 0 ? $count : '-';
                }

                $line[] = $row['total'];
                fputcsv($handle, $line, ';');
            }

            $footer = ['Toplam'];

            if ($report['show_class']) {
                $footer[] = '';
            }

            foreach ($report['days'] as $day) {
                $footer[] = $report['day_totals'][$day] ?? 0;
            }

            $footer[] = $report['grand_total'];
            fputcsv($handle, $footer, ';');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
