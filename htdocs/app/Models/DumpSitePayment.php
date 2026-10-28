<?php

namespace App\Models;

use App\Enums\Module;
use App\Enums\PaymentMethod;
use App\Services\Ledger\LedgerSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'dump_site_id',
    'payment_method',
    'amount',
    'occurred_on',
    'cheque_no',
    'description',
    'created_by',
])]
class DumpSitePayment extends Model implements LedgerSource
{
    use HasUuids, SoftDeletes;

    public function ledgerModule(): Module
    {
        return Module::Excavation;
    }

    public function ledgerSiteId(): ?string
    {
        return null;
    }

    /**
     * @return BelongsTo<DumpSite, $this>
     */
    public function dumpSite(): BelongsTo
    {
        return $this->belongsTo(DumpSite::class)->withTrashed();
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
            'payment_method' => PaymentMethod::class,
        ];
    }
}
