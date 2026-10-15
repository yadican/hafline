<?php

namespace App\Models;

use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'party_id',
    'role',
    'site_id',
    'occurred_on',
    'debit',
    'credit',
    'payment_method',
    'cash_category_id',
    'document_no',
    'description',
    'fuel_station_id',
    'liters',
    'unit_price',
    'vehicle_id',
    'owner_party_id',
    'created_by',
])]
class CompanyTransaction extends Model
{
    use HasUuids, SoftDeletes;

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
            'role' => PartyRole::class,
            'occurred_on' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'liters' => 'decimal:2',
            'unit_price' => 'decimal:4',
        ];
    }
}
