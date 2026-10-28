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
 * A quarry or any loading place. Its cari is the quarry firm we buy material from; a plain loading
 * place used only for transport has none.
 */
#[Fillable(['name', 'party_id', 'active'])]
class Quarry extends Model
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
     * Quarry purchase prices per material and carrier prices to delivery addresses.
     *
     * @return HasMany<TransportPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(TransportPrice::class)->whereIn('kind', [TransportPriceKind::Quarry, TransportPriceKind::Freight]);
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
