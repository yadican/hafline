<?php

namespace App\Services\Reports;

use App\Repositories\Reports\TicketControlReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketControlReportService extends Service
{
    public const GROUPS = [
        'none' => 'Gruplama yok',
        'site' => 'Şantiye',
        'owner' => 'Plaka sahibi',
        'dump_site' => 'Döküm yeri',
    ];

    public function __construct(private TicketControlReportRepository $reports) {}

    /**
     * @return array{
     *     sites: array<string, string>,
     *     dumpSites: array<string, string>,
     *     owners: array<string, string>,
     *     periods: array<string, string>,
     *     years: array<int, string>,
     *     months: array<int, string>,
     *     groups: array<string, string>
     * }
     */
    public function formOptions(): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            ...$this->reports->formLookups(),
            'periods' => VehicleBalanceReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
            'groups' => self::GROUPS,
        ];
    }

    /**
     * @param  array{
     *     period: 'month'|'range',
     *     year?: int,
     *     month?: int,
     *     from?: string,
     *     to?: string,
     *     site?: string|null,
     *     dump_site?: string|null,
     *     owner?: string|null,
     *     vehicle?: string|null,
     *     plate?: string|null,
     *     group: 'none'|'site'|'owner'|'dump_site'
     * }  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     period: 'month'|'range',
     *     period_label: string,
     *     year: ?int,
     *     month: ?int,
     *     from: string,
     *     to: string,
     *     group: string,
     *     group_label: string,
     *     fuel_na: bool,
     *     handovers: list<array{label: ?string, total: int}>,
     *     hauls: list<array{label: ?string, total: int}>,
     *     fuels: list<array{label: ?string, total: int}>,
     *     handover_total: int,
     *     haul_total: int,
     *     fuel_total: int
     * }
     */
    public function report(array $filters): array
    {
        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);

        $queryFilters = [
            'from' => $from,
            'toExclusive' => $toExclusive,
            'site' => $filters['site'] ?? null,
            'dump_site' => $filters['dump_site'] ?? null,
            'owner' => $filters['owner'] ?? null,
            'vehicle' => $filters['vehicle'] ?? null,
            'group' => $filters['group'] ?? 'none',
        ];

        $fuelNa = ($queryFilters['group'] === 'dump_site') || filled($queryFilters['dump_site']);

        $handovers = $this->mapRows($this->reports->handoverTotals($queryFilters));
        $hauls = $this->mapRows($this->reports->haulTotals($queryFilters));
        $fuels = $fuelNa ? [] : $this->mapRows($this->reports->fuelTotals($queryFilters));

        return [
            'title' => 'Hareket Fişi Kontrol',
            'subtitle' => $meta['label'].' · '.self::GROUPS[$queryFilters['group']],
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'group' => $queryFilters['group'],
            'group_label' => self::GROUPS[$queryFilters['group']],
            'fuel_na' => $fuelNa,
            'handovers' => $handovers,
            'hauls' => $hauls,
            'fuels' => $fuels,
            'handover_total' => array_sum(array_column($handovers, 'total')),
            'haul_total' => array_sum(array_column($hauls, 'total')),
            'fuel_total' => array_sum(array_column($fuels, 'total')),
        ];
    }

    /**
     * @param  array{
     *     period: 'month'|'range',
     *     year?: int,
     *     month?: int,
     *     from?: string,
     *     to?: string,
     *     site?: string|null,
     *     dump_site?: string|null,
     *     owner?: string|null,
     *     vehicle?: string|null,
     *     plate?: string|null,
     *     group: 'none'|'site'|'owner'|'dump_site'
     * }  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);

        $filename = $report['period'] === 'month'
            ? sprintf('hareket-fisi-kontrol-%04d-%02d.csv', $report['year'], $report['month'])
            : sprintf('hareket-fisi-kontrol-%s_%s.csv', $report['from'], $report['to']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Tür', 'Grup', 'Adet'], ';');

            foreach ($report['handovers'] as $row) {
                fputcsv($handle, ['Fiş Teslim', $row['label'] ?? '', $row['total']], ';');
            }

            fputcsv($handle, ['Fiş Teslim', 'Toplam', $report['handover_total']], ';');

            foreach ($report['hauls'] as $row) {
                fputcsv($handle, ['Hareket', $row['label'] ?? '', $row['total']], ';');
            }

            fputcsv($handle, ['Hareket', 'Toplam', $report['haul_total']], ';');

            if ($report['fuel_na']) {
                fputcsv($handle, ['Yakıt', 'Döküm yeri için uygulanamaz', ''], ';');
            } else {
                foreach ($report['fuels'] as $row) {
                    fputcsv($handle, ['Yakıt', $row['label'] ?? '', $row['total']], ';');
                }

                fputcsv($handle, ['Yakıt', 'Toplam', $report['fuel_total']], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  Collection<int, object{group_key: ?string, group_label: ?string, total: string}>  $rows
     * @return list<array{label: ?string, total: int}>
     */
    private function mapRows($rows): array
    {
        $mapped = [];

        foreach ($rows as $row) {
            $total = (int) $row->total;

            if ($total === 0 && $row->group_key === null && $row->group_label === null) {
                continue;
            }

            $mapped[] = [
                'label' => $row->group_label !== null ? (string) $row->group_label : null,
                'total' => $total,
            ];
        }

        return $mapped;
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
