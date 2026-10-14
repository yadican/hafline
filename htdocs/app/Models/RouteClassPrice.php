<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['route_id', 'vehicle_class_id', 'price'])]
class RouteClassPrice extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<VehicleClass, $this>
     */
    public function vehicleClass(): BelongsTo
    {
        return $this->belongsTo(VehicleClass::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }
}
