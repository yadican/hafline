<?php

namespace App\Models;

use App\Enums\StockItemUnit;
use Database\Factories\StockItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'unit', 'tracks_stock', 'cash_category_id', 'sales_price', 'active'])]
class StockItem extends Model
{
    /** @use HasFactory<StockItemFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Default expense category suggested on purchase invoice lines.
     *
     * @return BelongsTo<CashCategory, $this>
     */
    public function cashCategory(): BelongsTo
    {
        return $this->belongsTo(CashCategory::class)->withTrashed();
    }

    /**
     * Invoice lines that reference this item (purchase entries when the invoice is live).
     *
     * @return HasMany<InvoiceLine, $this>
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * Own-use exits of this item.
     *
     * @return HasMany<StockUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(StockUsage::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => StockItemUnit::class,
            'tracks_stock' => 'boolean',
            'sales_price' => 'decimal:4',
            'active' => 'boolean',
        ];
    }
}
