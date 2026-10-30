<?php

namespace App\Models;

use Database\Factories\StockCountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical count of a stock item; difference (counted minus the book stock of that day)
 * corrects the stock. Own gain or loss — nothing is written to a cari.
 */
#[Fillable(['stock_item_id', 'counted_on', 'quantity', 'difference', 'description', 'created_by'])]
class StockCount extends Model
{
    /** @use HasFactory<StockCountFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'counted_on' => 'date',
            'quantity' => 'decimal:3',
            'difference' => 'decimal:3',
        ];
    }
}
