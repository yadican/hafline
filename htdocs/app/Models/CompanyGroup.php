<?php

namespace App\Models;

use Database\Factories\CompanyGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Brings several company cari cards (e.g. an employer and its site companies) under one name.
 */
#[Fillable(['name', 'active'])]
class CompanyGroup extends Model
{
    /** @use HasFactory<CompanyGroupFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
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
