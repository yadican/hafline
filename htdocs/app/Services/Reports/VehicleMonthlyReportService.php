<?php

namespace App\Services\Reports;

use App\Repositories\Reports\VehicleMonthlyReportRepository;
use App\Services\Reports\Export\ReportCell;
use App\Services\Reports\Export\ReportColumn;
use App\Services\Reports\Export\ReportDocument;
use App\Services\Reports\Export\ReportRow;
use App\Services\Reports\Export\ReportSection;
use App\Services\Service;
use Illuminate\Support\Carbon;

class VehicleMonthlyReportService extends Service
{
    public function __construct(private VehicleMonthlyReportRepository $reports) {}

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
            'periods' => SiteExitReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{vehicle: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     * @return array{
     *     title: string,
     *     plate: string,
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
     *     machine_hours: float,
     *     machine_tickets: int,
     *     total_debit: float,
     *     total_credit: float,
     *     balance: float,
     * }|null
     */
    public function report(array $filters): ?array
    {
        $vehicle = $this->reports->findVehicle($filters['vehicle']);

        if ($vehicle === null) {
            return null;
        }

        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($this->reports->haulTotals($vehicle->id, $from, $toExclusive) as $haul) {
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

        foreach ($this->reports->machineTotals($vehicle->id, $from, $toExclusive) as $work) {
            $hours = round((float) $work->hours, 2);
            $amount = round((float) $work->amount, 2);

            $rows[] = [
                'type' => 'machine',
                'date' => null,
                'description' => $work->site_name.' - Makine çalışması',
                'quantity' => $hours,
                'quantity_unit' => 'saat',
                'unit_price' => $hours > 0 ? round($amount / $hours, 4) : 0.0,
                'debit' => null,
                'credit' => $amount,
            ];

            $totalCredit += $amount;
        }

        foreach ($this->reports->fuelTotals($vehicle->id, $from, $toExclusive) as $fuel) {
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

        foreach ($this->reports->payments($vehicle->id, $from, $toExclusive) as $payment) {
            $amount = round((float) $payment->amount, 2);

            $rows[] = [
                'type' => 'payment',
                'date' => Carbon::parse($payment->occurred_on)->toDateString(),
                'description' => filled($payment->description) ? (string) $payment->description : 'Plaka ödeme',
                'quantity' => null,
                'quantity_unit' => null,
                'unit_price' => null,
                'debit' => $amount,
                'credit' => null,
            ];

            $totalDebit += $amount;
        }

        foreach ($this->reports->receivables($vehicle->id, $from, $toExclusive) as $receivable) {
            $amount = round((float) $receivable->amount, 2);

            $rows[] = [
                'type' => 'receivable',
                'date' => Carbon::parse($receivable->occurred_on)->toDateString(),
                'description' => filled($receivable->description) ? (string) $receivable->description : 'Plaka alacak',
                'quantity' => null,
                'quantity_unit' => null,
                'unit_price' => null,
                'debit' => null,
                'credit' => $amount,
            ];

            $totalCredit += $amount;
        }

        foreach ($this->reports->paidFines($vehicle->id, $from, $toExclusive) as $fine) {
            $amount = round((float) $fine->amount, 2);

            $rows[] = [
                'type' => 'fine',
                'date' => Carbon::parse($fine->paid_on)->toDateString(),
                'description' => filled($fine->description) ? (string) $fine->description : 'Ceza ödemesi',
                'quantity' => null,
                'quantity_unit' => null,
                'unit_price' => null,
                'debit' => $amount,
                'credit' => null,
            ];

            $totalDebit += $amount;
        }

        foreach ($this->reports->transferLines($vehicle->id, $from, $toExclusive) as $line) {
            $debit = round((float) $line->debit, 2);
            $credit = round((float) $line->credit, 2);

            $rows[] = [
                'type' => 'transfer',
                'date' => Carbon::parse($line->occurred_on)->toDateString(),
                'description' => (string) $line->description,
                'quantity' => null,
                'quantity_unit' => null,
                'unit_price' => null,
                'debit' => $debit > 0 ? $debit : null,
                'credit' => $credit > 0 ? $credit : null,
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        $carryover = $this->reports->carryoverBalance($vehicle->id, $from);
        $carryoverDebit = null;
        $carryoverCredit = null;

        if ($carryover > 0) {
            $carryoverDebit = $carryover;
            $totalDebit += $carryover;
        } elseif ($carryover < 0) {
            $carryoverCredit = abs($carryover);
            $totalCredit += $carryoverCredit;
        }

        $rows[] = [
            'type' => 'carryover',
            'date' => null,
            'description' => 'Geçmiş Dönemler',
            'quantity' => null,
            'quantity_unit' => null,
            'unit_price' => null,
            'debit' => $carryoverDebit,
            'credit' => $carryoverCredit,
        ];

        $stats = $this->reports->footerStats($vehicle->id, $from, $toExclusive);
        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        return [
            'title' => 'Aylık Plaka Raporu',
            'plate' => $vehicle->plate,
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
            'machine_hours' => $stats['machine_hours'],
            'machine_tickets' => $stats['machine_tickets'],
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => round($totalCredit - $totalDebit, 2),
        ];
    }

    /**
     * @param  array{vehicle: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     */
    public function exportDocument(array $filters): ?ReportDocument
    {
        $report = $this->report($filters);

        if ($report === null) {
            return null;
        }

        $plateSlug = str($report['plate'])->slug('_');
        $filename = $report['period'] === 'month'
            ? sprintf('plaka-aylik-%s-%04d-%02d', $plateSlug, $report['year'], $report['month'])
            : sprintf('plaka-aylik-%s-%s_%s', $plateSlug, $report['from'], $report['to']);

        $rows = [];
        $index = 0;
        $carryover = null;

        foreach ($report['rows'] as $row) {
            if ($row['type'] === 'carryover') {
                $carryover = $row;

                continue;
            }

            $rows[] = ReportRow::data([
                'index' => ++$index,
                'date' => $row['date'],
                'description' => $row['description'],
                'quantity' => match (true) {
                    $row['quantity'] === null => null,
                    $row['quantity_unit'] === null => new ReportCell((int) $row['quantity'], 0),
                    default => new ReportCell($row['quantity'], 2, $row['quantity_unit']),
                },
                'unit_price' => $row['unit_price'],
                'debit' => $row['debit'],
                'credit' => $row['credit'],
            ]);
        }

        if ($carryover !== null) {
            $rows[] = ReportRow::opening([
                'description' => $carryover['description'],
                'debit' => $carryover['debit'],
                'credit' => $carryover['credit'],
            ]);
        }

        $rows[] = ReportRow::total('TOPLAM', [
            'quantity' => new ReportCell($report['trips'], 0, 'sefer'),
            'debit' => $report['total_debit'],
            'credit' => $report['total_credit'],
        ]);

        $summary = [
            'Sefer' => new ReportCell($report['trips'], 0),
            'Mazot' => sprintf(
                '%s lt / %d fiş / %d',
                number_format($report['fuel_liters'], 2, ',', '.'),
                $report['fuel_receipts'],
                $report['fuel_haul_tickets'],
            ),
        ];

        if ($report['machine_tickets'] > 0) {
            $summary['Makine'] = sprintf(
                '%s saat / %d fiş',
                number_format($report['machine_hours'], 2, ',', '.'),
                $report['machine_tickets'],
            );
        }

        $summary['Alacak'] = ReportCell::money($report['total_credit']);
        $summary['Borç'] = ReportCell::money($report['total_debit']);
        $summary['Bakiye'] = ReportCell::money($report['balance']);

        return new ReportDocument(
            title: $report['title'],
            subtitle: $report['plate'].' · '.$report['period_label'],
            filename: $filename,
            columns: [
                ReportColumn::number('index', 'Sıra', width: 6),
                ReportColumn::date('date', 'Tarih'),
                ReportColumn::text('description', 'Açıklama'),
                ReportColumn::number('quantity', 'Toplam', width: 14),
                ReportColumn::money('unit_price', 'Dönem Ort. Birim Fiyatı'),
                ReportColumn::money('debit', 'Borç'),
                ReportColumn::money('credit', 'Alacak'),
            ],
            sections: [new ReportSection($rows)],
            details: [
                'Plaka' => $report['plate'],
                'Dönem' => $report['period_label'],
            ],
            summary: $summary,
        );
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
