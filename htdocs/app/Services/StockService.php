<?php

namespace App\Services;

use App\Exceptions\OperationRejectedException;
use App\Models\StockItem;
use App\Repositories\StockRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Book stock and its weighted average cost (same math as our own fuel tank). Purchases and sale
 * returns raise quantity at their line cost (purchase matrah / frozen sale unit_cost); purchase
 * returns leave at that return's purchase cost (matrah), which adjusts the average; sales and
 * other exits leave at the running average. A count correction enters at the current average (or
 * 0 when empty) and exits like usage. A sale, purchase return, own-use exit or downward count may
 * not leave book stock negative on its date or on any later day (K10). A live purchase may not
 * shrink or be deleted below the quantity later exits still need from it (sale returns count as
 * stock available again).
 */
class StockService extends Service
{
    public function __construct(private StockRepository $stock) {}

    /**
     * Remaining quantity, weighted average unit cost and stock value on the date (today when null).
     *
     * @return array{quantity: float, average_cost: float, value: float}
     */
    public function stock(
        StockItem $item,
        ?CarbonInterface $on = null,
        ?string $exceptInvoiceId = null,
        ?string $exceptUsageId = null,
        ?string $exceptCountId = null,
    ): array {
        $quantity = 0.0;
        $value = 0.0;
        $average = 0.0;

        foreach ($this->stock->movements($item, $on, $exceptInvoiceId, $exceptUsageId, $exceptCountId) as $movement) {
            if (in_array($movement['kind'], ['purchase', 'sale_return'], true)) {
                $price = $movement['quantity'] > 0 ? $movement['amount'] / $movement['quantity'] : 0.0;
                $wasEmpty = $quantity <= 0;
                $quantity += $movement['quantity'];
                $value = $wasEmpty ? $quantity * $price : $value + $movement['amount'];
                $average = $quantity > 0 ? $value / $quantity : $price;

                continue;
            }

            if ($movement['kind'] === 'purchase_return') {
                $quantity += $movement['quantity'];
                $value -= $movement['amount'];
                $average = $quantity > 0 ? $value / $quantity : 0.0;

                continue;
            }

            $quantity += $movement['quantity'];
            $value = $quantity * $average;
        }

        return [
            'quantity' => round($quantity, 3),
            'average_cost' => round($average, 4),
            'value' => round($value, 2),
        ];
    }

    /**
     * Rejects when shrinking (or removing) a live purchase entry would leave book stock negative on
     * any day. Sale returns count as available again. Pass quantity 0 when deleting the purchase.
     *
     * @param  'update'|'delete'  $action
     */
    public function assertPurchaseKeepsUsedStock(
        StockItem $item,
        CarbonInterface|string $purchasedOn,
        float $quantity,
        string $exceptInvoiceId,
        string $action = 'update',
    ): void {
        if (! $item->tracks_stock) {
            return;
        }

        $proposed = round(max(0.0, $quantity), 3);

        if ($this->purchaseKeepsStockNonNegative($item, $purchasedOn, $proposed, $exceptInvoiceId)) {
            return;
        }

        if ($action === 'delete') {
            throw new OperationRejectedException('Stok çıkışı olan alış faturası silinemez.');
        }

        throw new OperationRejectedException(sprintf(
            '%s alış miktarı kullanılmış stokun altına düşürülemez (kullanılan %s %s).',
            $item->name,
            $this->quantity($this->purchaseUsageFloor($item, $exceptInvoiceId, $purchasedOn)),
            $item->unit->value,
        ));
    }

    /**
     * Minimum entry quantity on $purchasedOn that keeps book stock non-negative after excluding the
     * invoice being edited. When an earlier gap cannot be filled from this date, returns the deficit
     * seen so the shrink message still names a used quantity.
     */
    private function purchaseUsageFloor(
        StockItem $item,
        string $exceptInvoiceId,
        CarbonInterface|string $purchasedOn,
    ): float {
        $day = Carbon::parse($purchasedOn)->toDateString();
        $order = ['purchase' => 0, 'sale_return' => 0, 'purchase_return' => 1, 'sale' => 1, 'usage' => 1, 'count' => 2];
        $running = 0.0;
        $minimumAfter = 0.0;
        $inserted = false;
        $beforeDeficit = 0.0;

        foreach ($this->stock->movements($item, exceptInvoiceId: $exceptInvoiceId) as $movement) {
            if (! $inserted && ($movement['date'] > $day || ($movement['date'] === $day && $order[$movement['kind']] > 0))) {
                $inserted = true;
                $minimumAfter = $running;
            }

            $running = round($running + $movement['quantity'], 3);

            if (! $inserted && $running < 0) {
                $beforeDeficit = max($beforeDeficit, -$running);
            }

            if ($inserted) {
                $minimumAfter = min($minimumAfter, $running);
            }
        }

        if (! $inserted) {
            $minimumAfter = $running;
        }

        return round(max($beforeDeficit, -$minimumAfter, 0.0), 3);
    }

    /**
     * Whether injecting this purchase quantity on $purchasedOn (excluding the edited invoice) keeps
     * every day's book stock non-negative.
     */
    private function purchaseKeepsStockNonNegative(
        StockItem $item,
        CarbonInterface|string $purchasedOn,
        float $quantity,
        string $exceptInvoiceId,
    ): bool {
        $day = Carbon::parse($purchasedOn)->toDateString();
        $order = ['purchase' => 0, 'sale_return' => 0, 'purchase_return' => 1, 'sale' => 1, 'usage' => 1, 'count' => 2];
        $movements = $this->stock->movements($item, exceptInvoiceId: $exceptInvoiceId);

        if ($quantity > 0) {
            $movements = $movements
                ->push([
                    'date' => $day,
                    'kind' => 'purchase',
                    'quantity' => round($quantity, 3),
                    'amount' => 0.0,
                    'invoice_id' => null,
                    'invoice_no' => null,
                    'stock_usage_id' => null,
                    'stock_count_id' => null,
                ])
                ->sortBy([
                    fn (array $a, array $b): int => [$a['date'], $order[$a['kind']]] <=> [$b['date'], $order[$b['kind']]],
                ])
                ->values();
        }

        $running = 0.0;

        foreach ($movements as $movement) {
            $running = round($running + $movement['quantity'], 3);

            if ($running < 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rejects when the proposed live sale would leave this item's book stock negative on the sale
     * day or any later movement day. Pass exceptInvoiceId when replacing an existing live sale.
     */
    public function assertSaleKeepsStockNonNegative(
        StockItem $item,
        CarbonInterface|string $soldOn,
        float $quantity,
        ?string $exceptInvoiceId = null,
    ): void {
        $this->assertExitKeepsStockNonNegative($item, $soldOn, $quantity, 'sale', $exceptInvoiceId);
    }

    /**
     * Same non-negative walk as a sale, for a purchase return exiting at purchase cost.
     */
    public function assertPurchaseReturnKeepsStockNonNegative(
        StockItem $item,
        CarbonInterface|string $returnedOn,
        float $quantity,
        ?string $exceptInvoiceId = null,
    ): void {
        $this->assertExitKeepsStockNonNegative($item, $returnedOn, $quantity, 'purchase_return', $exceptInvoiceId);
    }

    /**
     * Same non-negative walk as a sale, for an own-use exit. Pass exceptUsageId when editing.
     */
    public function assertUsageKeepsStockNonNegative(
        StockItem $item,
        CarbonInterface|string $usedOn,
        float $quantity,
        ?string $exceptUsageId = null,
    ): void {
        $this->assertExitKeepsStockNonNegative($item, $usedOn, $quantity, 'usage', exceptUsageId: $exceptUsageId);
    }

    /**
     * Rejects when a count whose difference reduces stock would leave book stock negative on that
     * day or any later movement day. Pass exceptCountId when editing.
     */
    public function assertCountKeepsStockNonNegative(
        StockItem $item,
        CarbonInterface|string $countedOn,
        float $difference,
        ?string $exceptCountId = null,
    ): void {
        if (! $item->tracks_stock || $difference >= 0) {
            return;
        }

        $this->assertExitKeepsStockNonNegative(
            $item,
            $countedOn,
            abs($difference),
            'count',
            exceptCountId: $exceptCountId,
        );
    }

    /**
     * @param  'sale'|'purchase_return'|'usage'|'count'  $kind
     */
    private function assertExitKeepsStockNonNegative(
        StockItem $item,
        CarbonInterface|string $on,
        float $quantity,
        string $kind,
        ?string $exceptInvoiceId = null,
        ?string $exceptUsageId = null,
        ?string $exceptCountId = null,
    ): void {
        if (! $item->tracks_stock || $quantity <= 0) {
            return;
        }

        $day = Carbon::parse($on)->toDateString();
        $order = ['purchase' => 0, 'sale_return' => 0, 'purchase_return' => 1, 'sale' => 1, 'usage' => 1, 'count' => 2];
        $movements = $this->stock->movements($item, exceptInvoiceId: $exceptInvoiceId, exceptUsageId: $exceptUsageId, exceptCountId: $exceptCountId)
            ->push([
                'date' => $day,
                'kind' => $kind,
                'quantity' => -round($quantity, 3),
                'amount' => 0.0,
                'invoice_id' => null,
                'invoice_no' => null,
                'stock_usage_id' => null,
                'stock_count_id' => null,
            ])
            ->sortBy([
                fn (array $a, array $b): int => [$a['date'], $order[$a['kind']]] <=> [$b['date'], $order[$b['kind']]],
            ])
            ->values();

        $running = 0.0;
        $verb = match ($kind) {
            'usage' => 'kullanımı',
            'count' => 'sayımı',
            'purchase_return' => 'iadesi',
            default => 'satışı',
        };

        foreach ($movements as $movement) {
            $running = round($running + $movement['quantity'], 3);

            if ($movement['date'] < $day || $running >= 0) {
                continue;
            }

            $available = $this->stock($item, Carbon::parse($day), $exceptInvoiceId, $exceptUsageId, $exceptCountId)['quantity'];

            if (round($available - $quantity, 3) < 0) {
                throw new OperationRejectedException(sprintf(
                    '%s stokunda yeterli miktar yok (%s tarihinde kalan %s %s).',
                    $item->name,
                    Carbon::parse($day)->format('d.m.Y'),
                    $this->quantity($available),
                    $item->unit->value,
                ));
            }

            throw new OperationRejectedException(sprintf(
                '%s %s sonraki stok hareketlerini eksiye düşürür (%s tarihinde kalan %s %s).',
                $item->name,
                $verb,
                Carbon::parse($day)->format('d.m.Y'),
                $this->quantity($available),
                $item->unit->value,
            ));
        }
    }

    private function quantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, ',', '.'), '0'), ',');
    }
}
