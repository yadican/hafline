<?php

namespace App\Models;

use App\Enums\Module;
use App\Enums\Shift;
use App\Services\Ledger\LedgerSource;
use Database\Factories\HaulFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'ticket_no',
    'vehicle_id',
    'party_id',
    'route_id',
    'site_id',
    'dump_site_id',
    'dump_party_id',
    'driver_name',
    'tonnage',
    'price',
    'dump_price',
    'site_price',
    'site_party_id',
    'shift',
    'hauled_at',
    'created_by',
    'field_haul_session_id',
])]
class Haul extends Model implements LedgerSource
{
    /** @use HasFactory<HaulFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public function ledgerModule(): Module
    {
        return Module::Excavation;
    }

    public function ledgerSiteId(): ?string
    {
        return $this->site_id;
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * The vehicle's owner on the haul date; the haul's earnings are posted to this party.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function dumpParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'dump_party_id')->withTrashed();
    }

    /**
     * @return MorphMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }

    /**
     * @return BelongsTo<HaulRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(HaulRoute::class, 'route_id')->withTrashed();
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<FieldHaulSession, $this>
     */
    public function fieldSession(): BelongsTo
    {
        return $this->belongsTo(FieldHaulSession::class, 'field_haul_session_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tonnage' => 'integer',
            'price' => 'decimal:2',
            'dump_price' => 'decimal:2',
            'site_price' => 'decimal:2',
            'shift' => Shift::class,
            'hauled_at' => 'datetime',
        ];
    }
}
