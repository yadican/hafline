<?php

namespace App\Models;

use App\Enums\EmployeeOccupation;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'party_id',
    'started_on',
    'occupation',
    'salary',
    'bank_account_no',
    'active',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * @return HasMany<EmployeePeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(EmployeePeriod::class)->orderByDesc('started_on');
    }

    /**
     * @return HasMany<EmployeeSalary, $this>
     */
    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    /**
     * @return HasMany<EmployeePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(EmployeePayment::class);
    }

    /**
     * @return HasMany<EmployeeBonus, $this>
     */
    public function bonuses(): HasMany
    {
        return $this->hasMany(EmployeeBonus::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => $this->party?->name ?? '');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'occupation' => EmployeeOccupation::class,
            'salary' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
