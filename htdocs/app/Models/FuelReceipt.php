<?php

namespace App\Models;

use App\Enums\Shift;
use Database\Factories\FuelReceiptFactory;
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
    'fuel_station_id',
    'fuel_party_id',
    'site_id',
    'quantity',
    'unit_price',
    'issued_on',
    'km',
    'received_by',
    'shift',
    'haul_ticket_count',
    'created_by',
])]
class FuelReceipt extends Model
{
    /** @use HasFactory<FuelReceiptFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public static function formatQuantity(float|string|null $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 2, ',', '.'), '0'), ',');
    }

    /**
     * Same as the stored `amount` column, available before the row is re-read.
     */
    public function total(): string
    {
        return number_format(round((float) $this->quantity * (float) $this->unit_price, 2), 2, '.', '');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * The vehicle's owner on the receipt date; the fuel cost is debited to this party.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * @return BelongsTo<FuelStation, $this>
     */
    public function fuelStation(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class)->withTrashed();
    }

    /**
     * The station's firm, copied from the station so later station edits do not rewrite history.
     *
     * @return BelongsTo<Party, $this>
     */
    public function fuelParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'fuel_party_id')->withTrashed();
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
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:2',
            'issued_on' => 'date',
            'shift' => Shift::class,
            'km' => 'integer',
            'haul_ticket_count' => 'integer',
        ];
    }
}
