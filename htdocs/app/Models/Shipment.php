<?php

namespace App\Models;

use App\Enums\Module;
use App\Services\Ledger\LedgerSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One transport trip. party_id is the carrier: the plate's owner on the trip date. Prices are per
 * ton and the weight is the scale's net kg, so each side's base is net_kg / 1000 × price. The
 * driver's fee is paid to the carrier as a separate item on the plate.
 */
#[Fillable([
    'shipped_on',
    'party_id',
    'vehicle_id',
    'driver_name',
    'driver_amount',
    'waybill_no',
    'loading_place',
    'scale_ticket_no',
    'net_kg',
    'customer_party_id',
    'delivery_address_id',
    'carrier_price',
    'carrier_vat_rate',
    'carrier_withholding_rate',
    'carrier_amount',
    'carrier_vat_amount',
    'carrier_withholding_amount',
    'carrier_net_amount',
    'carrier_note',
    'sale_price',
    'vat_rate',
    'withholding_rate',
    'sale_amount',
    'vat_amount',
    'withholding_amount',
    'customer_amount',
    'customer_note',
    'commission_party_id',
    'commission_amount',
    'commission_note',
    'created_by',
])]
class Shipment extends Model implements LedgerSource
{
    use HasUuids, SoftDeletes;

    /**
     * VAT rates offered on a trip.
     */
    public const VAT_RATES = ['0' => 'KDV yok', '1' => '%1', '10' => '%10', '20' => '%20'];

    public function ledgerModule(): Module
    {
        return Module::Transport;
    }

    public function ledgerSiteId(): ?string
    {
        return null;
    }

    /**
     * Customer base less the carrier base, the driver's trip fee and the referrer, VAT excluded.
     */
    public function profit(): float
    {
        return round((float) $this->sale_amount - (float) $this->carrier_amount - (float) $this->driver_amount - (float) $this->commission_amount, 2);
    }

    public static function formatKg(int|string|null $kg): string
    {
        return number_format((int) $kg, 0, ',', '.');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * The carrier (nakliyeci).
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
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_party_id')->withTrashed();
    }

    /**
     * @return BelongsTo<DeliveryAddress, $this>
     */
    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(DeliveryAddress::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function commissionParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'commission_party_id')->withTrashed();
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
            'shipped_on' => 'date',
            'net_kg' => 'integer',
            'carrier_price' => 'decimal:4',
            'carrier_vat_rate' => 'decimal:2',
            'carrier_withholding_rate' => 'decimal:2',
            'carrier_amount' => 'decimal:2',
            'carrier_vat_amount' => 'decimal:2',
            'carrier_withholding_amount' => 'decimal:2',
            'carrier_net_amount' => 'decimal:2',
            'sale_price' => 'decimal:4',
            'vat_rate' => 'decimal:2',
            'withholding_rate' => 'decimal:2',
            'sale_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'withholding_amount' => 'decimal:2',
            'customer_amount' => 'decimal:2',
            'driver_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
        ];
    }
}
