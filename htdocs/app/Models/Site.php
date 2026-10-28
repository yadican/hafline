<?php

namespace App\Models;

use App\Enums\SiteBilling;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'party_id', 'billing_mode', 'active'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The site's own cari: it owes us for the work and pays us.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * The cari each haul debits, when the site is billed per trip.
     */
    public function perTripPartyId(): ?string
    {
        return $this->billing_mode === SiteBilling::PerTrip ? $this->party_id : null;
    }

    /**
     * @return HasMany<HaulRoute, $this>
     */
    public function routes(): HasMany
    {
        return $this->hasMany(HaulRoute::class);
    }

    /**
     * One row per (party, role); a party linked with two roles appears twice.
     *
     * @return BelongsToMany<Party, $this>
     */
    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(Party::class, 'site_parties')
            ->withPivot('role')
            ->withTimestamps()
            ->withTrashed()
            ->orderBy('name');
    }

    /**
     * @return BelongsToMany<FuelStation, $this>
     */
    public function fuelStations(): BelongsToMany
    {
        return $this->belongsToMany(FuelStation::class, 'fuel_station_sites');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_mode' => SiteBilling::class,
            'active' => 'boolean',
        ];
    }
}
