<?php

namespace App\Services\Reports;

use App\Enums\PaymentMethod;
use App\Repositories\Reports\DumpSitePaymentReportRepository;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DumpSitePaymentReportService extends Service
{
    public function __construct(private DumpSitePaymentReportRepository $reports) {}

    /**
     * @return array{dumpSites: array<string, string>, periods: array<string, string>, years: array<int, string>, months: array<int, string>}
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
            'periods' => VehicleBalanceReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{dump_site: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     dump_site_name: string,
     *     period: 'month'|'range',
     *     period_label: string,
     *     year: ?int,
     *     month: ?int,
     *     month_label: ?string,
     *     from: string,
     *     to: string,
     *     dump_rows: list<array{class_name: string, trips: int, tonnage: float, amount: float}>,
     *     total_trips: int,
     *     total_tonnage: float,
     *     total_dump_amount: float,
     *     payments: list<array{occurred_on: string, description: ?string, payment_method: string, amount: float}>,
     *     total_payment_amount: float,
     *     opening_dump: float,
     *     opening_payment: float,
     *     opening: float,
     *     balance: float
     * }|null
     */
    public function report(array $filters): ?array
    {
        $dumpSite = $this->reports->findDumpSite($filters['dump_site']);

        if ($dumpSite === null || $dumpSite->party_id === null) {
            return null;
        }

        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);

        $dumpRows = [];
        $totalTrips = 0;
        $totalTonnage = 0.0;
        $totalDumpAmount = 0.0;

        foreach ($this->reports->dumpByClass($dumpSite->id, $from, $toExclusive) as $row) {
            $trips = (int) $row->trips;
            $tonnage = round((float) $row->tonnage, 2);
            $amount = round((float) $row->amount, 2);
            $className = filled($row->class_name) ? (string) $row->class_name : 'Sınıfsız';

            $dumpRows[] = [
                'class_name' => $className,
                'trips' => $trips,
                'tonnage' => $tonnage,
                'amount' => $amount,
            ];

            $totalTrips += $trips;
            $totalTonnage += $tonnage;
            $totalDumpAmount += $amount;
        }

        $payments = [];
        $totalPaymentAmount = 0.0;

        foreach ($this->reports->payments($dumpSite->id, $from, $toExclusive) as $row) {
            $amount = round((float) $row->amount, 2);
            $method = PaymentMethod::tryFrom((string) $row->payment_method)?->label() ?? '—';

            if (filled($row->cheque_no)) {
                $method .= ' ('.$row->cheque_no.')';
            }

            $payments[] = [
                'occurred_on' => (string) $row->occurred_on,
                'description' => $row->description,
                'payment_method' => $method,
                'amount' => $amount,
            ];

            $totalPaymentAmount += $amount;
        }

        $opening = $this->reports->openingTotals($dumpSite->id, $from);
        $closing = $this->reports->closingTotals($dumpSite->id, $toExclusive);

        return [
            'title' => 'Döküm Yeri Ödeme Raporu',
            'subtitle' => "{$dumpSite->name} {$meta['label']} Raporu",
            'dump_site_name' => $dumpSite->name,
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'dump_rows' => $dumpRows,
            'total_trips' => $totalTrips,
            'total_tonnage' => round($totalTonnage, 2),
            'total_dump_amount' => round($totalDumpAmount, 2),
            'payments' => $payments,
            'total_payment_amount' => round($totalPaymentAmount, 2),
            'opening_dump' => $opening['credit'],
            'opening_payment' => $opening['debit'],
            'opening' => $opening['net'],
            'balance' => $closing['net'],
        ];
    }

    /**
     * @param  array{dump_site: string, period: 'month'|'range', year?: int, month?: int, from?: string, to?: string}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);

        abort_if($report === null, 404);

        $filename = $report['period'] === 'month'
            ? sprintf('dokum-yeri-odeme-%04d-%02d.csv', $report['year'], $report['month'])
            : sprintf('dokum-yeri-odeme-%s_%s.csv', $report['from'], $report['to']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Araç Tipi', 'Sefer Sayısı', 'Tonaj', 'Toplam Fiyat'], ';');

            foreach ($report['dump_rows'] as $row) {
                fputcsv($handle, [
                    $row['class_name'],
                    $row['trips'],
                    number_format($row['tonnage'], 2, ',', '.'),
                    number_format($row['amount'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($handle, [
                'Toplam Döküm',
                $report['total_trips'],
                number_format($report['total_tonnage'], 2, ',', '.'),
                number_format($report['total_dump_amount'], 2, ',', '.'),
            ], ';');

            fputcsv($handle, [], ';');
            fputcsv($handle, ['Ödeme Açıklama', 'Ödeme Tarih', 'Ödeme Tipi', 'Ödeme Miktarı'], ';');

            foreach ($report['payments'] as $payment) {
                fputcsv($handle, [
                    $payment['description'] ?? '',
                    Carbon::parse($payment['occurred_on'])->format('d.m.Y'),
                    $payment['payment_method'],
                    number_format($payment['amount'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($handle, [
                'Toplam Ödeme',
                '',
                '',
                number_format($report['total_payment_amount'], 2, ',', '.'),
            ], ';');

            fputcsv($handle, [
                'Devir',
                'Döküm : '.number_format($report['opening_dump'], 2, ',', '.'),
                'Ödeme : '.number_format($report['opening_payment'], 2, ',', '.'),
                number_format($report['opening'], 2, ',', '.'),
            ], ';');

            fputcsv($handle, [
                'Bakiye',
                '',
                '',
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
