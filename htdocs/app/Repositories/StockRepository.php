<?php

namespace App\Repositories;

use App\Enums\InvoiceDirection;
use App\Models\InvoiceLine;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\StockUsage;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Stock movements of one item: purchase / sale-return entries in, sale / purchase-return / own-use
 * exits out, count corrections (difference = counted − book that day).
 */
class StockRepository
{
    /**
     * Movements up to the date (all when null) in day order. Same-day purchase and sale-return
     * entries come first so stock bought or returned that morning is available for same-day exits;
     * counts come last so the book for a count includes that day's purchases and exits. Draft and
     * soft-deleted invoices / soft-deleted usages and counts are excluded. Pass except* ids when
     * re-checking a live document being edited. Document fields link the row to its source.
     *
     * @return Collection<int, array{date: string, kind: 'purchase'|'sale_return'|'purchase_return'|'sale'|'usage'|'count', quantity: float, amount: float, invoice_id: ?string, invoice_no: ?string, stock_usage_id: ?string, stock_count_id: ?string}>
     */
    public function movements(
        StockItem $item,
        ?CarbonInterface $until = null,
        ?string $exceptInvoiceId = null,
        ?string $exceptUsageId = null,
        ?string $exceptCountId = null,
    ): Collection {
        if (! $item->tracks_stock) {
            return collect();
        }

        $day = $until?->toDateString();
        $order = ['purchase' => 0, 'sale_return' => 0, 'purchase_return' => 1, 'sale' => 1, 'usage' => 1, 'count' => 2];

        $invoiceMovements = InvoiceLine::query()
            ->where('stock_item_id', $item->id)
            ->when($exceptInvoiceId, fn ($query) => $query->where('invoice_id', '!=', $exceptInvoiceId))
            ->whereHas('invoice', fn ($query) => $query
                ->withoutTrashed()
                ->whereIn('direction', [
                    InvoiceDirection::Purchase,
                    InvoiceDirection::Sale,
                    InvoiceDirection::PurchaseReturn,
                    InvoiceDirection::SaleReturn,
                ])
                ->where('is_draft', false)
                ->when($day, fn ($query) => $query->whereDate('issued_on', '<=', $day)))
            ->with(['invoice' => fn ($query) => $query->withoutTrashed()->select('id', 'issued_on', 'invoice_no', 'direction')])
            ->get(['id', 'invoice_id', 'quantity', 'subtotal', 'unit_cost'])
            ->map(function (InvoiceLine $line): array {
                $direction = $line->invoice->direction;
                $quantity = (float) $line->quantity;
                $entry = in_array($direction, [InvoiceDirection::Purchase, InvoiceDirection::SaleReturn], true);
                $purchaseReturn = $direction === InvoiceDirection::PurchaseReturn;

                return [
                    'date' => $line->invoice->issued_on->toDateString(),
                    'kind' => match ($direction) {
                        InvoiceDirection::Purchase => 'purchase',
                        InvoiceDirection::SaleReturn => 'sale_return',
                        InvoiceDirection::PurchaseReturn => 'purchase_return',
                        InvoiceDirection::Sale => 'sale',
                    },
                    'quantity' => $entry ? $quantity : -$quantity,
                    'amount' => match (true) {
                        $direction === InvoiceDirection::Purchase, $purchaseReturn => (float) $line->subtotal,
                        default => round($quantity * (float) ($line->unit_cost ?? 0), 2),
                    },
                    'invoice_id' => $line->invoice_id,
                    'invoice_no' => $line->invoice->invoice_no,
                    'stock_usage_id' => null,
                    'stock_count_id' => null,
                ];
            });

        $usageMovements = StockUsage::query()
            ->where('stock_item_id', $item->id)
            ->when($exceptUsageId, fn ($query) => $query->where('id', '!=', $exceptUsageId))
            ->when($day, fn ($query) => $query->whereDate('used_on', '<=', $day))
            ->get(['id', 'used_on', 'quantity', 'amount'])
            ->map(fn (StockUsage $usage): array => [
                'date' => $usage->used_on->toDateString(),
                'kind' => 'usage',
                'quantity' => -((float) $usage->quantity),
                'amount' => (float) $usage->amount,
                'invoice_id' => null,
                'invoice_no' => null,
                'stock_usage_id' => $usage->id,
                'stock_count_id' => null,
            ]);

        $countMovements = StockCount::query()
            ->where('stock_item_id', $item->id)
            ->when($exceptCountId, fn ($query) => $query->where('id', '!=', $exceptCountId))
            ->when($day, fn ($query) => $query->whereDate('counted_on', '<=', $day))
            ->get(['id', 'counted_on', 'difference'])
            ->map(fn (StockCount $count): array => [
                'date' => $count->counted_on->toDateString(),
                'kind' => 'count',
                'quantity' => (float) $count->difference,
                'amount' => 0.0,
                'invoice_id' => null,
                'invoice_no' => null,
                'stock_usage_id' => null,
                'stock_count_id' => $count->id,
            ]);

        return $invoiceMovements
            ->concat($usageMovements)
            ->concat($countMovements)
            ->sortBy([
                fn (array $a, array $b): int => [$a['date'], $order[$a['kind']]] <=> [$b['date'], $order[$b['kind']]],
            ])
            ->values();
    }
}
