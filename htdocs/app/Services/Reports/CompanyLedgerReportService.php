<?php

namespace App\Services\Reports;

use App\Enums\CariEntryKind;
use App\Enums\PartyRole;
use App\Repositories\Reports\CompanyBalanceReportRepository;
use App\Repositories\Reports\CompanyLedgerReportRepository;
use App\Services\Reports\Export\ReportColumn;
use App\Services\Reports\Export\ReportDocument;
use App\Services\Reports\Export\ReportRow;
use App\Services\Reports\Export\ReportSection;
use App\Services\Service;
use Illuminate\Support\Carbon;

class CompanyLedgerReportService extends Service
{
    public function __construct(private CompanyLedgerReportRepository $reports) {}

    /**
     * @return array{
     *     roles: array<string, string>,
     *     parties: array<string, string>,
     *     partiesByRole: array<string, array<string, string>>,
     *     periods: array<string, string>,
     *     years: array<int, string>,
     *     months: array<int, string>
     * }
     */
    public function formOptions(?string $roleValue = null): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        $roles = [];
        $partiesByRole = [];
        foreach (CompanyBalanceReportRepository::reportRoles() as $role) {
            $roles[$role->value] = CariEntryKind::from($role->value)->label();
            $partiesByRole[$role->value] = $this->reports->partiesForRole($role);
        }

        $parties = [];
        if ($roleValue !== null && $roleValue !== '' && isset($partiesByRole[$roleValue])) {
            $parties = $partiesByRole[$roleValue];
        }

        return [
            'roles' => $roles,
            'parties' => $parties,
            'partiesByRole' => $partiesByRole,
            'periods' => VehicleBalanceReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, role: string, party: string}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     role: string,
     *     role_label: string,
     *     show_fuel: bool,
     *     show_market: bool,
     *     show_party_column: bool,
     *     period: 'month'|'range',
     *     period_label: string,
     *     year: ?int,
     *     month: ?int,
     *     month_label: ?string,
     *     from: string,
     *     to: string,
     *     groups: list<array{
     *         party_id: string,
     *         party_name: string,
     *         opening: float,
     *         lines: list<array{
     *             occurred_on: string,
     *             document_no: ?string,
     *             description: ?string,
     *             debit: float,
     *             credit: float,
     *             balance: float,
     *             liters: ?float,
     *             unit_price: ?float,
     *             fuel_station_name: ?string,
     *             plate: ?string,
     *             owner_name: ?string
     *         }>,
     *         total_debit: float,
     *         total_credit: float,
     *         closing: float
     *     }>,
     *     parties: null,
     * }
     */
    public function report(array $filters): array
    {
        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);
        $role = PartyRole::from($filters['role']);
        $roleLabel = CariEntryKind::from($role->value)->label();
        $partyId = (string) $filters['party'];

        $parties = $this->reports->partiesWithActivity(
            $role,
            $from,
            $toExclusive,
            $partyId,
            paginate: false,
        );

        $partyIds = $parties->pluck('party_id')->map(fn ($id) => (string) $id)->all();
        $openings = $this->reports->openingBalances($role, $partyIds, $from);
        $linesByParty = $this->reports->periodLines($role, $partyIds, $from, $toExclusive)
            ->groupBy('party_id');

        $groups = [];

        foreach ($parties as $party) {
            $id = (string) $party->party_id;
            $opening = $openings[$id] ?? 0.0;
            $running = $opening;
            $totalDebit = 0.0;
            $totalCredit = 0.0;
            $lines = [];

            foreach ($linesByParty->get($id, collect()) as $line) {
                $debit = round((float) $line->debit, 2);
                $credit = round((float) $line->credit, 2);
                $running = round($running + $debit - $credit, 2);
                $totalDebit += $debit;
                $totalCredit += $credit;

                $lines[] = [
                    'occurred_on' => (string) $line->occurred_on,
                    'document_no' => $line->document_no,
                    'description' => $line->description,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $running,
                    'liters' => $line->liters !== null ? round((float) $line->liters, 2) : null,
                    'unit_price' => $line->unit_price !== null ? round((float) $line->unit_price, 4) : null,
                    'fuel_station_name' => $line->fuel_station_name,
                    'plate' => $line->plate,
                    'owner_name' => $line->owner_name,
                ];
            }

            $groups[] = [
                'party_id' => $id,
                'party_name' => (string) $party->name,
                'opening' => $opening,
                'lines' => $lines,
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'closing' => round($opening + $totalDebit - $totalCredit, 2),
            ];
        }

        $partyName = $groups[0]['party_name'] ?? null;

        return [
            'title' => 'Firma Cari Raporu',
            'subtitle' => ($partyName !== null ? $partyName.' · ' : '').$roleLabel.' · '.$meta['label'],
            'role' => $role->value,
            'role_label' => $roleLabel,
            'show_fuel' => $role === PartyRole::Fuel,
            'show_market' => $role === PartyRole::Market,
            'show_party_column' => false,
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'groups' => $groups,
            'parties' => null,
        ];
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, role: string, party: string}  $filters
     */
    public function exportDocument(array $filters): ReportDocument
    {
        $report = $this->report($filters);

        $filename = $report['period'] === 'month'
            ? sprintf('firma-cari-%s-%04d-%02d', $report['role'], $report['year'], $report['month'])
            : sprintf('firma-cari-%s-%s_%s', $report['role'], $report['from'], $report['to']);

        $columns = [
            ReportColumn::date('occurred_on', 'Tarih'),
            ReportColumn::text('document_no', 'Belge No'),
            ReportColumn::text('description', 'Açıklama'),
        ];

        if ($report['show_fuel']) {
            $columns[] = ReportColumn::number('liters', 'Litre', 2, 'lt');
            $columns[] = ReportColumn::money('unit_price', 'Birim Fiyat', 4);
            $columns[] = ReportColumn::text('fuel_station_name', 'Yakıt Yeri');
        }

        if ($report['show_market']) {
            $columns[] = ReportColumn::text('plate', 'Plaka', 12);
            $columns[] = ReportColumn::text('owner_name', 'Araç Sahibi');
        }

        $columns[] = ReportColumn::money('debit', 'Borç');
        $columns[] = ReportColumn::money('credit', 'Alacak');
        $columns[] = ReportColumn::balance('balance', 'Bakiye');

        $sections = [];

        foreach ($report['groups'] as $group) {
            $rows = [
                ReportRow::opening([
                    'description' => 'Geçmiş Dönemler (Devir)',
                    'debit' => $group['opening'] > 0 ? $group['opening'] : null,
                    'credit' => $group['opening'] < 0 ? abs($group['opening']) : null,
                    'balance' => $group['opening'],
                ]),
            ];

            foreach ($group['lines'] as $line) {
                $rows[] = ReportRow::data([
                    'occurred_on' => $line['occurred_on'],
                    'document_no' => $line['document_no'],
                    'description' => $line['description'],
                    'liters' => $line['liters'],
                    'unit_price' => $line['unit_price'],
                    'fuel_station_name' => $line['fuel_station_name'],
                    'plate' => $line['plate'],
                    'owner_name' => $line['owner_name'],
                    'debit' => $line['debit'] > 0 ? $line['debit'] : null,
                    'credit' => $line['credit'] > 0 ? $line['credit'] : null,
                    'balance' => $line['balance'],
                ]);
            }

            $rows[] = ReportRow::total('Toplam / Kapanış', [
                'debit' => $group['total_debit'],
                'credit' => $group['total_credit'],
                'balance' => $group['closing'],
            ]);

            $sections[] = new ReportSection($rows, count($report['groups']) > 1 ? $group['party_name'] : null);
        }

        if ($sections === []) {
            $sections[] = new ReportSection([]);
        }

        return new ReportDocument(
            title: $report['title'],
            subtitle: $report['subtitle'],
            filename: $filename,
            columns: $columns,
            sections: $sections,
            details: array_filter([
                'Cari türü' => $report['role_label'],
                'Firma' => $report['groups'][0]['party_name'] ?? null,
                'Dönem' => $report['period_label'],
            ], fn (?string $value): bool => $value !== null),
            landscape: $report['show_fuel'] || $report['show_market'],
            emptyMessage: 'Seçilen cari türü ve dönem için cari hareket bulunamadı.',
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
