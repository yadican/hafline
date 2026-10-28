<?php

namespace App\Models;

use App\Enums\Module;
use App\Services\Ledger\LedgerSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Virman: the debit party's balance goes up and the credit party's goes down by the same amount.
 */
#[Fillable([
    'debit_party_id',
    'credit_party_id',
    'occurred_on',
    'amount',
    'description',
    'created_by',
])]
class PartyTransfer extends Model implements LedgerSource
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
     * @return BelongsTo<Party, $this>
     */
    public function debitParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'debit_party_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function creditParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'credit_party_id')->withTrashed();
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
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
        ];
    }
}
