<?php

namespace App\Models;

use App\Enums\TransportPriceKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Where a customer takes delivery; the customer is billed for every shipment sent here.
 */
#[Fillable(['party_id', 'name', 'active'])]
class DeliveryAddress extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * @return HasMany<TransportPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(TransportPrice::class)->where('kind', TransportPriceKind::Sale);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
