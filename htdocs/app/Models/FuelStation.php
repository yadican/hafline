<?php

namespace App\Models;

use App\Enums\FuelProduct;
use App\Enums\FuelSourceType;
use Database\Factories\FuelStationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A fuel source: a station whose firm is credited per receipt at the purchase price, or our own
 * tank with no firm, filled from suppliers and kept as stock. unit_price is the sale price charged
 * to the vehicle owner.
 */
#[Fillable(['name', 'type', 'product', 'party_id', 'purchase_price', 'unit_price', 'active'])]
class FuelStation extends Model
{
    /** @use HasFactory<FuelStationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public function isTank(): bool
    {
        return $this->type === FuelSourceType::Tank;
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * @return BelongsToMany<Site, $this>
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'fuel_station_sites')->withTrashed()->orderBy('name');
    }

    /**
     * @return HasMany<FuelTankFill, $this>
     */
    public function fills(): HasMany
    {
        return $this->hasMany(FuelTankFill::class);
    }

    /**
     * @return HasMany<FuelTankCount, $this>
     */
    public function counts(): HasMany
    {
        return $this->hasMany(FuelTankCount::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FuelSourceType::class,
            'product' => FuelProduct::class,
            'purchase_price' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'active' => 'boolean',
        ];
    }
}
