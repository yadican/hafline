<?php

namespace Database\Seeders\LoadTest;

use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\CashCategory;
use App\Models\FuelReceipt;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CariEntryService;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Settlement-style cari movements derived from operational ledger balances.
 *
 * Plate/driver rule: after seeding, borç (debit) stays at or below alacak (credit)
 * within a small tolerance — payments settle most of the open receivable, they do
 * not push drivers deep into debt.
 */
final class CariStage
{
    /** Max borç / alacak after plate settlements (1.0 = equal). */
    private const MAX_DEBT_RATIO = 0.98;

    public function __construct(
        private Progress $progress,
        private CariEntryService $entries,
    ) {}

    public function run(CatalogStage $catalog, Carbon $from, Carbon $to): void
    {
        $this->progress->stage('Cari hareketleri (fiş bakiyesine göre)');
        $this->progress->note('Plaka/şoför: borç, alacağın altında kalacak şekilde mahsup');

        $userId = User::query()->value('id');
        $categories = CashCategory::query()->where('active', true)->get();
        $banks = Bank::query()->where('active', true)->get();
        $methods = PaymentMethod::values();
        $days = $this->days($from, $to);
        $doc = 0;
        $done = 0;

        // Phase order matters: later phases read ledger after earlier settlements post.
        $phases = [
            'plaka mahsup' => $this->queueVehicleSettlements($catalog, $days, $methods),
            'döküm mahsup' => $this->queueDumpSettlements($catalog, $days, $doc),
            'yakıt fatura/ödeme' => $this->queueFuelSettlements($catalog, $days, $doc),
        ];

        $planned = array_sum(array_map('count', $phases));
        // Owner residuals + light extras sized after first phases (estimate upper bound for progress).
        $planned += 2_500;

        $this->progress->note('Önce fiş bakiyeleri mahsup, sonra kalan cari');

        foreach ($phases as $label => $queue) {
            $this->progress->note($label.': '.number_format(count($queue)));
            $done = $this->flush($queue, $userId, $done, $planned);
        }

        $ownerQueue = $this->queueOwnerResiduals($catalog, $days, $methods, $categories);
        $this->progress->note('sahip/operatör kalan: '.number_format(count($ownerQueue)));
        $done = $this->flush($ownerQueue, $userId, $done, $planned);

        $extraQueue = $this->queueLightExtras($catalog, $days, $methods, $categories, $banks, $doc);
        $this->progress->note('ek cari: '.number_format(count($extraQueue)));
        $planned = max($planned, $done + count($extraQueue));
        $done = $this->flush($extraQueue, $userId, $done, $planned);

        $this->progress->done(number_format($done).' cari hareket');
    }

    /**
     * @param  list<array<string, mixed>>  $queue
     */
    private function flush(array $queue, ?string $userId, int $done, int $planned): int
    {
        foreach ($queue as $payload) {
            $this->entries->create($payload, $userId);
            $this->progress->batch(++$done, max($planned, $done), 'cari');
        }

        return $done;
    }

    /**
     * Settle haul/machine credits vs fuel debits per plate.
     *
     * @param  Collection<int, Carbon>  $days
     * @param  list<string>  $methods
     * @return list<array<string, mixed>>
     */
    private function queueVehicleSettlements(CatalogStage $catalog, Collection $days, array $methods): array
    {
        $vehicles = $catalog->allVehicles()->keyBy('id');
        $queue = [];

        $balances = LedgerEntry::query()
            ->whereNotNull('vehicle_id')
            ->whereNull('reversal_of')
            ->groupBy('vehicle_id')
            ->selectRaw('vehicle_id, COALESCE(SUM(credit), 0) as credit_total, COALESCE(SUM(debit), 0) as debit_total')
            ->get();

        foreach ($balances as $row) {
            /** @var Vehicle|null $vehicle */
            $vehicle = $vehicles->get($row->vehicle_id);

            if (! $vehicle) {
                continue;
            }

            $credit = round((float) $row->credit_total, 2);
            $debit = round((float) $row->debit_total, 2);

            if ($credit <= 0 && $debit <= 0) {
                continue;
            }

            // Target: keep final debit ≤ credit * MAX_DEBT_RATIO (slight open alacak).
            $maxDebit = round($credit * self::MAX_DEBT_RATIO, 2);

            if ($debit > $maxDebit) {
                // Fuel already outweighs earnings — top up alacak, do not add more borç.
                $gap = round($debit - ($credit * 0.85), 2);

                foreach ($this->splitAmount($gap, 2, 5) as $amount) {
                    $occurredOn = $this->dayWithOwner($catalog, $vehicle->id, $days);

                    if ($occurredOn === null) {
                        continue;
                    }

                    $queue[] = [
                        'kind' => 'vehicle_receivable',
                        'plate' => $vehicle->plate,
                        'amount' => $amount,
                        'occurred_on' => $occurredOn,
                        'payment_method' => PaymentMethod::Offset->value,
                        'description' => fake()->randomElement(['Sefer farkı', 'Kesinti iadesi', 'Plaka alacak düzeltmesi']),
                    ];
                    $credit = round($credit + $amount, 2);
                    $maxDebit = round($credit * self::MAX_DEBT_RATIO, 2);
                }
            }

            $room = round($maxDebit - $debit, 2);

            if ($room < 500) {
                continue;
            }

            // Settle most of the open alacak; leave 8–25% residual receivable.
            $residualRatio = fake()->randomFloat(2, 0.08, 0.25);
            $toPay = round($room * (1 - $residualRatio), 2);

            if ($toPay < 500) {
                continue;
            }

            foreach ($this->splitAmount($toPay, 4, 12) as $amount) {
                $occurredOn = $this->dayWithOwner($catalog, $vehicle->id, $days);

                if ($occurredOn === null) {
                    continue;
                }

                $queue[] = [
                    'kind' => 'vehicle_payment',
                    'plate' => $vehicle->plate,
                    'amount' => $amount,
                    'occurred_on' => $occurredOn,
                    'payment_method' => fake()->randomElement($methods),
                    'description' => fake()->randomElement(['Plaka ödemesi', 'Haftalık hakediş', 'Sefer mahsubu', 'Ara ödeme']),
                ];
            }
        }

        return $queue;
    }

    /**
     * Pay dump operators against dump-fee credits from haul ledger.
     *
     * @param  Collection<int, Carbon>  $days
     * @return list<array<string, mixed>>
     */
    private function queueDumpSettlements(CatalogStage $catalog, Collection $days, int &$doc): array
    {
        $queue = [];

        foreach ($catalog->allDumpSites() as $dump) {
            if (! $dump->party_id) {
                continue;
            }

            $credit = (float) LedgerEntry::query()
                ->where('party_id', $dump->party_id)
                ->whereNull('reversal_of')
                ->sum('credit');
            $debit = (float) LedgerEntry::query()
                ->where('party_id', $dump->party_id)
                ->whereNull('reversal_of')
                ->sum('debit');

            $open = round($credit - $debit, 2);

            if ($open < 1_000) {
                continue;
            }

            $toPay = round($open * fake()->randomFloat(2, 0.65, 0.92), 2);

            foreach ($this->splitAmount($toPay, 3, 8) as $amount) {
                $queue[] = [
                    'kind' => 'dump_payment',
                    'dump_site_id' => $dump->id,
                    'amount' => $amount,
                    'occurred_on' => fake()->randomElement($days)->toDateString(),
                    'payment_method' => fake()->randomElement([PaymentMethod::Cheque->value, PaymentMethod::BankTransfer->value]),
                    'cheque_no' => fake()->boolean(45) ? 'CK'.str_pad((string) (++$doc), 6, '0', STR_PAD_LEFT) : null,
                    'description' => 'Döküm bedeli mahsubu',
                ];
            }
        }

        return $queue;
    }

    /**
     * Invoice fuel firms for dispensed litres, then pay most of the invoice.
     *
     * @param  Collection<int, Carbon>  $days
     * @return list<array<string, mixed>>
     */
    private function queueFuelSettlements(CatalogStage $catalog, Collection $days, int &$doc): array
    {
        $queue = [];
        $stations = $catalog->allStations()->keyBy('id');

        $totals = FuelReceipt::query()
            ->selectRaw('fuel_party_id, fuel_station_id, COALESCE(SUM(quantity), 0) as liters, COALESCE(SUM(quantity * unit_price), 0) as total')
            ->groupBy('fuel_party_id', 'fuel_station_id')
            ->get();

        foreach ($totals as $row) {
            $station = $stations->get($row->fuel_station_id);

            if (! $station || (float) $row->total < 1_000) {
                continue;
            }

            $invoiceTotal = round((float) $row->total, 2);
            $litersTotal = round((float) $row->liters, 2);
            $siteId = $station->sites->isNotEmpty()
                ? $station->sites->random()->id
                : $catalog->allSites()->random()->id;

            $invoiceChunks = $this->splitAmount($invoiceTotal, 3, 7);
            $literChunks = $this->splitProportionally($litersTotal, $invoiceChunks);

            foreach ($invoiceChunks as $i => $amount) {
                $liters = $literChunks[$i] ?? round($amount / max((float) $station->unit_price, 0.01), 2);

                $queue[] = [
                    'kind' => 'fuel',
                    'direction' => 'credit',
                    'party_id' => $row->fuel_party_id,
                    'site_id' => $siteId,
                    'fuel_station_id' => $station->id,
                    'amount' => $amount,
                    'liters' => $liters,
                    'unit_price' => $station->unit_price,
                    'occurred_on' => fake()->randomElement($days)->toDateString(),
                    'payment_method' => PaymentMethod::BankTransfer->value,
                    'document_no' => 'AK-'.str_pad((string) (++$doc), 6, '0', STR_PAD_LEFT),
                    'description' => 'Akaryakıt faturası (fiş toplamı)',
                ];
            }

            $toPay = round($invoiceTotal * fake()->randomFloat(2, 0.72, 0.95), 2);

            foreach ($this->splitAmount($toPay, 2, 6) as $amount) {
                $queue[] = [
                    'kind' => 'fuel',
                    'direction' => 'debit',
                    'party_id' => $row->fuel_party_id,
                    'site_id' => $siteId,
                    'fuel_station_id' => $station->id,
                    'amount' => $amount,
                    'occurred_on' => fake()->randomElement($days)->toDateString(),
                    'payment_method' => PaymentMethod::BankTransfer->value,
                    'document_no' => 'AO-'.str_pad((string) (++$doc), 6, '0', STR_PAD_LEFT),
                    'description' => 'Akaryakıt ödemesi',
                ];
            }
        }

        return $queue;
    }

    /**
     * Settle leftover party credits after plate settlements have posted.
     *
     * @param  Collection<int, Carbon>  $days
     * @param  list<string>  $methods
     * @return list<array<string, mixed>>
     */
    private function queueOwnerResiduals(
        CatalogStage $catalog,
        Collection $days,
        array $methods,
        Collection $categories,
    ): array {
        $queue = [];
        $ownerIds = $catalog->partiesFor(PartyRole::VehicleOwner)
            ->concat($catalog->partiesFor(PartyRole::MachineOperator))
            ->pluck('id')
            ->unique()
            ->values();

        $balances = LedgerEntry::query()
            ->whereIn('party_id', $ownerIds)
            ->whereNull('reversal_of')
            ->groupBy('party_id')
            ->selectRaw('party_id, COALESCE(SUM(credit), 0) as credit_total, COALESCE(SUM(debit), 0) as debit_total')
            ->get();

        foreach ($balances as $row) {
            $credit = round((float) $row->credit_total, 2);
            $debit = round((float) $row->debit_total, 2);
            $open = round($credit - $debit, 2);

            if ($open < 2_000) {
                continue;
            }

            // Never pay more than open alacak — leave 15–40% residual.
            $toPay = round($open * fake()->randomFloat(2, 0.55, 0.85), 2);

            foreach ($this->splitAmount($toPay, 2, 6) as $amount) {
                $queue[] = [
                    'kind' => 'owner_payment',
                    'party_id' => $row->party_id,
                    'amount' => $amount,
                    'occurred_on' => fake()->randomElement($days)->toDateString(),
                    'payment_method' => fake()->randomElement($methods),
                    'cash_category_id' => $categories->random()->id,
                    'description' => 'Araç sahibi / operatör mahsubu',
                ];
            }
        }

        return $queue;
    }

    /**
     * Light typed extras for UI variety (not the volume driver).
     *
     * @param  Collection<int, Carbon>  $days
     * @param  list<string>  $methods
     * @return list<array<string, mixed>>
     */
    private function queueLightExtras(
        CatalogStage $catalog,
        Collection $days,
        array $methods,
        Collection $categories,
        Collection $banks,
        int &$doc,
    ): array {
        $queue = [];
        $day = fn () => fake()->randomElement($days)->toDateString();
        $site = fn () => $catalog->allSites()->random()->id;

        $suppliers = $catalog->partiesFor(PartyRole::Supplier);
        foreach ($suppliers->random(min(20, $suppliers->count())) as $party) {
            for ($n = 0; $n < 4; $n++) {
                $queue[] = [
                    'kind' => 'supplier',
                    'direction' => $n % 3 === 0 ? 'credit' : 'debit',
                    'party_id' => $party->id,
                    'site_id' => $site(),
                    'amount' => fake()->numberBetween(2_500, 35_000),
                    'occurred_on' => $day(),
                    'payment_method' => fake()->randomElement($methods),
                    'cash_category_id' => $categories->random()->id,
                    'document_no' => 'ST-'.str_pad((string) (++$doc), 6, '0', STR_PAD_LEFT),
                    'description' => fake()->randomElement(['Kum', 'Çakıl', 'Lastik', 'Yedek parça']),
                ];
            }
        }

        $customers = $catalog->partiesFor(PartyRole::Customer);
        foreach ($customers->random(min(25, $customers->count())) as $party) {
            for ($n = 0; $n < 4; $n++) {
                $queue[] = [
                    'kind' => 'customer',
                    'direction' => $n % 4 === 0 ? 'debit' : 'credit',
                    'party_id' => $party->id,
                    'site_id' => $site(),
                    'amount' => fake()->numberBetween(8_000, 90_000),
                    'occurred_on' => $day(),
                    'payment_method' => fake()->randomElement($methods),
                    'description' => fake()->randomElement(['Hakediş', 'Ara ödeme', 'Teminat iadesi']),
                ];
            }
        }

        $marketParties = $catalog->partiesFor(PartyRole::Market);
        $haulVehicles = $catalog->haulVehicles();

        if ($marketParties->isNotEmpty() && $haulVehicles->isNotEmpty()) {
            foreach ($marketParties->random(min(15, $marketParties->count())) as $party) {
                for ($n = 0; $n < 3; $n++) {
                    $vehicle = $haulVehicles->random();
                    $queue[] = [
                        'kind' => 'market',
                        'direction' => $n % 2 === 0 ? 'debit' : 'credit',
                        'party_id' => $party->id,
                        'plate' => $vehicle->plate,
                        'site_id' => $site(),
                        'amount' => fake()->numberBetween(1_500, 15_000),
                        'occurred_on' => $day(),
                        'payment_method' => PaymentMethod::Cash->value,
                        'description' => 'Piyasa seferi',
                    ];
                }
            }
        }

        foreach ($catalog->allSites()->random(min(40, $catalog->allSites()->count())) as $siteModel) {
            for ($n = 0; $n < 4; $n++) {
                $direction = $n % 2 === 0 ? 'debit' : 'credit';
                $queue[] = [
                    'kind' => 'site',
                    'direction' => $direction,
                    'site_id' => $siteModel->id,
                    'amount' => fake()->numberBetween(1_500, 30_000),
                    'occurred_on' => $day(),
                    'payment_method' => fake()->randomElement($methods),
                    'description' => $direction === 'debit' ? 'Şantiye geliri' : 'Şantiye gideri',
                ];
            }
        }

        for ($n = 0; $n < 400; $n++) {
            $direction = $n % 3 === 0 ? 'credit' : 'debit';
            $queue[] = [
                'kind' => 'cash',
                'direction' => $direction,
                'amount' => fake()->numberBetween(500, 40_000),
                'occurred_on' => $day(),
                'cash_category_id' => $categories->random()->id,
                'bank_id' => $n % 2 === 0 ? $banks->random()->id : null,
                'description' => fake()->randomElement(['Bankadan çekilen', 'Kasaya yatan', 'Ofis gideri', 'Nakit ihtiyaç']),
            ];
        }

        return $queue;
    }

    /**
     * @param  Collection<int, Carbon>  $days
     */
    private function dayWithOwner(CatalogStage $catalog, string $vehicleId, Collection $days): ?string
    {
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $day = fake()->randomElement($days);

            if ($catalog->ownerOn($vehicleId, $day)) {
                return $day->toDateString();
            }
        }

        return null;
    }

    /**
     * @return list<float>
     */
    private function splitAmount(float $total, int $minParts, int $maxParts): array
    {
        $total = round($total, 2);

        if ($total < 100) {
            return $total > 0 ? [$total] : [];
        }

        $parts = min($maxParts, max($minParts, (int) ceil($total / 80_000)));
        $parts = fake()->numberBetween($minParts, max($minParts, min($maxParts, $parts)));

        $weights = [];
        for ($i = 0; $i < $parts; $i++) {
            $weights[] = fake()->numberBetween(10, 40);
        }

        $weightSum = array_sum($weights);
        $chunks = [];
        $allocated = 0.0;

        foreach ($weights as $i => $weight) {
            if ($i === $parts - 1) {
                $chunks[] = round($total - $allocated, 2);

                break;
            }

            $chunk = round($total * ($weight / $weightSum), 2);
            $chunks[] = $chunk;
            $allocated = round($allocated + $chunk, 2);
        }

        return array_values(array_filter($chunks, static fn (float $amount): bool => $amount >= 50));
    }

    /**
     * @param  list<float>  $amountChunks
     * @return list<float>
     */
    private function splitProportionally(float $total, array $amountChunks): array
    {
        $amountSum = array_sum($amountChunks);

        if ($amountSum <= 0 || $total <= 0) {
            return array_fill(0, count($amountChunks), 0.0);
        }

        $chunks = [];
        $allocated = 0.0;

        foreach ($amountChunks as $i => $amount) {
            if ($i === count($amountChunks) - 1) {
                $chunks[] = round($total - $allocated, 2);

                break;
            }

            $chunk = round($total * ($amount / $amountSum), 2);
            $chunks[] = $chunk;
            $allocated = round($allocated + $chunk, 2);
        }

        return $chunks;
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function days(Carbon $from, Carbon $to): Collection
    {
        $days = collect();
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to)) {
            $days->push($cursor->copy());
            $cursor->addDay();
        }

        return $days;
    }
}
