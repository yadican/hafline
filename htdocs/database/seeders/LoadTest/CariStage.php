<?php

namespace Database\Seeders\LoadTest;

use App\Enums\AccountType;
use App\Enums\CashDirection;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Enums\SiteBilling;
use App\Models\FuelReceipt;
use App\Models\FuelTankFill;
use App\Models\Haul;
use App\Models\LedgerEntry;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\Shipment;
use App\Models\SiteTransaction;
use App\Models\User;
use App\Models\Vehicle;
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

    public function run(CatalogStage $catalog, Carbon $from, Carbon $to, ?InvoiceStage $invoices = null): void
    {
        $this->progress->stage('Cari hareketleri (fiş bakiyesine göre, tarih sırasıyla)');

        $userId = User::query()->value('id');
        $done = 0;

        // Order matters: açılış dönem başında; nakliye ödemeleri plaka hakedişinden önce;
        // fatura/stok (45) kasadan önce; cari virman bakiyelerden sonra; kasa en sonda.
        $phases = [
            'açılış bakiyeleri' => fn () => $this->queueOpenings($catalog, $from),
            'nakliye tahsilat + ödeme' => fn () => $this->queueShipmentSettlements($catalog, $from, $to),
            'plaka haftalık hakediş' => fn () => $this->queueVehicleSettlements($catalog, $from, $to),
            'döküm 15 günlük ödeme' => fn () => $this->queueDumpSettlements($catalog, $from, $to),
            'akaryakıt açık bakiye ödeme' => fn () => $this->queueFuelSettlements($catalog, $from, $to),
            'şantiye aylık hakediş + tahsilat' => fn () => $this->queueSiteBilling($catalog, $from, $to),
            'piyasa sefer + tahsilat' => fn () => $this->queueMarket($catalog, $from, $to),
            'şantiye gelir / gider' => fn () => $this->queueSiteMovements($catalog, $from, $to),
            'sahip / operatör ay sonu' => fn () => $this->queueOwnerResiduals($catalog, $from, $to),
            'cari virman' => fn () => $this->queuePartyTransfers($catalog, $from, $to),
        ];

        foreach ($phases as $label => $build) {
            $queue = $build();
            $this->progress->note($label.': '.number_format(count($queue)));
            $done = $this->flush($queue, $userId, $done, $catalog);
        }

        // Real invoices replace the old supplier "fatura" cari rows (K5); cash drawer still last.
        $invoices?->run($catalog, $from, $to);

        $cash = $this->queueCashDrawer($catalog, $from, $to);
        $this->progress->note('kasa: '.number_format(count($cash)));
        $done = $this->flush($cash, $userId, $done, $catalog);

        $this->progress->done(number_format($done).' cari hareket');
    }

    /**
     * @param  list<array<string, mixed>>  $queue
     */
    private function flush(array $queue, ?string $userId, int $done, CatalogStage $catalog): int
    {
        $total = count($queue);

        foreach ($queue as $index => $payload) {
            $this->entries->create(
                $catalog->withInstrumentDetails(
                    $catalog->withPaymentAccount($catalog->withCashCategory($payload)),
                ),
                $userId,
            );
            $this->progress->batch($index + 1, $total, 'cari');
        }

        return $done + $total;
    }

    /**
     * Transport trips: collect from the customer (borç), pay the carrier on the plate (alacak) and
     * the referrer (komisyoncu). Sized from each party's own open trip balance so a little borç /
     * alacak stays open. Carrier payments run before weekly plate settlements.
     *
     * @return list<array<string, mixed>>
     */
    private function queueShipmentSettlements(CatalogStage $catalog, Carbon $from, Carbon $to): array
    {
        $queue = [];
        $plates = Vehicle::query()->toBase()->pluck('plate', 'id');

        $customerRows = Shipment::query()->toBase()
            ->selectRaw('customer_party_id as party_id, substr(cast(shipped_on as text), 1, 10) as day, SUM(customer_amount) as total')
            ->whereNull('deleted_at')
            ->groupByRaw('customer_party_id, substr(cast(shipped_on as text), 1, 10)')
            ->orderBy('day')
            ->get()
            ->groupBy('party_id');

        foreach ($catalog->partiesFor(PartyRole::Customer) as $party) {
            $entries = ($customerRows[$party->id] ?? collect())->values();
            $open = ['customer' => 0.0];
            $cursor = 0;

            foreach ($this->weekdays($from->copy()->addWeek(), $to, Carbon::THURSDAY) as $payday) {
                $cursor = $this->accumulate($entries, $cursor, $payday, $open, fn () => 'customer', 'day', 'total', null);

                if ($open['customer'] < 3_000) {
                    continue;
                }

                $amount = $this->roundDown($open['customer'] * fake()->randomFloat(2, 0.65, 0.95));

                foreach ($this->splitAmount($amount, 1, 2) as $part) {
                    $queue[] = [
                        'kind' => 'customer',
                        'direction' => 'credit',
                        'party_id' => $party->id,
                        'amount' => $part,
                        'occurred_on' => $payday->toDateString(),
                        'payment_method' => fake()->randomElement([
                            PaymentMethod::BankTransfer,
                            PaymentMethod::BankTransfer,
                            PaymentMethod::Cash,
                            PaymentMethod::Cheque,
                            PaymentMethod::PromissoryNote,
                        ])->value,
                        'description' => 'Nakliye tahsilatı',
                    ];
                }

                $open['customer'] -= $amount;
            }
        }

        $carrierRows = Shipment::query()->toBase()
            ->selectRaw(
                'vehicle_id, party_id, substr(cast(shipped_on as text), 1, 10) as day, '
                .'SUM(carrier_net_amount + driver_amount) as total'
            )
            ->whereNull('deleted_at')
            ->groupByRaw('vehicle_id, party_id, substr(cast(shipped_on as text), 1, 10)')
            ->orderBy('day')
            ->get()
            ->groupBy(fn ($row) => $row->vehicle_id.'|'.$row->party_id);

        foreach ($carrierRows as $key => $group) {
            [$vehicleId, $partyId] = explode('|', (string) $key, 2);
            $plate = $plates[$vehicleId] ?? null;

            if (! $plate) {
                continue;
            }

            $entries = $group->values();
            $open = ['carrier' => 0.0];
            $cursor = 0;
            $payWeekday = fake()->randomElement([Carbon::TUESDAY, Carbon::FRIDAY]);

            foreach ($this->weekdays($from->copy()->addWeek(), $to, $payWeekday) as $payday) {
                $cursor = $this->accumulate($entries, $cursor, $payday, $open, fn () => 'carrier', 'day', 'total', null);

                if ($catalog->ownerOn($vehicleId, $payday) !== $partyId) {
                    continue;
                }

                if ($open['carrier'] < 2_000) {
                    continue;
                }

                $amount = $this->roundDown($open['carrier'] * fake()->randomFloat(2, 0.7, 0.95));
                $queue[] = [
                    'kind' => 'vehicle_payment',
                    'plate' => $plate,
                    'amount' => $amount,
                    'occurred_on' => $payday->toDateString(),
                    'payment_method' => fake()->randomElement(self::PLATE_METHODS)->value,
                    'description' => 'Nakliyeci ödemesi',
                ];
                $open['carrier'] -= $amount;
            }
        }

        $commissionRows = Shipment::query()->toBase()
            ->selectRaw('commission_party_id as party_id, substr(cast(shipped_on as text), 1, 10) as day, SUM(commission_amount) as total')
            ->whereNull('deleted_at')
            ->whereNotNull('commission_party_id')
            ->where('commission_amount', '>', 0)
            ->groupByRaw('commission_party_id, substr(cast(shipped_on as text), 1, 10)')
            ->orderBy('day')
            ->get()
            ->groupBy('party_id');

        foreach ($catalog->partiesFor(PartyRole::Commission) as $party) {
            $entries = ($commissionRows[$party->id] ?? collect())->values();
            $open = ['commission' => 0.0];
            $cursor = 0;

            foreach ($this->weekdays($from->copy()->addWeek(), $to, Carbon::WEDNESDAY) as $payday) {
                $cursor = $this->accumulate($entries, $cursor, $payday, $open, fn () => 'commission', 'day', 'total', null);

                if ($open['commission'] < 500) {
                    continue;
                }

                $amount = $this->roundDown($open['commission'] * fake()->randomFloat(2, 0.7, 0.95));
                $queue[] = [
                    'kind' => 'owner_payment',
                    'direction' => 'debit',
                    'party_id' => $party->id,
                    'amount' => $amount,
                    'occurred_on' => $payday->toDateString(),
                    'payment_method' => fake()->randomElement([
                        PaymentMethod::BankTransfer,
                        PaymentMethod::BankTransfer,
                        PaymentMethod::Cash,
                        PaymentMethod::Cheque,
                    ])->value,
                    'description' => 'Komisyon ödemesi',
                ];
                $open['commission'] -= $amount;
            }
        }

        return $this->sortByDate($queue);
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
     * Station receipts credit the firm at purchase_price; tank fills credit the supplier when
     * the pump is topped up. Wednesdays pay down that combined open alacak (payments only).
     *
     * @return list<array<string, mixed>>
     */
    private function queueFuelSettlements(CatalogStage $catalog, Carbon $from, Carbon $to): array
    {
        $queue = [];
        $receiptRows = FuelReceipt::query()->toBase()
            ->selectRaw('fuel_party_id as party_id, issued_on as day, SUM(purchase_amount) as total')
            ->whereNotNull('fuel_party_id')
            ->groupByRaw('fuel_party_id, issued_on')
            ->get();
        $fillRows = FuelTankFill::query()->toBase()
            ->selectRaw('party_id, filled_on as day, SUM(amount) as total')
            ->whereNull('deleted_at')
            ->groupByRaw('party_id, filled_on')
            ->get();
        $rows = $receiptRows->concat($fillRows)
            ->groupBy('party_id')
            ->map(fn (Collection $group) => $group->sortBy('day')->values());

        foreach ($catalog->partiesFor(PartyRole::Fuel) as $party) {
            $entries = ($rows[$party->id] ?? collect())->values();
            $open = ['fuel' => 0.0];
            $cursor = 0;

            foreach ($this->weekdays($from->copy()->addWeek(), $to, Carbon::WEDNESDAY) as $payday) {
                $cursor = $this->accumulate($entries, $cursor, $payday, $open, fn () => 'fuel', 'day', 'total', null);

                if ($open['fuel'] < 5_000) {
                    continue;
                }

                $amount = $this->roundDown($open['fuel'] * fake()->randomFloat(2, 0.6, 0.9));
                $queue[] = [
                    'kind' => 'fuel',
                    'direction' => 'debit',
                    'party_id' => $party->id,
                    'amount' => $amount,
                    'occurred_on' => $payday->toDateString(),
                    'payment_method' => fake()->randomElement([
                        PaymentMethod::BankTransfer,
                        PaymentMethod::BankTransfer,
                        PaymentMethod::Cheque,
                    ])->value,
                    'document_no' => 'AO-'.str_pad((string) (++$this->doc), 6, '0', STR_PAD_LEFT),
                    'description' => 'Akaryakıt ödemesi',
                ];
                $open['fuel'] -= $amount;
            }
        }

        return $this->sortByDate($queue);
    }

    /**
     * Month-end progress bill (hakediş) on Progress sites from haul site prices and machine hours
     * (plus margin), debited to the site's own cari. Per-trip sites are already debited by hauls.
     * Collections for both arrive 15–40 days later in 1–3 parts as şantiye tahsilatı.
     *
     * @return list<array<string, mixed>>
     */
    private function queueSiteBilling(CatalogStage $catalog, Carbon $from, Carbon $to): array
    {
        $queue = [];
        $revenue = [];

        // substr(text) so SQLite tests and Postgres agree (CAST AS DATE is year-only on SQLite).
        $hauls = Haul::query()->toBase()
            ->selectRaw('site_id, substr(cast(hauled_at as text), 1, 10) as day, SUM(site_price) as total')
            ->groupByRaw('site_id, substr(cast(hauled_at as text), 1, 10)')
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
            if (! $site->party_id) {
                continue;
            }

            foreach ($this->closedMonthEnds($from, $to) as $monthEnd) {
                $base = $revenue[$site->id][$monthEnd->format('Y-m')] ?? 0;

                if ($base < 10_000) {
                    continue;
                }

                if ($site->billing_mode === SiteBilling::Progress) {
                    $amount = $this->roundDown($base * fake()->randomFloat(2, 1.18, 1.32));
                    $queue[] = [
                        'kind' => 'site_bill',
                        'site_id' => $site->id,
                        'amount' => $amount,
                        'occurred_on' => $monthEnd->toDateString(),
                        'description' => $monthEnd->translatedFormat('F Y').' hakedişi',
                    ];
                    $collect = $this->roundDown($amount * fake()->randomFloat(2, 0.75, 1.0));
                } else {
                    // Per-trip hauls already debit the site cari; collect a share of the month.
                    $collect = $this->roundDown($base * fake()->randomFloat(2, 0.75, 1.0));
                }

                $collectOn = $monthEnd->copy()->addDays(fake()->numberBetween(15, 40));

                foreach ($this->splitAmount($collect, 1, 3) as $part) {
                    if ($collectOn->gt($to)) {
                        break;
                    }

                    $queue[] = [
                        'kind' => 'site',
                        'direction' => 'credit',
                        'site_id' => $site->id,
                        'amount' => $part,
                        'occurred_on' => $collectOn->toDateString(),
                        'payment_method' => fake()->randomElement([
                            PaymentMethod::BankTransfer,
                            PaymentMethod::BankTransfer,
                            PaymentMethod::Cheque,
                            PaymentMethod::PromissoryNote,
                        ])->value,
                        'description' => 'Şantiye tahsilatı',
                    ];
                    $collectOn->addDays(fake()->numberBetween(7, 15));
                }
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
     * @return list<array<string, mixed>>
     */
    private function queueSiteMovements(CatalogStage $catalog, Carbon $from, Carbon $to): array
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
     * @return list<array<string, mixed>>
     */
    private function queueOwnerResiduals(CatalogStage $catalog, Carbon $from, Carbon $to): array
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
                    'cash_category_id' => $catalog->categoryFor(CashDirection::Expense)?->id,
                    'description' => 'Operatörlük / devir sonrası mahsup',
                ];
                $balances[''] = ($balances[''] ?? 0) - $amount;
            }
        }

        return $this->sortByDate($queue);
    }

    /**
     * Prior-period party balances as of the seed window start. One opening per party; cash/bank
     * untouched. Customers open borçlu (debit), payables (supplier / fuel / dump) open alacaklı
     * (credit); market and owners get a mix.
     *
     * @return list<array<string, mixed>>
     */
    private function queueOpenings(CatalogStage $catalog, Carbon $from): array
    {
        $on = $from->toDateString();
        $queue = [];
        $used = [];

        $add = function (Party $party, string $direction, float $amount, string $label) use (&$queue, &$used, $on): void {
            if (isset($used[$party->id]) || $amount < 50) {
                return;
            }

            $used[$party->id] = true;
            $queue[] = [
                'kind' => 'opening',
                'direction' => $direction,
                'party_id' => $party->id,
                'amount' => $amount,
                'occurred_on' => $on,
                'description' => 'Açılış bakiyesi · '.$label,
            ];
        };

        foreach ($catalog->partiesFor(PartyRole::Customer)->shuffle()->take(12) as $party) {
            $add($party, 'debit', $this->roundDown(fake()->numberBetween(8_000, 75_000)), 'alıcı');
        }

        foreach ($catalog->partiesFor(PartyRole::Supplier)->shuffle()->take(12) as $party) {
            $add($party, 'credit', $this->roundDown(fake()->numberBetween(10_000, 90_000)), 'satıcı');
        }

        foreach ($catalog->partiesFor(PartyRole::Fuel)->shuffle()->take(6) as $party) {
            $add($party, 'credit', $this->roundDown(fake()->numberBetween(15_000, 120_000)), 'akaryakıt');
        }

        foreach ($catalog->partiesFor(PartyRole::DumpOperator)->shuffle()->take(4) as $party) {
            $add($party, 'credit', $this->roundDown(fake()->numberBetween(5_000, 45_000)), 'döküm');
        }

        foreach ($catalog->partiesFor(PartyRole::Market)->shuffle()->take(8)->values() as $index => $party) {
            $add(
                $party,
                $index % 2 === 0 ? 'debit' : 'credit',
                $this->roundDown(fake()->numberBetween(5_000, 40_000)),
                'piyasa',
            );
        }

        foreach ($catalog->partiesFor(PartyRole::VehicleOwner)->shuffle()->take(15)->values() as $index => $party) {
            $add(
                $party,
                $index % 3 === 0 ? 'credit' : 'debit',
                $this->roundDown(fake()->numberBetween(3_000, 35_000)),
                'araç sahibi',
            );
        }

        return $queue;
    }

    /**
     * Offsets open borç against open alacak without touching cash: debit the party we owe,
     * credit the party that owes us (mahsup / cari virman).
     *
     * @return list<array<string, mixed>>
     */
    private function queuePartyTransfers(CatalogStage $catalog, Carbon $from, Carbon $to): array
    {
        $rows = LedgerEntry::query()->toBase()
            ->whereNotNull('party_id')
            ->groupBy('party_id')
            ->havingRaw('ABS(SUM(debit) - SUM(credit)) >= ?', [2_000])
            ->get(['party_id', DB::raw('SUM(debit) - SUM(credit) as balance')]);

        $debtors = $rows->filter(fn ($row) => (float) $row->balance >= 2_000)->shuffle()->take(12)->values();
        $creditors = $rows->filter(fn ($row) => (float) $row->balance <= -2_000)->shuffle()->take(12)->values();
        $span = max(21, (int) $from->diffInDays($to));
        $queue = [];
        $n = min($debtors->count(), $creditors->count());

        for ($i = 0; $i < $n; $i++) {
            $debtor = $debtors[$i];
            $creditor = $creditors[$i];

            if ($debtor->party_id === $creditor->party_id) {
                continue;
            }

            $amount = $this->roundDown(min(
                abs((float) $debtor->balance) * 0.4,
                abs((float) $creditor->balance) * 0.4,
                (float) fake()->numberBetween(2_000, 30_000),
            ));

            if ($amount < 500) {
                continue;
            }

            $queue[] = [
                'kind' => 'party_transfer',
                'party_id' => $creditor->party_id,
                'counter_party_id' => $debtor->party_id,
                'amount' => $amount,
                'occurred_on' => $from->copy()->addDays(fake()->numberBetween(21, $span))->toDateString(),
                'description' => fake()->randomElement(['Cari virman', 'Mahsup', 'Cari mahsuplaşma']),
            ];
        }

        return $this->sortByDate($queue);
    }

    /**
     * Daily office spending plus account transfers that keep the drawer funded: bank→cash when
     * low, cash→bank when high (replaces the old fake "Bankadan çekilen" cash rows).
     *
     * @return list<array<string, mixed>>
     */
    private function queueCashDrawer(CatalogStage $catalog, Carbon $from, Carbon $to): array
    {
        $net = [];

        $addNet = function ($row) use (&$net): void {
            $date = Carbon::parse((string) $row->occurred_on)->toDateString();
            $net[$date] = ($net[$date] ?? 0) + (float) $row->net;
        };

        LedgerEntry::query()->toBase()
            ->where('payment_method', PaymentMethod::Cash->value)
            ->groupBy('occurred_on')
            ->get(['occurred_on', DB::raw('SUM(credit) - SUM(debit) as net')])
            ->each($addNet);

        SiteTransaction::query()->toBase()
            ->where('payment_method', PaymentMethod::Cash->value)
            ->groupBy('occurred_on')
            ->get(['occurred_on', DB::raw('SUM(credit) - SUM(debit) as net')])
            ->each($addNet);

        $banks = $catalog->banksOf(AccountType::Bank);
        $cashBoxes = $catalog->banksOf(AccountType::Cash);
        $queue = [];
        $balance = 0.0;
        $expense = fn (float $amount, Carbon $day, string $description): array => [
            'kind' => 'cash',
            'direction' => 'credit',
            'amount' => $amount,
            'occurred_on' => $day->toDateString(),
            'cash_category_id' => $catalog->categoryFor(
                $catalog->categoryDirectionFor('cash', 'credit'),
            )?->id,
            'description' => $description,
        ];
        $transfer = fn (string $fromId, string $toId, float $amount, Carbon $day, string $description): array => [
            'kind' => 'account_transfer',
            'bank_id' => $fromId,
            'counter_bank_id' => $toId,
            'amount' => $amount,
            'occurred_on' => $day->toDateString(),
            'description' => $description,
        ];

        foreach ($this->days($from, $to) as $day) {
            $date = $day->toDateString();
            $expected = $net[$date] ?? 0;

            if (! $day->isSunday()) {
                for ($n = 0; $n < fake()->numberBetween(1, 3); $n++) {
                    $amount = $this->roundDown(fake()->numberBetween(300, 6_000));
                    $queue[] = $expense($amount, $day, fake()->randomElement(['Ofis gideri', 'Yemek', 'Kırtasiye', 'Binek araç yakıtı', 'Kargo']));
                    $expected -= $amount;
                }
            }

            if ($balance + $expected < 50_000) {
                $amount = $this->roundDown(fake()->numberBetween(150_000, 250_000) - ($balance + $expected));

                if ($amount > 0 && $banks->isNotEmpty() && $cashBoxes->isNotEmpty()) {
                    $queue[] = $transfer(
                        $banks->random()->id,
                        $cashBoxes->random()->id,
                        $amount,
                        $day,
                        'Bankadan kasaya çekim',
                    );
                    $expected += $amount;
                }
            } elseif ($balance + $expected > 400_000) {
                $amount = $this->roundDown($balance + $expected - fake()->numberBetween(150_000, 250_000));

                if ($amount > 0 && $banks->isNotEmpty() && $cashBoxes->isNotEmpty()) {
                    $queue[] = $transfer(
                        $cashBoxes->random()->id,
                        $banks->random()->id,
                        $amount,
                        $day,
                        'Kasadan bankaya yatırma',
                    );
                    $expected -= $amount;
                }
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
