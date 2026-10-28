<?php

namespace App\Models;

use App\Enums\Module;
use App\Services\Ledger\LedgerSource;
use Database\Factories\EmployeeSalaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'employee_id',
    'period',
    'base_salary',
    'worked_days',
    'earned_salary',
])]
class EmployeeSalary extends Model implements LedgerSource
{
    /** @use HasFactory<EmployeeSalaryFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public function ledgerModule(): Module
    {
        return Module::General;
    }

    public function ledgerSiteId(): ?string
    {
        return null;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => 'date',
            'base_salary' => 'decimal:2',
            'worked_days' => 'integer',
            'earned_salary' => 'decimal:2',
        ];
    }
}
