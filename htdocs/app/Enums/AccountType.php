<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Where the company keeps money: a cash box, a bank account, a POS terminal or a credit card.
 */
enum AccountType: string
{
    use HasLabel;

    case Cash = 'cash';
    case Bank = 'bank';
    case Pos = 'pos';
    case CreditCard = 'credit_card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Kasa',
            self::Bank => 'Banka',
            self::Pos => 'POS',
            self::CreditCard => 'Kredi kartı',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cash => 'fa-solid fa-cash-register',
            self::Bank => 'fa-solid fa-building-columns',
            self::Pos => 'fa-solid fa-mobile-screen-button',
            self::CreditCard => 'fa-solid fa-credit-card',
        };
    }

    /**
     * Bank-side details (branch, IBAN, representative) only mean something for these.
     */
    public function hasBankDetails(): bool
    {
        return $this !== self::Cash;
    }
}
