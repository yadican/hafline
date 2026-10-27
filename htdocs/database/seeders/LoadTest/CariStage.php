<?php

namespace Database\Seeders\LoadTest;

use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\CashCategory;
use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\LedgerEntry;
use App\Models\MachineWork;
use App\Models\SiteTransaction;
use App\Models\User;
use App\Services\CariEntryService;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cari movements derived from the operational tickets, posted in calendar order per account.
 *
 * Every payment is sized from the balance open on its own date (never more than that), so
 * running balances stay on the right side: plates, dumps, fuel firms and suppliers keep a
 * small open alacak, customers a small open borç, and the cash drawer never goes negative.
 * Invoices carry no payment method; only real payments show up in cash / bank reports.
 */
final class CariStage
{
    /** Weighted: mostly transfer, then cash; cheques and notes for larger plates. */
    private const PLATE_METHODS = [
        PaymentMethod::BankTransfer, PaymentMethod::BankTransfer, PaymentMethod::BankTransfer, PaymentMethod::BankTransfer,
        PaymentMethod::Cash, PaymentMethod::Cash, PaymentMethod::Cash,
        PaymentMethod::Cheque, PaymentMethod::Offset, PaymentMethod::PromissoryNote,
    ];

    private int $doc = 0;

    public function __construct(
        private Progress $progress,
        private CariEntryService $entries,
    ) {}

    public function run(CatalogStage $catalog, Carbon $from, Carbon $to): void
    {
        $this->progress->stage('Cari hareketleri (fiş bakiyesine göre, tarih sırasıyla)');

        $userId = User::query()->value('id');
        $categories = CashCategory::query()->where('active', true)->get();
        $banks = Bank::query()->where('active', true)->get();
        $done = 0;

        // Order matters: owner residuals and the cash drawer read ledger rows posted by earlier phases.
        $phases = [
            'plaka haftalık hakediş' => fn () => $this->queueVehicleSettlements($catalog, $from, $to),
            'döküm 15 günlük ödeme' => fn () => $this->queueDumpSettlements($catalog, $from, $to),
            'akaryakıt 15 günlük fatura + ödeme' => fn () => $this->queueFuelSettlements($catalog, $from, $to),
            'müşteri aylık hakediş + tahsilat' => fn () => $this->queueCustomerBilling($catalog, $from, $to),
            'tedarikçi fatura + ödeme' => fn () => $this->queueSuppliers($catalog, $from, $to, $categories),
            'piyasa sefer + tahsilat' => fn () => $this->queueMarket($catalog, $from, $to),
            'şantiye gelir / gider' => fn () => $this->queueSiteMovements($catalog, $from, $to, $categories),
            'sahip / operatör ay sonu' => fn () => $this->queueOwnerResiduals($catalog, $from, $to, $categories),
            'kasa' => fn () => $this->queueCashDrawer($from, $to, $categories, $banks),
        ];

        foreach ($phases as $label => $build) {
            $queue = $build();
            $this->progress->note($label.': '.number_format(count($queue)));
            $done = $this->flush($queue, $userId, $done);
        }

        $this->progress->done(number_format($done).' cari hareket');
    }

    /**
     * @param  list<array<string, mixed>>  $queue
     */
    private function flush(array $queue, ?string $userId, int $done): int
    {
        $total = count($queue);

        foreach ($queue as $index => $payload) {
            $this->entries->create($payload, $userId);
            $this->progress->batch($index + 1, $total, 'cari');
        }

        return $done + $total;
    }

    /**
     * Weekly plate payment (70–90% of the open alacak) on the plate's pay day, sometimes with a
     * mid-week cash advance. Balances are per vehicle and owner, so transfers settle cleanly.
     *
     * @return list<array<string, mixed>>
     */
    private function queueVehicleSettlements(CatalogStage $catalog, Carbon $from, Carbon $to): array
    {
        $queue = [];

        // One vehicle at a time: a whole fleet's daily ledger does not fit in default memory on long periods.
        foreach ($catalog->allVehicles()->pluck('plate', 'id') as $vehicleId => $plate) {
            $entries = LedgerEntry::query()->toBase()
                ->where('vehicle_id', $vehicleId)
                ->whereNull('reversal_of')
                ->groupBy('party_id', 'occurred_on')
                ->orderBy('occurred_on')
                ->get(['party_id', 'occurred_on', DB::raw('SUM(credit) as credit'), DB::raw('SUM(debit) as debit')]);

            if ($entries->isEmpty()) {
                continue;
            }

            $balances = [];
            $cursor = 0;
            $payWeekday = fake()->randomElement([Carbon::MONDAY, Carbon::FRIDAY, Carbon::SATURDAY]);

            foreach ($this->weekdays($from->copy()->addWeek(), $to, $payWeekday) as $payday) {
                $cursor = $this->accumulate($entries, $cursor, $payday, $balances, fn ($row) => $row->party_id);
                $ownerId = $catalog->ownerOn($vehicleId, $payday);

                if (! $ownerId) {
                    continue;
                }

                if (fake()->boolean(5)) {
                    $amount = $this->roundDown(fake()->numberBetween(300, 2_500));
                    $queue[] = [
                        'kind' => 'vehicle_receivable',
                        'plate' => $plate,
                        'amount' => $amount,
                        'occurred_on' => $payday->toDateString(),
                        'payment_method' => PaymentMethod::Offset->value,
                        'description' => fake()->randomElement(['Sefer farkı', 'Kesinti iadesi', 'Bekleme ücreti']),
                    ];
                    $balances[$ownerId] = ($balances[$ownerId] ?? 0) + $amount;
                }

                $open = $balances[$ownerId] ?? 0;

                if ($open < 1_500) {
                    continue;
                }

                $settled = $this->roundDown($open * fake()->randomFloat(2, 0.7, 0.9));
                $total = $settled;
                $advanceDay = $payday->copy()->subDays(3);
                $advance = fake()->boolean(25) ? $this->roundDown($total * fake()->randomFloat(2, 0.2, 0.35)) : 0;

                if ($advance >= 500 && $catalog->ownerOn($vehicleId, $advanceDay) === $ownerId) {
                    $queue[] = [
                        'kind' => 'vehicle_payment',
                        'plate' => $plate,
                        'amount' => $advance,
                        'occurred_on' => $advanceDay->toDateString(),
                        'payment_method' => PaymentMethod::Cash->value,
                        'description' => 'Avans',
                    ];
                    $total -= $advance;
                }

                $queue[] = [
                    'kind' => 'vehicle_payment',
                    'plate' => $plate,
                    'amount' => $total,
                    'occurred_on' => $payday->toDateString(),
                    'payment_method' => fake()->randomElement(self::PLATE_METHODS)->value,
                    'description' => fake()->randomElement(['Haftalık hakediş', 'Sefer mahsubu', 'Plaka ödemesi']),
                ];
                $balances[$ownerId] = $open - $settled;
            }
        }

        return $this->sortByDate($queue);
    }

    /**
     * Dump fees accrue per haul; operators are paid on the 1st and 16th from that dump's own balance.
     *
     * @return list<array<string, mixed>>
     */
    private function queueDumpSettlements(CatalogStage $catalog, Carbon $from, Carbon $to): array
    {
        $queue = [];
        $rows = Haul::query()->toBase()
            ->selectRaw('dump_site_id, CAST(hauled_at AS DATE) as day, SUM(dump_price) as total')
            ->groupByRaw('dump_site_id, CAST(hauled_at AS DATE)')
            ->orderBy('day')
            ->get()
            ->groupBy('dump_site_id');

        foreach ($catalog->allDumpSites() as $dump) {
            $entries = ($rows[$dump->id] ?? collect())->values();
            $open = ['dump' => 0.0];
            $cursor = 0;

            foreach ($this->biMonthlyDays($from, $to) as $payday) {
                $cursor = $this->accumulate($entries, $cursor, $payday, $open, fn () => 'dump', 'day', 'total', null);

                if ($open['dump'] < 5_000) {
                    continue;
                }

                $amount = $this->roundDown($open['dump'] * fake()->randomFloat(2, 0.75, 0.95));
                $parts = $amount > 150_000 && fake()->boolean(35) ? $this->splitAmount($amount, 2, 2) : [$amount];

                foreach ($parts as $i => $part) {
                    $cheque = $i === 0 && fake()->boolean(50);
                    $queue[] = [
                        'kind' => 'dump_payment',
                        'dump_site_id' => $dump->id,
                        'amount' => $part,
                        'occurred_on' => $payday->toDateString(),
                        'payment_method' => $cheque ? PaymentMethod::Cheque->value : PaymentMethod::BankTransfer->value,
                        'cheque_no' => $cheque ? 'CK'.str_pad((string) (++$this->doc), 6, '0', STR_PAD_LEFT) : null,
                        'description' => 'Döküm bedeli ödemesi',
                    ];
                }

                $open['dump'] -= $amount;
            }
        }

        return $this->sortByDate($queue);
    }

    /**
     * Fuel firms invoice each station / site twice a month from the receipts; invoices are paid
     * 10–35 days later. Unpaid invoices near the end of the period stay open.
     *
     * @return list<array<string, mixed>>
     */
    private function queueFuelSettlements(CatalogStage $catalog, Carbon $from, Carbon $to): array
    {
        $queue = [];
        $stations = $catalog->allStations()->keyBy('id');
        $periods = $this->halfMonthPeriods($from, $to);

        $rows = FuelReceipt::query()->toBase()
            ->selectRaw('fuel_station_id, fuel_party_id, site_id, issued_on, SUM(quantity) as liters, SUM(quantity * unit_price) as total')
            ->groupBy('fuel_station_id', 'fuel_party_id', 'site_id', 'issued_on')
            ->get();

        $buckets = [];

        foreach ($rows as $row) {
            foreach ($periods as $index => [$start, $end]) {
                if ($row->issued_on >= $start->toDateString() && $row->issued_on <= $end->toDateString()) {
                    $key = $index.'|'.$row->fuel_station_id.'|'.$row->fuel_party_id.'|'.$row->site_id;
                    $buckets[$key] ??= ['period' => $index, 'station' => $row->fuel_station_id, 'party' => $row->fuel_party_id, 'site' => $row->site_id, 'liters' => 0.0, 'total' => 0.0];
                    $buckets[$key]['liters'] += (float) $row->liters;
                    $buckets[$key]['total'] += (float) $row->total;

                    break;
                }
            }
        }

        foreach ($buckets as $bucket) {
            $station = $stations->get($bucket['station']);
            $invoiceOn = $periods[$bucket['period']][1]->copy()->addDays(fake()->numberBetween(1, 3));

            if (! $station || $bucket['total'] < 1_000 || $invoiceOn->gt($to)) {
                continue;
            }

            $invoice = round($bucket['total'], 2);
            $queue[] = [
                'kind' => 'fuel',
                'direction' => 'credit',
                'party_id' => $bucket['party'],
                'site_id' => $bucket['site'],
                'fuel_station_id' => $station->id,
                'amount' => $invoice,
                'liters' => round($bucket['liters'], 2),
                'unit_price' => $station->unit_price,
                'occurred_on' => $invoiceOn->toDateString(),
                'document_no' => 'AK-'.str_pad((string) (++$this->doc), 6, '0', STR_PAD_LEFT),
                'description' => 'Akaryakıt faturası (15 günlük fiş toplamı)',
            ];

            $paidOn = $invoiceOn->copy()->addDays(fake()->numberBetween(10, 25));
            $toPay = $this->roundDown($invoice * fake()->randomFloat(2, 0.85, 1.0));

            foreach ($this->splitAmount($toPay, 1, 2) as $amount) {
                if ($paidOn->gt($to)) {
                    break;
                }

                $queue[] = [
                    'kind' => 'fuel',
                    'direction' => 'debit',
                    'party_id' => $bucket['party'],
                    'site_id' => $bucket['site'],
                    'fuel_station_id' => $station->id,
                    'amount' => $amount,
                    'occurred_on' => $paidOn->toDateString(),
                    'payment_method' => fake()->boolean(80) ? PaymentMethod::BankTransfer->value : PaymentMethod::Cheque->value,
                    'document_no' => 'AO-'.str_pad((string) (++$this->doc), 6, '0', STR_PAD_LEFT),
                    'description' => 'Akaryakıt ödemesi',
                ];
                $paidOn->addDays(fake()->numberBetween(7, 12));
            }
        }

        return $this->sortByDate($queue);
    }

    /**
     * Month-end hakediş per site from its hauls and machine hours (plus margin), billed to the
     * site's customer; collections arrive 15–40 days later in 1–3 parts.
     *
     * @return list<array<string, mixed>>
     */
    private function queueCustomerBilling(CatalogStage $catalog, Carbon $from, Carbon $to): array
    {
        $queue = [];
        $revenue = [];

        $hauls = Haul::query()->toBase()
            ->selectRaw('site_id, CAST(hauled_at AS DATE) as day, SUM(price) as total')
            ->groupByRaw('site_id, CAST(hauled_at AS DATE)')
            ->get();
        $works = MachineWork::query()->toBase()
            ->selectRaw('site_id, worked_on as day, SUM(hours * hourly_rate) as total')
            ->groupBy('site_id', 'worked_on')
            ->get();

        foreach ($hauls->concat($works) as $row) {
            $month = substr((string) $row->day, 0, 7);
            $revenue[$row->site_id][$month] = ($revenue[$row->site_id][$month] ?? 0) + (float) $row->total;
        }

        foreach ($catalog->allSites() as $site) {
            $customerId = $catalog->customerOf($site->id);

            if (! $customerId) {
                continue;
            }

            foreach ($this->closedMonthEnds($from, $to) as $monthEnd) {
                $base = $revenue[$site->id][$monthEnd->format('Y-m')] ?? 0;

                if ($base < 10_000) {
                    continue;
                }

                $amount = $this->roundDown($base * fake()->randomFloat(2, 1.18, 1.32));
                $queue[] = [
                    'kind' => 'customer',
                    'direction' => 'debit',
                    'party_id' => $customerId,
                    'site_id' => $site->id,
                    'amount' => $amount,
                    'occurred_on' => $monthEnd->toDateString(),
                    'document_no' => 'HK-'.str_pad((string) (++$this->doc), 6, '0', STR_PAD_LEFT),
                    'description' => $monthEnd->translatedFormat('F Y').' hakedişi',
                ];

                $collectOn = $monthEnd->copy()->addDays(fake()->numberBetween(15, 40));
                $collect = $this->roundDown($amount * fake()->randomFloat(2, 0.75, 1.0));

                foreach ($this->splitAmount($collect, 1, 3) as $part) {
                    if ($collectOn->gt($to)) {
                        break;
                    }

                    $queue[] = [
                        'kind' => 'customer',
                        'direction' => 'credit',
                        'party_id' => $customerId,
                        'site_id' => $site->id,
                        'amount' => $part,
                        'occurred_on' => $collectOn->toDateString(),
                        'payment_method' => fake()->randomElement([PaymentMethod::BankTransfer, PaymentMethod::BankTransfer, PaymentMethod::Cheque, PaymentMethod::PromissoryNote])->value,
                        'description' => 'Hakediş tahsilatı',
                    ];
                    $collectOn->addDays(fake()->numberBetween(7, 15));
                }
            }
        }

        return $this->sortByDate($queue);
    }

    /**
     * Material / spare-part invoices, each paid 7–30 days later (70–100%).
     *
     * @param  Collection<int, CashCategory>  $categories
     * @return list<array<string, mixed>>
     */
    private function queueSuppliers(CatalogStage $catalog, Carbon $from, Carbon $to, Collection $categories): array
    {
        $queue = [];
        $sites = $catalog->allSites();
        $days = $this->days($from, $to);

        foreach ($catalog->partiesFor(PartyRole::Supplier) as $party) {
            $invoiceCount = fake()->numberBetween(2, 3) * OperationsStage::MONTHS;

            for ($n = 0; $n < $invoiceCount; $n++) {
                $invoiceOn = fake()->randomElement($days);
                $siteId = $sites->random()->id;
                $amount = $this->roundDown(fake()->numberBetween(5_000, 60_000));
                $queue[] = [
                    'kind' => 'supplier',
                    'direction' => 'credit',
                    'party_id' => $party->id,
                    'site_id' => $siteId,
                    'amount' => $amount,
                    'occurred_on' => $invoiceOn->toDateString(),
                    'document_no' => 'ST-'.str_pad((string) (++$this->doc), 6, '0', STR_PAD_LEFT),
                    'description' => fake()->randomElement(['Kum', 'Çakıl', 'Lastik', 'Yedek parça', 'Madeni yağ', 'Filtre']),
                ];

                $payOn = $invoiceOn->copy()->addDays(fake()->numberBetween(7, 30));

                if ($payOn->gt($to)) {
                    continue;
                }

                $queue[] = [
                    'kind' => 'supplier',
                    'direction' => 'debit',
                    'party_id' => $party->id,
                    'site_id' => $siteId,
                    'amount' => $this->roundDown($amount * fake()->randomFloat(2, 0.7, 1.0)),
                    'occurred_on' => $payOn->toDateString(),
                    'payment_method' => fake()->randomElement([PaymentMethod::BankTransfer, PaymentMethod::BankTransfer, PaymentMethod::Cash, PaymentMethod::CreditCard])->value,
                    'cash_category_id' => $categories->random()->id,
                    'document_no' => 'SO-'.str_pad((string) (++$this->doc), 6, '0', STR_PAD_LEFT),
                    'description' => 'Tedarikçi ödemesi',
                ];
            }
        }

        return $this->sortByDate($queue);
    }

    /**
     * Market trips by our trucks are billed to the market firm and collected the next month.
     *
     * @return list<array<string, mixed>>
     */
    private function queueMarket(CatalogStage $catalog, Carbon $from, Carbon $to): array
    {
        $queue = [];
        $vehicles = $catalog->haulVehicles();
        $sites = $catalog->allSites();
        $days = $this->days($from, $to);

        foreach ($catalog->partiesFor(PartyRole::Market) as $party) {
            $billed = [];

            for ($n = 0; $n < fake()->numberBetween(3, 5) * OperationsStage::MONTHS; $n++) {
                $day = fake()->randomElement($days);
                $vehicle = $vehicles->random();

                if (! $catalog->ownerOn($vehicle->id, $day)) {
                    continue;
                }

                $amount = $this->roundDown(fake()->numberBetween(1_500, 15_000));
                $queue[] = [
                    'kind' => 'market',
                    'direction' => 'debit',
                    'party_id' => $party->id,
                    'plate' => $vehicle->plate,
                    'site_id' => $sites->random()->id,
                    'amount' => $amount,
                    'occurred_on' => $day->toDateString(),
                    'description' => 'Piyasa seferi',
                ];
                $month = $day->format('Y-m');
                $billed[$month] = ($billed[$month] ?? 0) + $amount;
            }

            foreach ($billed as $month => $total) {
                $collectOn = Carbon::parse($month.'-01')->endOfMonth()->startOfDay()->addDays(fake()->numberBetween(5, 20));

                if ($collectOn->gt($to)) {
                    continue;
                }

                $queue[] = [
                    'kind' => 'market',
                    'direction' => 'credit',
                    'party_id' => $party->id,
                    'amount' => $this->roundDown($total * fake()->randomFloat(2, 0.7, 1.0)),
                    'occurred_on' => $collectOn->toDateString(),
                    'payment_method' => fake()->boolean(60) ? PaymentMethod::Cash->value : PaymentMethod::BankTransfer->value,
                    'description' => 'Piyasa tahsilatı',
                ];
            }
        }

        return $this->sortByDate($queue);
    }

    /**
     * Site running costs (çıkış = debit) and the odd site income (giriş = credit).
     *
     * @param  Collection<int, CashCategory>  $categories
     * @return list<array<string, mixed>>
     */
    private function queueSiteMovements(CatalogStage $catalog, Carbon $from, Carbon $to, Collection $categories): array
    {
        $queue = [];
        $days = $this->days($from, $to);
        $months = (int) ceil(count($days) / 30);

        foreach ($catalog->allSites() as $site) {
            for ($n = 0; $n < $months * 3; $n++) {
                $queue[] = [
                    'kind' => 'site',
                    'direction' => 'debit',
                    'site_id' => $site->id,
                    'amount' => $this->roundDown(fake()->numberBetween(1_500, 25_000)),
                    'occurred_on' => fake()->randomElement($days)->toDateString(),
                    'payment_method' => fake()->boolean(70) ? PaymentMethod::Cash->value : PaymentMethod::BankTransfer->value,
                    'description' => fake()->randomElement(['Yemek', 'İçme suyu', 'Elektrik', 'Şantiye malzemesi', 'Konteyner kirası']),
                ];
            }

            if (fake()->boolean(40)) {
                $queue[] = [
                    'kind' => 'site',
                    'direction' => 'credit',
                    'site_id' => $site->id,
                    'amount' => $this->roundDown(fake()->numberBetween(2_000, 20_000)),
                    'occurred_on' => fake()->randomElement($days)->toDateString(),
                    'payment_method' => PaymentMethod::Cash->value,
                    'description' => fake()->randomElement(['Hurda satışı', 'Ekipman kira geliri']),
                ];
            }
        }

        return $this->sortByDate($queue);
    }

    /**
     * Month-end payment of what plate payments do not cover: operator fees and the balance a
     * former owner still has on a transferred vehicle.
     *
     * @param  Collection<int, CashCategory>  $categories
     * @return list<array<string, mixed>>
     */
    private function queueOwnerResiduals(CatalogStage $catalog, Carbon $from, Carbon $to, Collection $categories): array
    {
        $queue = [];
        $partyIds = $catalog->partiesFor(PartyRole::VehicleOwner)
            ->concat($catalog->partiesFor(PartyRole::MachineOperator))
            ->pluck('id')
            ->unique()
            ->values();

        foreach ($partyIds as $partyId) {
            $entries = LedgerEntry::query()->toBase()
                ->where('party_id', $partyId)
                ->whereNull('reversal_of')
                ->groupBy('vehicle_id', 'occurred_on')
                ->orderBy('occurred_on')
                ->get(['vehicle_id', 'occurred_on', DB::raw('SUM(credit) as credit'), DB::raw('SUM(debit) as debit')]);

            if ($entries->isEmpty()) {
                continue;
            }

            $balances = [];
            $cursor = 0;

            foreach ($this->closedMonthEnds($from, $to) as $monthEnd) {
                $payday = $monthEnd->copy()->addDay();
                $cursor = $this->accumulate($entries, $cursor, $payday, $balances, fn ($row) => (string) $row->vehicle_id);

                // Lines of a vehicle the party still owns are settled by the weekly plate payment.
                $open = collect($balances)
                    ->filter(fn (float $balance, string $vehicleId) => $vehicleId === '' || $catalog->ownerOn($vehicleId, $payday) !== $partyId)
                    ->sum();

                if ($open < 3_000) {
                    continue;
                }

                $amount = $this->roundDown($open * fake()->randomFloat(2, 0.6, 0.9));
                $queue[] = [
                    'kind' => 'owner_payment',
                    'party_id' => $partyId,
                    'amount' => $amount,
                    'occurred_on' => $payday->toDateString(),
                    'payment_method' => fake()->boolean(70) ? PaymentMethod::BankTransfer->value : PaymentMethod::Cash->value,
                    'cash_category_id' => $categories->random()->id,
                    'description' => 'Operatörlük / devir sonrası mahsup',
                ];
                $balances[''] = ($balances[''] ?? 0) - $amount;
            }
        }

        return $this->sortByDate($queue);
    }

    /**
     * Daily office spending plus bank withdrawals that keep the drawer above zero, given every
     * cash payment and collection posted so far (cash_entries: debit = giriş, credit = çıkış).
     *
     * @param  Collection<int, CashCategory>  $categories
     * @param  Collection<int, Bank>  $banks
     * @return list<array<string, mixed>>
     */
    private function queueCashDrawer(Carbon $from, Carbon $to, Collection $categories, Collection $banks): array
    {
        $net = [];

        LedgerEntry::query()->toBase()
            ->where('payment_method', PaymentMethod::Cash->value)
            ->groupBy('occurred_on')
            ->get(['occurred_on', DB::raw('SUM(credit) - SUM(debit) as net')])
            ->each(function ($row) use (&$net): void {
                $net[(string) $row->occurred_on] = ($net[(string) $row->occurred_on] ?? 0) + (float) $row->net;
            });

        SiteTransaction::query()->toBase()
            ->where('payment_method', PaymentMethod::Cash->value)
            ->groupBy('occurred_on')
            ->get(['occurred_on', DB::raw('SUM(credit) - SUM(debit) as net')])
            ->each(function ($row) use (&$net): void {
                $net[(string) $row->occurred_on] = ($net[(string) $row->occurred_on] ?? 0) + (float) $row->net;
            });

        $queue = [];
        $balance = 0.0;
        $entry = fn (string $direction, float $amount, Carbon $day, string $description, bool $withBank = false): array => [
            'kind' => 'cash',
            'direction' => $direction,
            'amount' => $amount,
            'occurred_on' => $day->toDateString(),
            'cash_category_id' => $categories->random()->id,
            'bank_id' => $withBank ? $banks->random()->id : null,
            'description' => $description,
        ];

        foreach ($this->days($from, $to) as $day) {
            $date = $day->toDateString();
            $expected = $net[$date] ?? 0;

            if (! $day->isSunday()) {
                for ($n = 0; $n < fake()->numberBetween(1, 3); $n++) {
                    $amount = $this->roundDown(fake()->numberBetween(300, 6_000));
                    $queue[] = $entry('credit', $amount, $day, fake()->randomElement(['Ofis gideri', 'Yemek', 'Kırtasiye', 'Binek araç yakıtı', 'Kargo']));
                    $expected -= $amount;
                }
            }

            if ($balance + $expected < 50_000) {
                $amount = $this->roundDown(fake()->numberBetween(150_000, 250_000) - ($balance + $expected));
                $queue[] = $entry('debit', $amount, $day, 'Bankadan çekilen', true);
                $expected += $amount;
            } elseif ($balance + $expected > 400_000) {
                $amount = $this->roundDown($balance + $expected - fake()->numberBetween(150_000, 250_000));
                $queue[] = $entry('credit', $amount, $day, 'Bankaya yatan', true);
                $expected -= $amount;
            }

            $balance += $expected;
        }

        return $queue;
    }

    /**
     * Adds rows dated before $until to $balances; returns the new cursor.
     *
     * @param  Collection<int, object>  $entries
     * @param  array<string, float>  $balances
     * @param  callable(object): string  $keyOf
     */
    private function accumulate(
        Collection $entries,
        int $cursor,
        Carbon $until,
        array &$balances,
        callable $keyOf,
        string $dateColumn = 'occurred_on',
        string $creditColumn = 'credit',
        ?string $debitColumn = 'debit',
    ): int {
        $date = $until->toDateString();

        while ($cursor < $entries->count() && (string) $entries[$cursor]->{$dateColumn} < $date) {
            $row = $entries[$cursor++];
            $key = $keyOf($row);
            $balances[$key] = ($balances[$key] ?? 0)
                + (float) $row->{$creditColumn}
                - ($debitColumn ? (float) $row->{$debitColumn} : 0);
        }

        return $cursor;
    }

    /**
     * @param  list<array<string, mixed>>  $queue
     * @return list<array<string, mixed>>
     */
    private function sortByDate(array $queue): array
    {
        usort($queue, fn (array $a, array $b) => strcmp($a['occurred_on'], $b['occurred_on']));

        return $queue;
    }

    private function roundDown(float $amount): float
    {
        return floor($amount / 50) * 50;
    }

    /**
     * @return list<float>
     */
    private function splitAmount(float $total, int $minParts, int $maxParts): array
    {
        $total = $this->roundDown($total);
        $parts = fake()->numberBetween($minParts, $maxParts);

        if ($total < 1_000 || $parts === 1) {
            return $total > 0 ? [$total] : [];
        }

        $weights = array_map(fn () => fake()->numberBetween(10, 40), range(1, $parts));
        $weightSum = array_sum($weights);
        $chunks = [];

        foreach (array_slice($weights, 0, -1) as $weight) {
            $chunks[] = $this->roundDown($total * $weight / $weightSum);
        }

        $chunks[] = $total - array_sum($chunks);

        return array_values(array_filter($chunks, fn (float $amount) => $amount >= 50));
    }

    /**
     * @return list<Carbon>
     */
    private function days(Carbon $from, Carbon $to): array
    {
        $days = [];
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return list<Carbon>
     */
    private function weekdays(Carbon $from, Carbon $to, int $weekday): array
    {
        return array_values(array_filter($this->days($from, $to), fn (Carbon $day) => $day->dayOfWeek === $weekday));
    }

    /**
     * 1st and 16th of each month after the start.
     *
     * @return list<Carbon>
     */
    private function biMonthlyDays(Carbon $from, Carbon $to): array
    {
        return array_values(array_filter(
            $this->days($from->copy()->addDay(), $to),
            fn (Carbon $day) => in_array($day->day, [1, 16], true),
        ));
    }

    /**
     * Closed half-month invoice periods: [1–15] and [16–end of month].
     *
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function halfMonthPeriods(Carbon $from, Carbon $to): array
    {
        $periods = [];
        $cursor = $from->copy()->startOfMonth();

        while ($cursor->lte($to)) {
            $mid = $cursor->copy()->day(15);
            $end = $cursor->copy()->endOfMonth()->startOfDay();

            foreach ([[$cursor->copy(), $mid], [$mid->copy()->addDay(), $end]] as [$start, $finish]) {
                if ($finish->lt($to)) {
                    $periods[] = [$start, $finish];
                }
            }

            $cursor->addMonth();
        }

        return $periods;
    }

    /**
     * @return list<Carbon>
     */
    private function closedMonthEnds(Carbon $from, Carbon $to): array
    {
        $ends = [];
        $cursor = $from->copy()->startOfMonth();

        while ($cursor->lte($to)) {
            $end = $cursor->copy()->endOfMonth()->startOfDay();

            if ($end->lt($to)) {
                $ends[] = $end;
            }

            $cursor->addMonth();
        }

        return $ends;
    }
}
