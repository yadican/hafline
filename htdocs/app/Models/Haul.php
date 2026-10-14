<?php

namespace App\Models;

use App\Enums\Shift;
use Database\Factories\HaulFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'ticket_no',
    'vehicle_id',
    'owner_id',
    'route_id',
    'site_id',
    'dump_site_id',
    'driver_name',
    'tonnage',
    'price',
    'dump_price',
    'shift',
    'hauled_at',
    'created_by',
])]
class Haul extends Model
{
    /** @use HasFactory<HaulFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<VehicleOwner, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(VehicleOwner::class, 'owner_id')->withTrashed();
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tonnage' => 'integer',
            'price' => 'decimal:2',
            'dump_price' => 'decimal:2',
            'shift' => Shift::class,
            'hauled_at' => 'datetime',
        ];
    }
}
