<?php

namespace App\Models;

use App\Enums\Shift;
use Database\Factories\TicketHandoverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'vehicle_id',
    'route_id',
    'haul_count',
    'fuel_count',
    'description',
    'handed_on',
    'shift',
    'created_by',
])]
class TicketHandover extends Model
{
    /** @use HasFactory<TicketHandoverFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<HaulRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(HaulRoute::class, 'route_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'handed_on' => 'date',
            'haul_count' => 'integer',
            'fuel_count' => 'integer',
            'shift' => Shift::class,
        ];
    }
}
