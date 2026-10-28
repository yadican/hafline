<?php

namespace App\Models;

use App\Enums\Module;
use App\Services\Ledger\LedgerSource;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vehicle_id', 'party_id', 'started_on', 'ended_on', 'carried_from_party_id', 'carried_balance'])]
class VehicleOwnership extends Model implements LedgerSource
{
    use HasUuids;

    public function ledgerModule(): Module
    {
        return Module::General;
    }

    public function ledgerSiteId(): ?string
    {
        return null;
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function carriedFrom(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'carried_from_party_id')->withTrashed();
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
            'started_on' => 'date',
            'ended_on' => 'date',
            'carried_balance' => 'decimal:2',
        ];
    }
}
