<?php

namespace App\Services\Reports;

use App\Repositories\Reports\VehicleMonthlyReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleMonthlyReportService extends Service
{
    public function __construct(private VehicleMonthlyReportRepository $reports) {}

    /**
     * @return array{years: array<int, string>, months: array<int, string>}
     */
    public function formOptions(): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{vehicle: string, year: int, month: int}  $filters
     * @return array{
     *     title: string,
     *     plate: string,
     *     year: int,
     *     month: int,
     *     month_label: string,
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

        if ($vehicle === null) {
            return null;
        }

        $year = (int) $filters['year'];
        $month = (int) $filters['month'];
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->addMonth();

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($this->reports->haulTotals($vehicle->id, $from, $to) as $haul) {
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

        foreach ($this->reports->fuelTotals($vehicle->id, $from, $to) as $fuel) {
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

        foreach ($this->reports->payments($vehicle->id, $from, $to) as $payment) {
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

        foreach ($this->reports->receivables($vehicle->id, $from, $to) as $receivable) {
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

        foreach ($this->reports->paidFines($vehicle->id, $from, $to) as $fine) {
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

        $stats = $this->reports->footerStats($vehicle->id, $from, $to);
        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        return [
            'title' => 'Aylık Plaka Raporu',
            'plate' => $vehicle->plate,
            'year' => $year,
            'month' => $month,
            'month_label' => SiteExitReportService::MONTHS[$month],
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
     * @param  array{vehicle: string, year: int, month: int}  $filters
     */
    public function exportCsv(array $filters): ?StreamedResponse
    {
        $report = $this->report($filters);

        if ($report === null) {
            return null;
        }

        $filename = sprintf(
            'plaka-aylik-%s-%04d-%02d.csv',
            str($report['plate'])->slug('_'),
            $report['year'],
            $report['month'],
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

            $index = 0;

            foreach ($report['rows'] as $row) {
                if ($row['type'] === 'carryover') {
                    continue;
                }

                $index++;
                $quantity = '';

                if ($row['quantity'] !== null) {
                    $quantity = $row['quantity_unit'] === 'lt'
                        ? number_format($row['quantity'], 2, ',', '.').' lt'
                        : (string) (int) $row['quantity'];
                }

                fputcsv($handle, [
                    $index,
                    $row['date'] ?? '',
                    $row['description'],
                    $quantity,
                    $row['unit_price'] !== null ? number_format($row['unit_price'], 2, ',', '.') : '',
                    $row['debit'] !== null ? number_format($row['debit'], 2, ',', '.') : '',
                    $row['credit'] !== null ? number_format($row['credit'], 2, ',', '.') : '',
                ], ';');
            }

            $carryover = collect($report['rows'])->firstWhere('type', 'carryover');

            fputcsv($handle, [
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
}
