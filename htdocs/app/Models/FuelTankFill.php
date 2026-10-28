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
 * Fuel bought into our own tank; the supplier is credited for it.
 */
#[Fillable([
    'fuel_station_id',
    'party_id',
    'filled_on',
    'quantity',
    'unit_price',
    'document_no',
    'description',
    'created_by',
])]
class FuelTankFill extends Model implements LedgerSource
{
    use HasUuids, SoftDeletes;

    public function ledgerModule(): Module
    {
        return Module::Fuel;
    }

    public function ledgerSiteId(): ?string
    {
        return null;
    }

    public function total(): string
    {
        return number_format(round((float) $this->quantity * (float) $this->unit_price, 2), 2, '.', '');
    }

    /**
     * @return BelongsTo<FuelStation, $this>
     */
    public function fuelStation(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class)->withTrashed();
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
            'filled_on' => 'date',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }
}
