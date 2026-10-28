<?php

namespace App\Models;

use App\Enums\TransportPriceKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A unit price valid from a date until the next price for the same key starts.
 */
#[Fillable(['kind', 'quarry_id', 'delivery_address_id', 'material_id', 'price', 'valid_from'])]
class TransportPrice extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<Quarry, $this>
     */
    public function quarry(): BelongsTo
    {
        return $this->belongsTo(Quarry::class)->withTrashed();
    }

    /**
     * @return BelongsTo<DeliveryAddress, $this>
     */
    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(DeliveryAddress::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TransportPriceKind::class,
            'price' => 'decimal:4',
            'valid_from' => 'date',
        ];
    }
}
