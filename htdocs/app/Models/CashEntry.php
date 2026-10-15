<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'cash_category_id',
    'bank_id',
    'debit',
    'credit',
    'occurred_on',
    'description',
    'created_by',
])]
class CashEntry extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<CashCategory, $this>
     */
    public function cashCategory(): BelongsTo
    {
        return $this->belongsTo(CashCategory::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class)->withTrashed();
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
        ];
    }
}
