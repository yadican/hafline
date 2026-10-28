<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Fixed on purpose: reports and side effects (cash balance, cheque tracking, bank accounts) key off these values.
 */
enum PaymentMethod: string
{
    use HasLabel;

    case Cash = 'cash';
    case Cheque = 'cheque';
    case PromissoryNote = 'promissory_note';
    case BankTransfer = 'bank_transfer';
    case CreditCard = 'credit_card';
    case Offset = 'offset';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Nakit',
            self::Cheque => 'Çek',
            self::PromissoryNote => 'Senet',
            self::BankTransfer => 'Havale / EFT',
            self::CreditCard => 'Kredi kartı',
            self::Offset => 'Mahsup',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cash => 'fa-solid fa-money-bill-wave',
            self::Cheque => 'fa-solid fa-money-check',
            self::PromissoryNote => 'fa-solid fa-file-signature',
            self::BankTransfer => 'fa-solid fa-building-columns',
            self::CreditCard => 'fa-solid fa-credit-card',
            self::Offset => 'fa-solid fa-right-left',
        };
    }

    public function movesCash(): bool
    {
        return $this === self::Cash;
    }

    /**
     * Cheques and promissory notes are followed up by due date in the instrument register.
     */
    public function hasDueDate(): bool
    {
        return in_array($this, [self::Cheque, self::PromissoryNote], true);
    }

    public function needsBank(): bool
    {
        return in_array($this, [self::BankTransfer, self::CreditCard], true);
    }

    /**
     * Account kinds the money of this method goes through; empty when no account moves
     * (cheque and note wait in the portfolio, offset moves no money).
     *
     * @return list<AccountType>
     */
    public function accountTypes(): array
    {
        return match ($this) {
            self::Cash => [AccountType::Cash],
            self::BankTransfer => [AccountType::Bank],
            self::CreditCard => [AccountType::Pos, AccountType::CreditCard],
            default => [],
        };
    }
}
