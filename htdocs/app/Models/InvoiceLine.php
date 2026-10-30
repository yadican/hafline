<?php

namespace App\Models;

use Database\Factories\InvoiceLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an invoice: subtotal = quantity × unit price, VAT on top at the line's rate. The
 * category is the expense (purchase) or income (sale) item the line is reported under. On a live
 * sale of a tracked item, unit_cost freezes that day's weighted average so later buys do not change it.
 */
#[Fillable([
    'invoice_id',
    'description',
    'quantity',
    'unit_price',
    'vat_rate',
    'subtotal',
    'vat_amount',
    'total',
    'cash_category_id',
    'stock_item_id',
    'unit_cost',
    'sort_order',
])]
class InvoiceLine extends Model
{
    /** @use HasFactory<InvoiceLineFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * @return BelongsTo<CashCategory, $this>
     */
    public function cashCategory(): BelongsTo
    {
        return $this->belongsTo(CashCategory::class)->withTrashed();
    }

    /**
     * Optional stock item; a tracked item on a live purchase enters stock, on a live sale leaves it.
     *
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'vat_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'unit_cost' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }
}
