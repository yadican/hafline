<?php

namespace App\Models;

use App\Enums\Module;
use App\Enums\ShipmentKind;
use App\Services\Ledger\LedgerSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One transport trip. party_id is the carrier: the plate's owner on the shipment date. The quarry
 * firm, the customer and the amounts are copied onto the shipment so later card edits do not
 * rewrite what was posted.
 */
#[Fillable([
    'kind',
    'shipped_on',
    'vehicle_id',
    'party_id',
    'quarry_id',
    'quarry_party_id',
    'material_id',
    'delivery_address_id',
    'customer_party_id',
    'net_kg',
    'quantity',
    'sale_price',
    'carrier_price',
    'quarry_price',
    'vat_rate',
    'withholding_rate',
    'carrier_vat_rate',
    'carrier_withholding_rate',
    'sale_amount',
    'vat_amount',
    'withholding_amount',
    'customer_amount',
    'carrier_amount',
    'carrier_vat_amount',
    'carrier_withholding_amount',
    'carrier_net_amount',
    'quarry_amount',
    'commission_party_id',
    'commission_amount',
    'waybill_no',
    'scale_ticket_no',
    'quarry_invoice_no',
    'description',
    'created_by',
])]
class Shipment extends Model implements LedgerSource
{
    use HasUuids, SoftDeletes;

    /**
     * VAT rates offered on a shipment.
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
     * Sale before VAT less what the carrier, the quarry and the commission agent earn.
     */
    public function profit(): float
    {
        return round((float) $this->sale_amount - (float) $this->carrier_amount - (float) $this->quarry_amount - (float) $this->commission_amount, 2);
    }

    public static function formatQuantity(float|string|null $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 3, ',', '.'), '0'), ',');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * The carrier: owner of the plate on the shipment date.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Quarry, $this>
     */
    public function quarry(): BelongsTo
    {
        return $this->belongsTo(Quarry::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function quarryParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'quarry_party_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
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
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_party_id')->withTrashed();
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
            'kind' => ShipmentKind::class,
            'shipped_on' => 'date',
            'net_kg' => 'integer',
            'quantity' => 'decimal:3',
            'sale_price' => 'decimal:4',
            'carrier_price' => 'decimal:4',
            'quarry_price' => 'decimal:4',
            'vat_rate' => 'decimal:2',
            'withholding_rate' => 'decimal:2',
            'carrier_vat_rate' => 'decimal:2',
            'carrier_withholding_rate' => 'decimal:2',
            'sale_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'withholding_amount' => 'decimal:2',
            'customer_amount' => 'decimal:2',
            'carrier_amount' => 'decimal:2',
            'carrier_vat_amount' => 'decimal:2',
            'carrier_withholding_amount' => 'decimal:2',
            'carrier_net_amount' => 'decimal:2',
            'quarry_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
        ];
    }
}
