<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vehicle_id', 'hourly_rate', 'started_on', 'ended_on'])]
class VehicleRate extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeCovering(Builder $query, CarbonInterface $date): void
    {
        $day = $date->toDateString();

        $query->whereDate('started_on', '<=', $day)
            ->where(fn (Builder $query) => $query->whereNull('ended_on')->orWhereDate('ended_on', '>=', $day));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }
}
