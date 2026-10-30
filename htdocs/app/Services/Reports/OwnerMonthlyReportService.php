<?php

namespace App\Services\Reports;

use App\Models\Vehicle;
use App\Repositories\Reports\OwnerMonthlyReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerMonthlyReportService extends Service
{
    public function __construct(private OwnerMonthlyReportRepository $reports) {}

    /**
     * @return array{owners: array<string, string>, periods: array<string, string>, years: array<int, string>, months: array<int, string>}
     */
    public function formOptions(?string $includeOwner = null): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            'owners' => $this->reports->ownerOptions($includeOwner),
            'periods' => SiteExitReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{owner: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     * @return array{
     *     title: string,
     *     owner: string,
     *     owner_name: string,
     *     period: 'month'|'range',
     *     period_label: string,
     *     year: ?int,
     *     month: ?int,
     *     month_label: ?string,
     *     from: string,
     *     to: string,
     *     vehicles: list<array{
     *         plate: string,
     *         rows: list<array{
     *             type: string,
     *             date: string|null,
     *             description: string,
     *             invoice_id?: string,
     *             quantity: float|null,
     *             quantity_unit: string|null,
     *             unit_price: float|null,
     *             debit: float|null,
     *             credit: float|null,
     *         }>,
     *         trips: int,
     *         fuel_liters: float,
     *         fuel_receipts: int,
     *         fuel_haul_tickets: int,
     *         machine_hours: float,
     *         machine_tickets: int,
     *         total_debit: float,
     *         total_credit: float,
     *     }>,
     *     operator_works: list<array{
     *         type: string,
     *         date: string|null,
     *         description: string,
     *         quantity: float|null,
     *         quantity_unit: string|null,
     *         unit_price: float|null,
     *         debit: float|null,
     *         credit: float|null,
     *     }>,
     *     owner_payments: list<array{
     *         type: string,
     *         date: string|null,
     *         description: string,
     *         quantity: float|null,
     *         quantity_unit: string|null,
     *         unit_price: float|null,
     *         debit: float|null,
     *         credit: float|null,
     *     }>,
     *     carryover: array{
     *         type: string,
     *         date: string|null,
     *         description: string,
     *         quantity: float|null,
     *         quantity_unit: string|null,
     *         unit_price: float|null,
     *         debit: float|null,
     *         credit: float|null,
     *     },
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
        $owner = $this->reports->findOwner($filters['owner']);

        if ($owner === null) {
            return null;
        }

        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);

        $vehicles = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $trips = 0;
        $fuelLiters = 0.0;
        $fuelReceipts = 0;
        $fuelHaulTickets = 0;

        foreach ($this->reports->activeVehicles($owner->id, $from, $toExclusive) as $vehicle) {
            $block = $this->vehicleBlock($owner->id, $vehicle, $from, $toExclusive);
            $vehicles[] = $block;
            $totalDebit += $block['total_debit'];
            $totalCredit += $block['total_credit'];
            $trips += $block['trips'];
            $fuelLiters += $block['fuel_liters'];
            $fuelReceipts += $block['fuel_receipts'];
            $fuelHaulTickets += $block['fuel_haul_tickets'];
        }

        $operatorWorks = [];

        foreach ($this->reports->operatorTotals($owner->id, $from, $toExclusive) as $work) {
            $hours = round((float) $work->hours, 2);
            $amount = round((float) $work->amount, 2);

            $operatorWorks[] = [
                'type' => 'operator',
                'date' => null,
                'description' => $work->plate.' · '.$work->site_name.' - Operatörlük',
                'quantity' => $hours,
                'quantity_unit' => 'saat',
                'unit_price' => $hours > 0 ? round($amount / $hours, 4) : 0.0,
                'debit' => null,
                'credit' => $amount,
            ];

            $totalCredit += $amount;
        }

        $ownerPayments = [];

        foreach ($this->reports->ownerPayments($owner->id, $from, $toExclusive) as $payment) {
            $amount = round((float) $payment->amount, 2);

            $ownerPayments[] = [
                'type' => 'owner_payment',
                'date' => Carbon::parse($payment->occurred_on)->toDateString(),
                'description' => filled($payment->description) ? (string) $payment->description : 'Plaka sahibi ödemesi',
                'quantity' => null,
                'quantity_unit' => null,
                'unit_price' => null,
                'debit' => $amount,
                'credit' => null,
            ];

            $totalDebit += $amount;
        }

        $carryover = $this->reports->carryoverBalance($owner->id, $from);
        $carryoverDebit = null;
        $carryoverCredit = null;

        if ($carryover > 0) {
            $carryoverDebit = $carryover;
            $totalDebit += $carryover;
        } elseif ($carryover < 0) {
            $carryoverCredit = abs($carryover);
            $totalCredit += $carryoverCredit;
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        return [
            'title' => 'Plaka Sahibi Aylık Raporu',
            'owner' => $owner->id,
            'owner_name' => $owner->name,
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'vehicles' => $vehicles,
            'operator_works' => $operatorWorks,
            'owner_payments' => $ownerPayments,
            'carryover' => [
                'type' => 'carryover',
                'date' => null,
                'description' => 'Geçmiş Dönemler',
                'quantity' => null,
                'quantity_unit' => null,
                'unit_price' => null,
                'debit' => $carryoverDebit,
                'credit' => $carryoverCredit,
            ],
            'trips' => $trips,
            'fuel_liters' => round($fuelLiters, 2),
            'fuel_receipts' => $fuelReceipts,
            'fuel_haul_tickets' => $fuelHaulTickets,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => round($totalCredit - $totalDebit, 2),
        ];
    }

    /**
     * @param  array{owner: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     */
    public function exportCsv(array $filters): ?StreamedResponse
    {
        $report = $this->report($filters);

        if ($report === null) {
            return null;
        }

        $filename = $report['period'] === 'month'
            ? sprintf(
                'plaka-sahibi-aylik-%s-%04d-%02d.csv',
                str($report['owner_name'])->slug('_'),
                $report['year'],
                $report['month'],
            )
            : sprintf(
                'plaka-sahibi-aylik-%s-%s_%s.csv',
                str($report['owner_name'])->slug('_'),
                $report['from'],
                $report['to'],
            );

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Plaka',
                'Sıra',
                'Tarih',
                'Açıklama',
                'Toplam',
                'Dönem Ortalama Birim Fiyatı',
                'Borç',
                'Alacak',
            ], ';');

            $index = 0;

            foreach ($report['vehicles'] as $vehicle) {
                foreach ($vehicle['rows'] as $row) {
                    $index++;
                    fputcsv($handle, [
                        $vehicle['plate'],
                        $index,
                        $row['date'] ?? '',
                        $row['description'],
                        $this->formatQuantity($row),
                        $row['unit_price'] !== null ? number_format($row['unit_price'], 2, ',', '.') : '',
                        $row['debit'] !== null ? number_format($row['debit'], 2, ',', '.') : '',
                        $row['credit'] !== null ? number_format($row['credit'], 2, ',', '.') : '',
                    ], ';');
                }

                fputcsv($handle, [
                    $vehicle['plate'],
                    '',
                    '',
                    sprintf(
                        'Sefer:%d Yakıt:%s lt %d yakıt fişi %d hareket fişi%s',
                        $vehicle['trips'],
                        number_format($vehicle['fuel_liters'], 2, ',', '.'),
                        $vehicle['fuel_receipts'],
                        $vehicle['fuel_haul_tickets'],
                        $vehicle['machine_tickets'] > 0
                            ? sprintf(' Makine:%s saat %d fiş', number_format($vehicle['machine_hours'], 2, ',', '.'), $vehicle['machine_tickets'])
                            : '',
                    ),
                    '',
                    '',
                    number_format($vehicle['total_debit'], 2, ',', '.'),
                    number_format($vehicle['total_credit'], 2, ',', '.'),
                ], ';');
            }

            foreach ([...$report['operator_works'], ...$report['owner_payments']] as $row) {
                $index++;
                fputcsv($handle, [
                    'Plaka sahibi',
                    $index,
                    $row['date'] ?? '',
                    $row['description'],
                    $this->formatQuantity($row),
                    $row['unit_price'] !== null ? number_format($row['unit_price'], 2, ',', '.') : '',
                    $row['debit'] !== null ? number_format($row['debit'], 2, ',', '.') : '',
                    $row['credit'] !== null ? number_format($row['credit'], 2, ',', '.') : '',
                ], ';');
            }

            $carryover = $report['carryover'];

            fputcsv($handle, [
                '',
                '',
                '',
                'Geçmiş Dönemler',
                '',
                '',
                $carryover['debit'] !== null ? number_format($carryover['debit'], 2, ',', '.') : '',
                $carryover['credit'] !== null ? number_format($carryover['credit'], 2, ',', '.') : '',
            ], ';');

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
     * @return array{
     *     plate: string,
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
     * }
     */
    private function vehicleBlock(string $partyId, Vehicle $vehicle, Carbon $from, Carbon $to): array
    {
        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($this->reports->haulTotals($partyId, $vehicle->id, $from, $to) as $haul) {
            $tripCount = (int) $haul->trips;
            $amount = round((float) $haul->amount, 2);
            $unitPrice = $tripCount > 0 ? round($amount / $tripCount, 4) : 0.0;

            $rows[] = [
                'type' => 'haul',
                'date' => null,
                'description' => trim($haul->site_name.' - '.$haul->dump_site_name),
                'quantity' => (float) $tripCount,
                'quantity_unit' => null,
                'unit_price' => $unitPrice,
                'debit' => null,
                'credit' => $amount,
            ];

            $totalCredit += $amount;
        }

        foreach ($this->reports->machineTotals($partyId, $vehicle->id, $from, $to) as $work) {
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

        foreach ($this->reports->fuelTotals($partyId, $vehicle->id, $from, $to) as $fuel) {
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

        foreach ($this->reports->materialTotals($partyId, $vehicle->id, $from, $to) as $invoice) {
            $amount = round((float) $invoice->total, 2);

            $rows[] = [
                'type' => 'material',
                'date' => Carbon::parse($invoice->issued_on)->toDateString(),
                'description' => filled($invoice->description)
                    ? (string) $invoice->description
                    : 'Malzeme · '.$invoice->invoice_no,
                'invoice_id' => (string) $invoice->id,
                'quantity' => null,
                'quantity_unit' => null,
                'unit_price' => null,
                'debit' => $amount,
                'credit' => null,
            ];

            $totalDebit += $amount;
        }

        foreach ($this->reports->payments($partyId, $vehicle->id, $from, $to) as $payment) {
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

        foreach ($this->reports->receivables($partyId, $vehicle->id, $from, $to) as $receivable) {
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

        foreach ($this->reports->paidFines($partyId, $vehicle->id, $from, $to) as $fine) {
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

        foreach ($this->reports->transferLines($partyId, $vehicle->id, $from, $to) as $line) {
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

        $stats = $this->reports->vehicleStats($partyId, $vehicle->id, $from, $to);

        return [
            'plate' => $vehicle->plate,
            'rows' => $rows,
            'trips' => $stats['trips'],
            'fuel_liters' => $stats['liters'],
            'fuel_receipts' => $stats['receipts'],
            'fuel_haul_tickets' => $stats['haul_tickets'],
            'machine_hours' => $stats['machine_hours'],
            'machine_tickets' => $stats['machine_tickets'],
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
        ];
    }

    /**
     * @param  array{quantity: float|null, quantity_unit: string|null}  $row
     */
    private function formatQuantity(array $row): string
    {
        if ($row['quantity'] === null) {
            return '';
        }

        return match ($row['quantity_unit']) {
            'lt', 'saat' => number_format($row['quantity'], 2, ',', '.').' '.$row['quantity_unit'],
            default => (string) (int) $row['quantity'],
        };
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
