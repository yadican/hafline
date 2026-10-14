<?php

namespace App\Models;

use Database\Factories\DumpSiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'active'])]
class DumpSite extends Model
{
    /** @use HasFactory<DumpSiteFactory> */
    use HasFactory;

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
