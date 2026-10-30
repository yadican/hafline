<?php

namespace App\Services\Reports;

use App\Enums\InvoiceDirection;
use App\Repositories\Reports\InvoiceAgingReportRepository;
use App\Services\Reports\Export\ReportColumn;
use App\Services\Reports\Export\ReportDocument;
use App\Services\Reports\Export\ReportRow;
use App\Services\Reports\Export\ReportSection;
use App\Services\Service;
use Illuminate\Support\Carbon;

class InvoiceAgingReportService extends Service
{
    public const BUCKET_CURRENT = 'current';

    public const BUCKET_0_30 = 'd0_30';

    public const BUCKET_31_60 = 'd31_60';

    public const BUCKET_61_90 = 'd61_90';

    public const BUCKET_90_PLUS = 'd90_plus';

    /**
     * @return list<string>
     */
    public static function bucketKeys(): array
    {
        return [
            self::BUCKET_CURRENT,
            self::BUCKET_0_30,
            self::BUCKET_31_60,
            self::BUCKET_61_90,
            self::BUCKET_90_PLUS,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function bucketLabels(): array
    {
        return [
            self::BUCKET_CURRENT => 'Vadesi gelmedi',
            self::BUCKET_0_30 => '0–30 gün',
            self::BUCKET_31_60 => '31–60 gün',
            self::BUCKET_61_90 => '61–90 gün',
            self::BUCKET_90_PLUS => '90+ gün',
        ];
    }

    public function __construct(private InvoiceAgingReportRepository $reports) {}

    /**
     * @return array{directions: array<string, string>}
     */
    public function formOptions(): array
    {
        return [
            'directions' => [
                InvoiceDirection::Sale->value => 'Alacaklar',
                InvoiceDirection::Purchase->value => 'Borçlar',
            ],
        ];
    }

    /**
     * @param  array{direction: string}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     direction: string,
     *     direction_label: string,
     *     as_of: string,
     *     as_of_label: string,
     *     bucket_labels: array<string, string>,
     *     rows: list<array{name: string, current: float, d0_30: float, d31_60: float, d61_90: float, d90_plus: float, total: float}>,
     *     totals: array{current: float, d0_30: float, d31_60: float, d61_90: float, d90_plus: float, total: float},
     * }
     */
    public function report(array $filters): array
    {
        $direction = InvoiceDirection::from($filters['direction']);
        $directionLabel = $this->formOptions()['directions'][$direction->value];
        $today = Carbon::today();
        $empty = $this->emptyBuckets();

        $byParty = [];

        foreach ($this->reports->openRemainings($direction) as $invoice) {
            $remaining = round((float) $invoice->remaining, 2);
            $bucket = $this->bucketFor($invoice->due_on, $today);
            $partyId = (string) $invoice->party_id;

            if (! isset($byParty[$partyId])) {
                $byParty[$partyId] = [
                    'name' => (string) $invoice->name,
                    ...$empty,
                    'total' => 0.0,
                ];
            }

            $byParty[$partyId][$bucket] = round($byParty[$partyId][$bucket] + $remaining, 2);
            $byParty[$partyId]['total'] = round($byParty[$partyId]['total'] + $remaining, 2);
        }

        $rows = array_values($byParty);
        usort($rows, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        $totals = [...$empty, 'total' => 0.0];

        foreach ($rows as $row) {
            foreach ([...self::bucketKeys(), 'total'] as $key) {
                $totals[$key] = round($totals[$key] + $row[$key], 2);
            }
        }

        return [
            'title' => 'Fatura Yaşlandırma Raporu',
            'subtitle' => $directionLabel.' · '.$today->format('d.m.Y'),
            'direction' => $direction->value,
            'direction_label' => $directionLabel,
            'as_of' => $today->toDateString(),
            'as_of_label' => $today->format('d.m.Y'),
            'bucket_labels' => self::bucketLabels(),
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * @param  array{direction: string}  $filters
     */
    public function exportDocument(array $filters): ReportDocument
    {
        $report = $this->report($filters);
        $labels = $report['bucket_labels'];

        $rows = [];

        foreach ($report['rows'] as $row) {
            $rows[] = ReportRow::data([
                'name' => $row['name'],
                'current' => $row['current'] > 0 ? $row['current'] : null,
                'd0_30' => $row['d0_30'] > 0 ? $row['d0_30'] : null,
                'd31_60' => $row['d31_60'] > 0 ? $row['d31_60'] : null,
                'd61_90' => $row['d61_90'] > 0 ? $row['d61_90'] : null,
                'd90_plus' => $row['d90_plus'] > 0 ? $row['d90_plus'] : null,
                'total' => $row['total'],
            ]);
        }

        if ($rows !== []) {
            $rows[] = ReportRow::total('TOPLAM', [
                'current' => $report['totals']['current'],
                'd0_30' => $report['totals']['d0_30'],
                'd31_60' => $report['totals']['d31_60'],
                'd61_90' => $report['totals']['d61_90'],
                'd90_plus' => $report['totals']['d90_plus'],
                'total' => $report['totals']['total'],
            ]);
        }

        return new ReportDocument(
            title: $report['title'],
            subtitle: $report['subtitle'],
            filename: sprintf('fatura-yaslandirma-%s-%s', $report['direction'], $report['as_of']),
            columns: [
                ReportColumn::text('name', 'Cari', 28),
                ReportColumn::money('current', $labels[self::BUCKET_CURRENT]),
                ReportColumn::money('d0_30', $labels[self::BUCKET_0_30]),
                ReportColumn::money('d31_60', $labels[self::BUCKET_31_60]),
                ReportColumn::money('d61_90', $labels[self::BUCKET_61_90]),
                ReportColumn::money('d90_plus', $labels[self::BUCKET_90_PLUS]),
                ReportColumn::money('total', 'Toplam'),
            ],
            sections: [new ReportSection($rows)],
            details: [
                'Tür' => $report['direction_label'],
                'Tarih' => $report['as_of_label'],
            ],
            landscape: true,
            emptyMessage: 'Açık fatura bakiyesi bulunamadı.',
        );
    }

    /**
     * Classifies remaining by due date vs $today.
     * No due date or future due → current; otherwise overdue day count into 0–30 / 31–60 / 61–90 / 90+.
     */
    public function bucketFor(?string $dueOn, Carbon $today): string
    {
        if ($dueOn === null || $dueOn === '') {
            return self::BUCKET_CURRENT;
        }

        $due = Carbon::parse($dueOn)->startOfDay();
        $asOf = $today->copy()->startOfDay();

        if ($due->greaterThan($asOf)) {
            return self::BUCKET_CURRENT;
        }

        $overdueDays = (int) $due->diffInDays($asOf);

        return match (true) {
            $overdueDays <= 30 => self::BUCKET_0_30,
            $overdueDays <= 60 => self::BUCKET_31_60,
            $overdueDays <= 90 => self::BUCKET_61_90,
            default => self::BUCKET_90_PLUS,
        };
    }

    /**
     * @return array{current: float, d0_30: float, d31_60: float, d61_90: float, d90_plus: float}
     */
    private function emptyBuckets(): array
    {
        return [
            self::BUCKET_CURRENT => 0.0,
            self::BUCKET_0_30 => 0.0,
            self::BUCKET_31_60 => 0.0,
            self::BUCKET_61_90 => 0.0,
            self::BUCKET_90_PLUS => 0.0,
        ];
    }
}
