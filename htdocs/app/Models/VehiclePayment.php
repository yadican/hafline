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
    'vehicle_id',
    'party_id',
    'payment_method',
    'amount',
    'occurred_on',
    'description',
    'company_transaction_id',
    'created_by',
])]
class VehiclePayment extends Model implements LedgerSource
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
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
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
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
            'payment_method' => PaymentMethod::class,
        ];
    }
}
