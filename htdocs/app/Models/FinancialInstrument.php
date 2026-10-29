<?php

namespace App\Models;

use App\Enums\InstrumentDirection;
use App\Enums\InstrumentStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A cheque or promissory note taken or given on a cari entry (its source). The entry already
 * settled the cari; the instrument follows the paper until the money really moves.
 */
#[Fillable([
    'payment_method',
    'direction',
    'status',
    'amount',
    'issued_on',
    'due_on',
    'number',
    'bank_name',
    'drawer',
    'party_id',
    'source_type',
    'source_id',
    'description',
    'created_by',
])]
class FinancialInstrument extends Model
{
    use HasUuids, SoftDeletes;

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_on->lt(Carbon::today());
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * The cari entry the cheque or note came in or went out with.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * @return HasMany<FinancialInstrumentMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(FinancialInstrumentMovement::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<FinancialInstrumentPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(FinancialInstrumentPhoto::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'direction' => InstrumentDirection::class,
            'status' => InstrumentStatus::class,
            'amount' => 'decimal:2',
            'issued_on' => 'date',
            'due_on' => 'date',
        ];
    }
}
