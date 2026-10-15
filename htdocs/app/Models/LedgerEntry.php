<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'party_id',
    'occurred_on',
    'debit',
    'credit',
    'description',
    'source_type',
    'source_id',
    'vehicle_id',
    'payment_method',
    'cash_category_id',
    'reversal_of',
    'created_by',
])]
class LedgerEntry extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
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
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function reversed(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of');
    }

    /**
     * @return HasOne<self, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'created_at' => 'datetime',
        ];
    }
}
