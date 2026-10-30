<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Purchase: a supplier billed us (the party is credited). Sale: we billed a customer (the party is
 * debited). PurchaseReturn: we return goods to a supplier (stock out at purchase cost, party debited);
 * it may optionally reduce an open purchase invoice's remaining. SaleReturn: customer returns goods
 * (stock in at the sale's frozen unit_cost, party credited); it may optionally reduce an open sale.
 */
enum InvoiceDirection: string
{
    use HasLabel;

    case Purchase = 'purchase';
    case Sale = 'sale';
    case PurchaseReturn = 'purchase_return';
    case SaleReturn = 'sale_return';

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
            self::Purchase => 'Alış',
            self::Sale => 'Satış',
            self::PurchaseReturn => 'Alış iadesi',
            self::SaleReturn => 'Satış iadesi',
        };
    }

    /**
     * What settles the invoice: we pay a supplier, a customer pays us.
     */
    public function paymentLabel(): string
    {
        return match ($this) {
            self::Purchase, self::SaleReturn => 'Ödeme',
            self::Sale, self::PurchaseReturn => 'Tahsilat',
        };
    }

    /**
     * The button that settles the invoice from its detail screen.
     */
    public function payAction(): string
    {
        return match ($this) {
            self::Purchase, self::SaleReturn => 'Öde',
            self::Sale, self::PurchaseReturn => 'Tahsil et',
        };
    }

    /**
     * Cash / cheque settlement from the invoice screen (pay / collect). Returns are settled by
     * linking to an open invoice or left open on the party; they are not paid at entry.
     */
    public function acceptsPayment(): bool
    {
        return match ($this) {
            self::Purchase, self::Sale => true,
            self::PurchaseReturn, self::SaleReturn => false,
        };
    }

    /**
     * Line categories: purchase and purchase return use expense, sale and sale return use income.
     */
    public function categoryDirection(): CashDirection
    {
        return match ($this) {
            self::Purchase, self::PurchaseReturn => CashDirection::Expense,
            self::Sale, self::SaleReturn => CashDirection::Income,
        };
    }

    /**
     * Party role assigned when the invoice is saved.
     */
    public function partyRole(): PartyRole
    {
        return match ($this) {
            self::Purchase, self::PurchaseReturn => PartyRole::Supplier,
            self::Sale, self::SaleReturn => PartyRole::Customer,
        };
    }

    /**
     * Whether this direction is a return that may link via returns_invoice_id.
     */
    public function isReturn(): bool
    {
        return match ($this) {
            self::PurchaseReturn, self::SaleReturn => true,
            default => false,
        };
    }

    /**
     * Open invoice direction a return may reduce (null when not a return).
     */
    public function returnTarget(): ?self
    {
        return match ($this) {
            self::PurchaseReturn => self::Purchase,
            self::SaleReturn => self::Sale,
            default => null,
        };
    }

    /**
     * Cari side of the invoice total (payments are separate).
     */
    public function creditsParty(): bool
    {
        return match ($this) {
            self::Purchase, self::SaleReturn => true,
            default => false,
        };
    }
}
