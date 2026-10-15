<?php

namespace App\Models;

use App\Enums\PartyRole;
use App\Enums\PartyType;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'type',
    'name',
    'phone',
    'mobile',
    'email',
    'address',
    'city_id',
    'district_id',
    'neighborhood_id',
    'notes',
    'active',
])]
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return HasOne<Person, $this>
     */
    public function person(): HasOne
    {
        return $this->hasOne(Person::class);
    }

    /**
     * @return HasOne<Company, $this>
     */
    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    /**
     * @return HasMany<PartyRoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(PartyRoleAssignment::class);
    }

    /**
     * @return HasMany<VehicleOwnership, $this>
     */
    public function ownerships(): HasMany
    {
        return $this->hasMany(VehicleOwnership::class);
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * @return HasMany<DumpSite, $this>
     */
    public function dumpSites(): HasMany
    {
        return $this->hasMany(DumpSite::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class)->withTrashed();
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Neighborhood, $this>
     */
    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class)->withTrashed();
    }

    /**
     * @return BelongsToMany<Site, $this>
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_parties')
            ->withPivot('role')
            ->withTimestamps()
            ->withTrashed()
            ->orderBy('name');
    }

    /**
     * @return HasMany<FuelStation, $this>
     */
    public function fuelStations(): HasMany
    {
        return $this->hasMany(FuelStation::class);
    }

    public function hasRole(PartyRole $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function assignRole(PartyRole $role): void
    {
        $this->roleAssignments()->firstOrCreate(['role' => $role]);
        $this->unsetRelation('roleAssignments');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeWithRole(Builder $query, PartyRole $role): void
    {
        $query->whereHas('roleAssignments', fn (Builder $query) => $query->where('role', $role));
    }

    /**
     * @return Attribute<list<PartyRole>, never>
     */
    protected function roles(): Attribute
    {
        return Attribute::get(fn () => $this->roleAssignments
            ->map(fn (PartyRoleAssignment $assignment) => $assignment->role)
            ->sortBy(fn (PartyRole $role) => array_search($role, PartyRole::cases(), true))
            ->values()
            ->all());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PartyType::class,
            'active' => 'boolean',
        ];
    }
}
