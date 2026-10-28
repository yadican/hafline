<?php

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\BankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A money account of the company: cash box, bank account, POS terminal or credit card.
 */
#[Fillable([
    'type',
    'name',
    'branch',
    'representative',
    'phone',
    'account_no',
    'iban',
    'opening_balance',
    'opened_on',
    'active',
])]
class Bank extends Model
{
    /** @use HasFactory<BankFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'opening_balance' => 'decimal:2',
            'opened_on' => 'date',
            'active' => 'boolean',
        ];
    }
}
