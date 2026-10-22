<?php

namespace App\Services\Reports;

use App\Enums\CariEntryKind;
use App\Enums\PartyRole;
use App\Repositories\Reports\CompanyBalanceReportRepository;
use App\Repositories\Reports\CompanyLedgerReportRepository;
use App\Services\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyLedgerReportService extends Service
{
    public function __construct(private CompanyLedgerReportRepository $reports) {}

    /**
     * @return array{roles: array<string, string>, parties: array<string, string>, periods: array<string, string>, years: array<int, string>, months: array<int, string>}
     */
    public function formOptions(?string $roleValue = null): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        $roles = [];
        foreach (CompanyBalanceReportRepository::reportRoles() as $role) {
            $roles[$role->value] = CariEntryKind::from($role->value)->label();
        }

        $parties = [];
        if ($roleValue !== null && $roleValue !== '') {
            $parties = $this->reports->partiesForRole(PartyRole::from($roleValue));
        }

        return [
            'roles' => $roles,
            'parties' => $parties,
            'periods' => VehicleBalanceReportService::PERIODS,
            'years' => $years,
            'months' => SiteExitReportService::MONTHS,
        ];
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, role: string, party?: ?string}  $filters
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
     *     parties: LengthAwarePaginator|null,
     * }
     */
    public function report(array $filters, bool $paginate = true): array
    {
        [$from, $toExclusive, $meta] = $this->resolvePeriod($filters);
        $role = PartyRole::from($filters['role']);
        $roleLabel = CariEntryKind::from($role->value)->label();
        $partyId = $filters['party'] ?? null;
        $singleParty = filled($partyId);

        $parties = $this->reports->partiesWithActivity(
            $role,
            $from,
            $toExclusive,
            $singleParty ? (string) $partyId : null,
            paginate: $paginate && ! $singleParty,
        );

        $partyRows = $parties instanceof LengthAwarePaginator
            ? collect($parties->items())
            : $parties;

        $partyIds = $partyRows->pluck('party_id')->map(fn ($id) => (string) $id)->all();
        $openings = $this->reports->openingBalances($role, $partyIds, $from);
        $linesByParty = $this->reports->periodLines($role, $partyIds, $from, $toExclusive)
            ->groupBy('party_id');

        $groups = [];

        foreach ($partyRows as $party) {
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

        return [
            'title' => 'Firma Cari Raporu',
            'subtitle' => $roleLabel.' · '.$meta['label'],
            'role' => $role->value,
            'role_label' => $roleLabel,
            'show_fuel' => $role === PartyRole::Fuel,
            'show_market' => $role === PartyRole::Market,
            'show_party_column' => ! $singleParty,
            'period' => $meta['period'],
            'period_label' => $meta['label'],
            'year' => $meta['year'],
            'month' => $meta['month'],
            'month_label' => $meta['month_label'],
            'from' => $meta['from'],
            'to' => $meta['to'],
            'groups' => $groups,
            'parties' => $parties instanceof LengthAwarePaginator ? $parties : null,
        ];
    }

    /**
     * @param  array{period: 'month'|'range', year?: int, month?: int, from?: string, to?: string, role: string, party?: ?string}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters, paginate: false);

        $filename = $report['period'] === 'month'
            ? sprintf('firma-cari-%s-%04d-%02d.csv', $report['role'], $report['year'], $report['month'])
            : sprintf('firma-cari-%s-%s_%s.csv', $report['role'], $report['from'], $report['to']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            $headers = ['Firma', 'Tarih', 'Belge No', 'Açıklama'];
            if ($report['show_fuel']) {
                $headers = array_merge($headers, ['Litre', 'Birim Fiyat', 'Yakıt Yeri']);
            }
            if ($report['show_market']) {
                $headers = array_merge($headers, ['Plaka', 'Araç Sahibi']);
            }
            $headers = array_merge($headers, ['Borç', 'Alacak', 'Bakiye']);

            fputcsv($handle, $headers, ';');

            foreach ($report['groups'] as $group) {
                fputcsv($handle, array_merge(
                    [$group['party_name'], '', '', 'Geçmiş Dönemler (Devir)'],
                    $report['show_fuel'] ? ['', '', ''] : [],
                    $report['show_market'] ? ['', ''] : [],
                    [
                        $group['opening'] > 0 ? number_format($group['opening'], 2, ',', '.') : '',
                        $group['opening'] < 0 ? number_format(abs($group['opening']), 2, ',', '.') : '',
                        number_format($group['opening'], 2, ',', '.'),
                    ],
                ), ';');

                foreach ($group['lines'] as $line) {
                    $row = [
                        $group['party_name'],
                        Carbon::parse($line['occurred_on'])->format('d.m.Y'),
                        $line['document_no'] ?? '',
                        $line['description'] ?? '',
                    ];

                    if ($report['show_fuel']) {
                        $row[] = $line['liters'] !== null ? number_format($line['liters'], 2, ',', '.') : '';
                        $row[] = $line['unit_price'] !== null ? number_format($line['unit_price'], 4, ',', '.') : '';
                        $row[] = $line['fuel_station_name'] ?? '';
                    }

                    if ($report['show_market']) {
                        $row[] = $line['plate'] ?? '';
                        $row[] = $line['owner_name'] ?? '';
                    }

                    $row[] = $line['debit'] > 0 ? number_format($line['debit'], 2, ',', '.') : '';
                    $row[] = $line['credit'] > 0 ? number_format($line['credit'], 2, ',', '.') : '';
                    $row[] = number_format($line['balance'], 2, ',', '.');

                    fputcsv($handle, $row, ';');
                }

                fputcsv($handle, array_merge(
                    [$group['party_name'], '', '', 'Toplam / Kapanış'],
                    $report['show_fuel'] ? ['', '', ''] : [],
                    $report['show_market'] ? ['', ''] : [],
                    [
                        number_format($group['total_debit'], 2, ',', '.'),
                        number_format($group['total_credit'], 2, ',', '.'),
                        number_format($group['closing'], 2, ',', '.'),
                    ],
                ), ';');
            }

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
