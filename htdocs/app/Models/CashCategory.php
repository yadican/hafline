<?php

namespace App\Models;

use App\Enums\CashDirection;
use Database\Factories\CashCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['parent_id', 'name', 'direction', 'active'])]
class CashCategory extends Model
{
    /** @use HasFactory<CashCategoryFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The group this item sits under (one level only: a group has no parent).
     *
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * "Grup › Kalem" for an item in a group, the plain name otherwise.
     */
    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }

    /**
     * Categories that can be picked for money going out (expense) or coming in (income).
     *
     * @param  Builder<self>  $query
     */
    public function scopeFor(Builder $query, CashDirection $direction): void
    {
        $query->whereIn('direction', array_unique([$direction->value, CashDirection::Both->value]));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => CashDirection::class,
            'active' => 'boolean',
        ];
    }
}
