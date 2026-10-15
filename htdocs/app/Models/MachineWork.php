<?php

namespace App\Models;

use App\Enums\Shift;
use Database\Factories\MachineWorkFactory;
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
    'site_id',
    'operator_id',
    'operator_rate',
    'shift',
    'hours',
    'hourly_rate',
    'worked_on',
    'created_by',
])]
class MachineWork extends Model
{
    /** @use HasFactory<MachineWorkFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * "4,5" rather than "4,50"; whole hours drop the decimals.
     */
    public static function formatHours(float|string|null $hours): string
    {
        return rtrim(rtrim(number_format((float) $hours, 2, ',', '.'), '0'), ',');
    }

    /**
     * Same as the stored `amount` column, available before the row is re-read.
     */
    public function total(): string
    {
        return number_format(round((float) $this->hours * (float) $this->hourly_rate, 2), 2, '.', '');
    }

    /**
     * What the operator earns from this ticket; null when the operator is not paid per ticket.
     */
    public function operatorTotal(): ?string
    {
        if ($this->operator_id === null || $this->operator_rate === null) {
            return null;
        }

        return number_format(round((float) $this->hours * (float) $this->operator_rate, 2), 2, '.', '');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * The machine's owner on the work date; the earnings are posted to this party.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * A party with the machine operator role.
     *
     * @return BelongsTo<Party, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'operator_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'operator_rate' => 'decimal:2',
            'operator_amount' => 'decimal:2',
            'shift' => Shift::class,
            'worked_on' => 'date',
        ];
    }
}
