<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hesaplar arası virman: the amount leaves one of our accounts and enters another; no cari moves.
 */
#[Fillable([
    'from_bank_id',
    'to_bank_id',
    'occurred_on',
    'amount',
    'description',
    'created_by',
])]
class AccountTransfer extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function fromBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'from_bank_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function toBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'to_bank_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
        ];
    }
}
