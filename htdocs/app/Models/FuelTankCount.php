<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical count of our tank; difference (counted minus the book stock of that day) corrects
 * the stock. It is our own loss or gain, so nothing is written to a cari.
 */
#[Fillable(['fuel_station_id', 'counted_on', 'quantity', 'difference', 'description', 'created_by'])]
class FuelTankCount extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<FuelStation, $this>
     */
    public function fuelStation(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'counted_on' => 'date',
            'quantity' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }
}
