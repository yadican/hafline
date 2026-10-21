<?php

namespace App\Services\Reports;

use App\Repositories\Reports\VehicleSiteMonthlyReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleSiteMonthlyReportService extends Service
{
    public function __construct(private VehicleSiteMonthlyReportRepository $reports) {}

    /**
     * @return array{sites: array<string, string>, periods: array<string, string>, years: array<int, string>, months: array<int, string>}
     */
    public function formOptions(): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            'sites' => $this->reports->sites(),
            'periods' => SiteExitReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{vehicle: string, site: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     * @return array{
     *     title: string,
     *     plate: string,
     *     site_name: string,
     *     period: 'month'|'range',
     *     period_label: string,
     *     year: ?int,
     *     month: ?int,
     *     month_label: ?string,
     *     from: string,
     *     to: string,
     *     rows: list<array{
     *         type: string,
     *         date: string|null,
     *         description: string,
     *         quantity: float|null,
     *         quantity_unit: string|null,
     *         unit_price: float|null,
     *         debit: float|null,
     *         credit: float|null,
     *     }>,
     *     trips: int,
     *     fuel_liters: float,
     *     fuel_receipts: int,
     *     fuel_haul_tickets: int,
     *     total_debit: float,
     *     total_credit: float,
     *     balance: float,
     * }|null
     */
    public function report(array $filters): ?array
    {
        $vehicle = $this->reports->findVehicle($filters['vehicle']);
        $site = $this->reports->findSite($filters['site']);

        if ($vehicle === null || $site === null) {
            return null;
        }

        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($this->reports->haulTotals($vehicle->id, $site->id, $from, $toExclusive) as $haul) {
            $trips = (int) $haul->trips;
            $amount = round((float) $haul->amount, 2);
            $unitPrice = $trips > 0 ? round($amount / $trips, 4) : 0.0;

            $rows[] = [
                'type' => 'haul',
                'date' => null,
                'description' => trim($haul->site_name.' - '.$haul->dump_site_name),
                'quantity' => (float) $trips,
                'quantity_unit' => null,
                'unit_price' => $unitPrice,
                'debit' => null,
                'credit' => $amount,
            ];

            $totalCredit += $amount;
        }

        foreach ($this->reports->fuelTotals($vehicle->id, $site->id, $from, $toExclusive) as $fuel) {
            $liters = round((float) $fuel->liters, 2);
            $amount = round((float) $fuel->amount, 2);
            $unitPrice = $liters > 0 ? round($amount / $liters, 4) : 0.0;

            $rows[] = [
                'type' => 'fuel',
                'date' => null,
                'description' => (string) $fuel->station_name,
                'quantity' => $liters,
                'quantity_unit' => 'lt',
                'unit_price' => $unitPrice,
                'debit' => $amount,
                'credit' => null,
            ];

            $totalDebit += $amount;
        }

        $stats = $this->reports->footerStats($vehicle->id, $site->id, $from, $toExclusive);
        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        return [
            'title' => 'Plaka Şantiye Bazlı Aylık Hareket Raporu',
            'plate' => $vehicle->plate,
            'site_name' => $site->name,
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'rows' => $rows,
            'trips' => $stats['trips'],
            'fuel_liters' => $stats['liters'],
            'fuel_receipts' => $stats['receipts'],
            'fuel_haul_tickets' => $stats['haul_tickets'],
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => round($totalCredit - $totalDebit, 2),
        ];
    }

    /**
     * @param  array{vehicle: string, site: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     */
    public function exportCsv(array $filters): ?StreamedResponse
    {
        $report = $this->report($filters);

        if ($report === null) {
            return null;
        }

        $filename = $report['period'] === 'month'
            ? sprintf(
                'plaka-santiye-%s-%s-%04d-%02d.csv',
                str($report['plate'])->slug('_'),
                str($report['site_name'])->slug('_'),
                $report['year'],
                $report['month'],
            )
            : sprintf(
                'plaka-santiye-%s-%s-%s_%s.csv',
                str($report['plate'])->slug('_'),
                str($report['site_name'])->slug('_'),
                $report['from'],
                $report['to'],
            );

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Sıra',
                'Tarih',
                'Açıklama',
                'Toplam',
                'Dönem Ortalama Birim Fiyatı',
                'Borç',
                'Alacak',
            ], ';');

            foreach ($report['rows'] as $index => $row) {
                $quantity = '';

                if ($row['quantity'] !== null) {
                    $quantity = $row['quantity_unit'] === 'lt'
                        ? number_format($row['quantity'], 2, ',', '.').' lt'
                        : (string) (int) $row['quantity'];
                }

                fputcsv($handle, [
                    $index + 1,
                    $row['date'] ?? '',
                    $row['description'],
                    $quantity,
                    $row['unit_price'] !== null ? number_format($row['unit_price'], 2, ',', '.') : '',
                    $row['debit'] !== null ? number_format($row['debit'], 2, ',', '.') : '',
                    $row['credit'] !== null ? number_format($row['credit'], 2, ',', '.') : '',
                ], ';');
            }

            fputcsv($handle, []);
            fputcsv($handle, [
                'Sefer',
                (string) $report['trips'],
                'Mazot',
                number_format($report['fuel_liters'], 2, ',', '.').' / '.$report['fuel_receipts'].' / '.$report['fuel_haul_tickets'],
            ], ';');
            fputcsv($handle, [
                'Alacak',
                number_format($report['total_credit'], 2, ',', '.'),
                'Borç',
                number_format($report['total_debit'], 2, ',', '.'),
                'Bakiye',
                number_format($report['balance'], 2, ',', '.'),
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
