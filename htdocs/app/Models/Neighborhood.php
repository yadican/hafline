<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['district_id', 'name', 'type', 'postal_code'])]
class Neighborhood extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class)->withTrashed();
    }

    /**
     * Villages carry a suffix so they are not confused with a neighborhood of the same name.
     *
     * @return Attribute<string, never>
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => $this->type === 'koy' ? $this->name.' (Köy)' : $this->name);
    }
}
