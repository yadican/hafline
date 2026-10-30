<?php

namespace App\Models;

use Database\Factories\StockUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Own-use stock exit: quantity leaves book stock at the frozen average cost. No cari party;
 * the cost is a site/vehicle expense (see SiteExpenseReport when site_id is set).
 */
#[Fillable([
    'stock_item_id',
    'quantity',
    'unit_cost',
    'amount',
    'used_on',
    'site_id',
    'vehicle_id',
    'cash_category_id',
    'description',
    'created_by',
])]
class StockUsage extends Model
{
    /** @use HasFactory<StockUsageFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<CashCategory, $this>
     */
    public function cashCategory(): BelongsTo
    {
        return $this->belongsTo(CashCategory::class)->withTrashed();
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
            'used_on' => 'date',
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }
}
