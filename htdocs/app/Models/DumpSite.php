<?php

namespace App\Models;

use Database\Factories\DumpSiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'party_id', 'active'])]
class DumpSite extends Model
{
    /** @use HasFactory<DumpSiteFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The operator whose account is credited with dump fees.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * @return HasMany<HaulRoute, $this>
     */
    public function routes(): HasMany
    {
        return $this->hasMany(HaulRoute::class);
    }

    /**
     * @return HasMany<DumpSiteClassPrice, $this>
     */
    public function classPrices(): HasMany
    {
        return $this->hasMany(DumpSiteClassPrice::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
