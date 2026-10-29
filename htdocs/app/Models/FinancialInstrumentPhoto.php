<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A picture of a cheque or note, stored on the data disk.
 */
#[Fillable([
    'financial_instrument_id',
    'path',
    'created_by',
])]
class FinancialInstrumentPhoto extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<FinancialInstrument, $this>
     */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(FinancialInstrument::class, 'financial_instrument_id');
    }
}
