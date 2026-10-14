<?php

namespace App\Models;

use Database\Factories\HaulRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['site_id', 'dump_site_id', 'price', 'bonus', 'active'])]
class HaulRoute extends Model
{
    /** @use HasFactory<HaulRouteFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public static function between(string $siteId, string $dumpSiteId): ?self
    {
        return self::query()
            ->where('site_id', $siteId)
            ->where('dump_site_id', $dumpSiteId)
            ->where('active', true)
            ->first();
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class)->withTrashed();
    }

    /**
     * @return BelongsTo<DumpSite, $this>
     */
    public function dumpSite(): BelongsTo
    {
        return $this->belongsTo(DumpSite::class)->withTrashed();
    }

    /**
     * @return Attribute<string, never>
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn () => $this->site?->name.' → '.$this->dumpSite?->name);
    }

    /**
     * @return HasMany<RouteClassPrice, $this>
     */
    public function classPrices(): HasMany
    {
        return $this->hasMany(RouteClassPrice::class, 'route_id');
    }

    /**
     * @return HasMany<RouteVehiclePrice, $this>
     */
    public function vehiclePrices(): HasMany
    {
        return $this->hasMany(RouteVehiclePrice::class, 'route_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'bonus' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
