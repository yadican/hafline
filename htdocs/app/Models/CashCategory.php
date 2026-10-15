<?php

namespace App\Models;

use App\Enums\CashDirection;
use Database\Factories\CashCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'direction', 'active'])]
class CashCategory extends Model
{
    /** @use HasFactory<CashCategoryFactory> */
    use HasFactory, HasUuids, SoftDeletes;

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
