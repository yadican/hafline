<?php

namespace App\Models;

use App\Enums\InstrumentMovementType;
use App\Enums\Module;
use App\Services\Ledger\LedgerSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One step of a cheque or note. Endorsing and a return unpaid post to the cari; collecting and
 * paying move the amount in or out of the named account.
 */
#[Fillable([
    'financial_instrument_id',
    'type',
    'occurred_on',
    'bank_id',
    'party_id',
    'description',
    'created_by',
])]
class FinancialInstrumentMovement extends Model implements LedgerSource
{
    use HasUuids, SoftDeletes;

    public function ledgerModule(): Module
    {
        return Module::General;
    }

    public function ledgerSiteId(): ?string
    {
        return null;
    }

    /**
     * @return BelongsTo<FinancialInstrument, $this>
     */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(FinancialInstrument::class, 'financial_instrument_id');
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class)->withTrashed();
    }

    /**
     * Endorsing: the party the cheque was given to. A return of an endorsed cheque: the party that gave it back.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * @return MorphMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InstrumentMovementType::class,
            'occurred_on' => 'date',
        ];
    }
}
