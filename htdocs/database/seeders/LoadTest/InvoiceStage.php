<?php

namespace Database\Seeders\LoadTest;

use App\Enums\CashDirection;
use App\Enums\InvoiceDirection;
use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoiceRecipient;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Exceptions\OperationRejectedException;
use App\Models\CashCategory;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Site;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InvoiceService;
use App\Services\StockCountService;
use App\Services\StockService;
use App\Services\StockUsageService;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 45 — real invoices (InvoiceService), stock movements and plate sales for the load-test window.
 *
 * Purchases enter stock first; customer / plate sales and own-use exits only take what book stock
 * still has on that day (K10). Supplier "fatura" cari rows from the old queue are gone (K5): the
 * invoice posts the cari line; payments match through InvoiceService::pay with 38b account rules.
 */
final class InvoiceStage
{
    private int $doc = 0;

    /** @var list<array{invoice: Invoice, pay_on: string, ratio: float}> */
    private array $scheduledPayments = [];

    /** @var array<string, float> Fixed purchase unit cost per item so average cost stays exact. */
    private array $itemCosts = [];

    public function __construct(
        private Progress $progress,
        private InvoiceService $invoices,
        private StockService $stock,
        private StockUsageService $usages,
        private StockCountService $counts,
    ) {}

    public function run(CatalogStage $catalog, Carbon $from, Carbon $to): void
    {
        $this->progress->stage('Fatura · stok · plakaya satış');

        $userId = User::query()->value('id');
        $tracked = $catalog->trackedStockItems();
        $service = $catalog->stockItems()->firstWhere('tracks_stock', false);

        if ($tracked->isEmpty()) {
            $this->progress->done('stok kalemi yok · atlandı');

            return;
        }

        // Purchases first; exits/counts in calendar order so frozen unit costs match the audit walk.
        $purchases = $this->seedPurchases($catalog, $from, $to, $userId, $tracked, $service);
        $exits = $this->seedExitsChronologically($catalog, $from, $to, $userId, $tracked, $service);
        $payments = $this->seedPayments($catalog, $to, $userId);

        $this->progress->done(sprintf(
            'alış %d · satış %d · plaka %d · kullanım %d · sayım %d · ödeme %d',
            $purchases,
            $exits['sales'],
            $exits['plate'],
            $exits['usages'],
            $exits['counts'],
            $payments,
        ));
    }

    /**
     * Plan customer/plate sales, usages and counts, then apply them oldest-first so each freeze of
     * average cost sees the same prior movements ledger:audit will walk (K10).
     *
     * @param  Collection<int, StockItem>  $tracked
     * @return array{sales: int, plate: int, usages: int, counts: int}
     */
    public function seedExitsChronologically(
        CatalogStage $catalog,
        Carbon $from,
        Carbon $to,
        ?string $userId,
        Collection $tracked,
        ?StockItem $service,
    ): array {
        $days = $this->exitDays($from, $to);
        $customers = $catalog->partiesFor(PartyRole::Customer);
        $vehicles = $catalog->haulVehicles();
        $allVehicles = $catalog->allVehicles();
        $sites = $catalog->allSites();
        $income = $catalog->categoryFor(CashDirection::Income);
        $expense = $catalog->categoryFor(CashDirection::Expense);

        /** @var list<array{on: string, type: string, run: callable(): bool}> $events */
        $events = [];

        $saleTarget = max(6, (int) round($customers->count() * OperationsStage::MONTHS * 0.2));
        for ($n = 0; $n < $saleTarget; $n++) {
            $on = fake()->randomElement($days)->toDateString();
            $events[] = [
                'on' => $on,
                'type' => 'sale',
                'run' => fn (): bool => $this->writeCustomerSale(
                    $catalog,
                    $customers,
                    $tracked,
                    $service,
                    $income,
                    $on,
                    $to,
                    $userId,
                ),
            ];
        }

        $plateTarget = max(8, (int) round($vehicles->count() * OperationsStage::MONTHS * 0.03));
        for ($n = 0; $n < $plateTarget; $n++) {
            $on = fake()->randomElement($days)->toDateString();
            $events[] = [
                'on' => $on,
                'type' => 'plate',
                'run' => fn (): bool => $this->writePlateSale($catalog, $vehicles, $tracked, $income, $on, $userId),
            ];
        }

        $usageTarget = max(8, (int) round($tracked->count() * OperationsStage::MONTHS * 0.5));
        if ($expense !== null) {
            for ($n = 0; $n < $usageTarget; $n++) {
                $on = fake()->randomElement($days)->toDateString();
                $events[] = [
                    'on' => $on,
                    'type' => 'usage',
                    'run' => fn (): bool => $this->writeUsage($tracked, $sites, $allVehicles, $expense->id, $on),
                ];
            }
        }

        $countTarget = min(12, max(2, (int) round($tracked->count() * 0.4)));
        for ($n = 0; $n < $countTarget; $n++) {
            $on = fake()->randomElement($days)->toDateString();
            $events[] = [
                'on' => $on,
                'type' => 'count',
                'run' => fn (): bool => $this->writeCount($tracked, $on),
            ];
        }

        usort($events, fn (array $a, array $b): int => [$a['on'], $a['type']] <=> [$b['on'], $b['type']]);

        $created = ['sales' => 0, 'plate' => 0, 'usages' => 0, 'counts' => 0];
        $key = ['sale' => 'sales', 'plate' => 'plate', 'usage' => 'usages', 'count' => 'counts'];

        foreach ($events as $event) {
            if ($event['run']()) {
                $created[$key[$event['type']]]++;
            }
        }

        return $created;
    }

    /**
     * Material purchases from each supplier; most stay open and get a later payment.
     *
     * @param  Collection<int, StockItem>  $tracked
     */
    public function seedPurchases(
        CatalogStage $catalog,
        Carbon $from,
        Carbon $to,
        ?string $userId,
        Collection $tracked,
        ?StockItem $service,
    ): int {
        $days = $this->days($from, $to);
        $sites = $catalog->allSites();
        $expense = $catalog->categoryFor(CashDirection::Expense);
        $created = 0;

        foreach ($catalog->partiesFor(PartyRole::Supplier) as $party) {
            $count = fake()->numberBetween(1, 2) * OperationsStage::MONTHS;

            for ($n = 0; $n < $count; $n++) {
                $on = fake()->randomElement($days);
                $item = $tracked->random();
                $qty = $this->purchaseQty($item);
                $unitCost = $this->purchaseUnitCost($item);
                $lines = [[
                    'description' => $item->name,
                    'stock_item_id' => $item->id,
                    'quantity' => $this->qty($qty),
                    'unit_price' => $this->money($unitCost),
                    'vat_rate' => '0',
                    'cash_category_id' => $item->cash_category_id ?? $expense?->id,
                ]];

                if ($service !== null && fake()->boolean(20)) {
                    $lines[] = [
                        'description' => $service->name,
                        'stock_item_id' => $service->id,
                        'quantity' => '1',
                        'unit_price' => $this->money((float) $service->sales_price * fake()->randomFloat(2, 0.7, 1.0)),
                        'vat_rate' => '0',
                        'cash_category_id' => $service->cash_category_id ?? $expense?->id,
                    ];
                }

                $payload = [
                    'direction' => InvoiceDirection::Purchase->value,
                    'party_id' => $party->id,
                    'invoice_no' => $this->invoiceNo('A'),
                    'issued_on' => $on->toDateString(),
                    'site_id' => $sites->isNotEmpty() && fake()->boolean(70) ? $sites->random()->id : null,
                    'description' => $item->name,
                    'lines' => $lines,
                    ...$this->entryPayment($catalog, InvoiceDirection::Purchase, $on),
                ];

                $invoice = $this->invoices->create($payload, $userId);
                $this->schedulePayment($invoice, $on, $to, InvoiceDirection::Purchase);
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param  Collection<int, Party>  $customers
     * @param  Collection<int, StockItem>  $tracked
     */
    private function writeCustomerSale(
        CatalogStage $catalog,
        Collection $customers,
        Collection $tracked,
        ?StockItem $service,
        ?CashCategory $income,
        string $on,
        Carbon $to,
        ?string $userId,
    ): bool {
        if ($customers->isEmpty()) {
            return false;
        }

        $day = Carbon::parse($on);
        $item = $tracked->random();
        $available = $this->exitAvailable($item, $day);

        if ($available <= 0) {
            return false;
        }

        $qty = $this->exitQty($item, $available);
        $unitPrice = max(
            (float) $item->sales_price,
            $this->stock->stock($item, $day)['average_cost'] * fake()->randomFloat(2, 1.05, 1.4),
        );

        $lines = [[
            'description' => $item->name,
            'stock_item_id' => $item->id,
            'quantity' => $this->qty($qty),
            'unit_price' => $this->money($unitPrice),
            'vat_rate' => '0',
            'cash_category_id' => $income?->id,
        ]];

        if ($service !== null && fake()->boolean(15)) {
            $lines[] = [
                'description' => $service->name,
                'stock_item_id' => $service->id,
                'quantity' => '1',
                'unit_price' => $this->money((float) $service->sales_price),
                'vat_rate' => '0',
                'cash_category_id' => $income?->id,
            ];
        }

        try {
            $invoice = $this->invoices->create([
                'direction' => InvoiceDirection::Sale->value,
                'recipient' => InvoiceRecipient::Party->value,
                'party_id' => $customers->random()->id,
                'invoice_no' => $this->invoiceNo('S'),
                'issued_on' => $on,
                'description' => $item->name,
                'lines' => $lines,
                ...$this->entryPayment($catalog, InvoiceDirection::Sale, $day),
            ], $userId);
        } catch (OperationRejectedException) {
            return false;
        }

        $this->schedulePayment($invoice, $day, $to, InvoiceDirection::Sale);

        return true;
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @param  Collection<int, StockItem>  $tracked
     */
    private function writePlateSale(
        CatalogStage $catalog,
        Collection $vehicles,
        Collection $tracked,
        ?CashCategory $income,
        string $on,
        ?string $userId,
    ): bool {
        if ($vehicles->isEmpty()) {
            return false;
        }

        $day = Carbon::parse($on);
        $vehicle = $vehicles->random();

        if (! $catalog->ownerOn($vehicle->id, $day)) {
            return false;
        }

        $item = $tracked->random();
        $available = $this->exitAvailable($item, $day);

        if ($available <= 0) {
            return false;
        }

        $qty = $this->exitQty($item, $available);
        $unitPrice = (float) ($item->sales_price ?: $this->stock->stock($item, $day)['average_cost'] * 1.2);

        try {
            $this->invoices->create([
                'direction' => InvoiceDirection::Sale->value,
                'recipient' => InvoiceRecipient::Plate->value,
                'plate' => $vehicle->plate,
                'invoice_no' => $this->invoiceNo('P'),
                'issued_on' => $on,
                'description' => $item->name.' · plaka',
                'payment_status' => InvoicePaymentStatus::Unpaid->value,
                'lines' => [[
                    'description' => $item->name,
                    'stock_item_id' => $item->id,
                    'quantity' => $this->qty($qty),
                    'unit_price' => $this->money($unitPrice),
                    'vat_rate' => '0',
                    'cash_category_id' => $income?->id,
                ]],
            ], $userId);
        } catch (OperationRejectedException) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, StockItem>  $tracked
     * @param  Collection<int, Site>  $sites
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function writeUsage(
        Collection $tracked,
        Collection $sites,
        Collection $vehicles,
        string $expenseCategoryId,
        string $on,
    ): bool {
        $day = Carbon::parse($on);
        $item = $tracked->random();
        $available = $this->exitAvailable($item, $day);

        if ($available <= 0) {
            return false;
        }

        $qty = $this->exitQty($item, $available);
        $target = $this->usageTarget($sites, $vehicles);

        if ($target === null) {
            return false;
        }

        try {
            $this->usages->create([
                'stock_item_id' => $item->id,
                'quantity' => $this->qty($qty),
                'used_on' => $on,
                'site_id' => $target['site_id'],
                'vehicle_id' => $target['vehicle_id'],
                'cash_category_id' => $item->cash_category_id ?? $expenseCategoryId,
                'description' => 'Stok kullanım · '.$item->name,
            ]);
        } catch (OperationRejectedException) {
            return false;
        }

        return true;
    }

    /**
     * stock_usages_target_check: site_id or vehicle_id (or both). Never neither.
     *
     * @param  Collection<int, Site>  $sites
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array{site_id: ?string, vehicle_id: ?string}|null
     */
    private function usageTarget(Collection $sites, Collection $vehicles): ?array
    {
        $siteId = $sites->isNotEmpty() && fake()->boolean(60) ? $sites->random()->id : null;
        $vehicleId = $vehicles->isNotEmpty() && fake()->boolean(50) ? $vehicles->random()->id : null;

        if ($siteId !== null || $vehicleId !== null) {
            return ['site_id' => $siteId, 'vehicle_id' => $vehicleId];
        }

        if ($sites->isNotEmpty()) {
            return ['site_id' => $sites->random()->id, 'vehicle_id' => null];
        }

        if ($vehicles->isNotEmpty()) {
            return ['site_id' => null, 'vehicle_id' => $vehicles->random()->id];
        }

        return null;
    }

    /**
     * @param  Collection<int, StockItem>  $tracked
     */
    private function writeCount(Collection $tracked, string $on): bool
    {
        $day = Carbon::parse($on);
        $item = $tracked->random();
        $book = $this->stock->stock($item, $day)['quantity'];

        if ($book <= 0) {
            return false;
        }

        try {
            $this->counts->create([
                'stock_item_id' => $item->id,
                'quantity' => $this->qty($book),
                'counted_on' => $on,
                'description' => 'Stok sayımı · '.$item->name,
            ]);
        } catch (OperationRejectedException) {
            return false;
        }

        return true;
    }

    /**
     * Deferred invoice payments (7–30 days later, 70–100% of remaining) with 38b accounts.
     */
    public function seedPayments(CatalogStage $catalog, Carbon $to, ?string $userId): int
    {
        $paid = 0;

        foreach ($this->scheduledPayments as $row) {
            $payOn = Carbon::parse($row['pay_on']);

            if ($payOn->gt($to)) {
                continue;
            }

            $invoice = $row['invoice']->fresh();

            if ($invoice === null || $invoice->remainingAmount() <= 0) {
                continue;
            }

            $amount = $this->roundDown($invoice->remainingAmount() * $row['ratio']);

            if ($amount < 1) {
                continue;
            }

            $method = fake()->randomElement([
                PaymentMethod::BankTransfer,
                PaymentMethod::BankTransfer,
                PaymentMethod::Cash,
                PaymentMethod::CreditCard,
            ]);

            $payment = $catalog->withInstrumentDetails($catalog->withPaymentAccount([
                'amount' => $this->money($amount),
                'occurred_on' => $payOn->toDateString(),
                'payment_method' => $method->value,
                'description' => $invoice->direction === InvoiceDirection::Purchase
                    ? 'Tedarikçi fatura ödemesi'
                    : 'Müşteri fatura tahsilatı',
            ]));

            $this->invoices->pay($invoice, $payment, $userId);
            $paid++;
        }

        return $paid;
    }

    /**
     * @return array{payment_status: string, payment?: array<string, mixed>}
     */
    private function entryPayment(CatalogStage $catalog, InvoiceDirection $direction, Carbon $on): array
    {
        // Mostly unpaid (later pay / leave open); some paid in full at entry (K3).
        if (fake()->boolean(75)) {
            return ['payment_status' => InvoicePaymentStatus::Unpaid->value];
        }

        $method = fake()->randomElement([
            PaymentMethod::BankTransfer,
            PaymentMethod::BankTransfer,
            PaymentMethod::Cash,
            PaymentMethod::CreditCard,
        ]);

        return [
            'payment_status' => InvoicePaymentStatus::Paid->value,
            'payment' => $catalog->withInstrumentDetails($catalog->withPaymentAccount([
                'occurred_on' => $on->toDateString(),
                'payment_method' => $method->value,
            ])),
        ];
    }

    private function schedulePayment(Invoice $invoice, Carbon $issuedOn, Carbon $to, InvoiceDirection $direction): void
    {
        if ($invoice->paymentStatus() === InvoicePaymentStatus::Paid) {
            return;
        }

        // Leave a share unpaid; plate sales are never scheduled here.
        if ($direction === InvoiceDirection::Sale && fake()->boolean(25)) {
            return;
        }

        if ($direction === InvoiceDirection::Purchase && fake()->boolean(15)) {
            return;
        }

        $payOn = $issuedOn->copy()->addDays(fake()->numberBetween(7, 30));

        if ($payOn->gt($to)) {
            return;
        }

        $this->scheduledPayments[] = [
            'invoice' => $invoice,
            'pay_on' => $payOn->toDateString(),
            'ratio' => fake()->randomFloat(2, 0.7, 1.0),
        ];
    }

    private function purchaseQty(StockItem $item): float
    {
        // Whole units keep average-cost money identity within ledger:audit tolerance.
        return match ($item->unit->value) {
            'lt', 'kg' => (float) fake()->numberBetween(20, 200),
            default => (float) fake()->numberBetween(2, 24),
        };
    }

    private function purchaseUnitCost(StockItem $item): float
    {
        if (isset($this->itemCosts[$item->id])) {
            return $this->itemCosts[$item->id];
        }

        $sale = (float) $item->sales_price;
        $base = $sale > 0 ? $sale * 0.7 : 100;

        return $this->itemCosts[$item->id] = (float) max(1, (int) round($base));
    }

    /**
     * Leave a small buffer so exits never empty the item (average-cost rounding stays audit-clean).
     */
    private function exitAvailable(StockItem $item, Carbon $on): float
    {
        $book = $this->stock->stock($item, $on)['quantity'];
        $buffer = match ($item->unit->value) {
            'lt', 'kg' => 5.0,
            default => 2.0,
        };

        return (float) max(0, (int) floor($book - $buffer));
    }

    private function exitQty(StockItem $item, float $available): float
    {
        $max = max(1, (int) floor($available));
        $want = match ($item->unit->value) {
            'lt', 'kg' => fake()->numberBetween(1, min(40, $max)),
            default => fake()->numberBetween(1, min(6, $max)),
        };

        return (float) $want;
    }

    private function invoiceNo(string $prefix): string
    {
        return $prefix.'-'.str_pad((string) (++$this->doc), 6, '0', STR_PAD_LEFT);
    }

    private function qty(float $value): string
    {
        return number_format(round($value, 3), 3, '.', '');
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    private function roundDown(float $amount): float
    {
        return floor($amount / 50) * 50;
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
     * Exits run after purchases have had time to land, so book stock is usually available (K10).
     *
     * @return list<Carbon>
     */
    private function exitDays(Carbon $from, Carbon $to): array
    {
        $days = $this->days($from, $to);
        $offset = (int) floor(count($days) * 0.35);

        return array_values(array_slice($days, $offset)) ?: $days;
    }
}
