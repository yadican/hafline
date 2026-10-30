<?php

namespace App\Enums;

/**
 * Derived from the payments matched to an invoice. It is picked by hand only when the invoice is
 * entered, and then only to write the payment along with it (K3).
 */
enum InvoicePaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    /**
     * The choices on the new invoice form.
     *
     * @return list<self>
     */
    public static function atEntry(): array
    {
        return [self::Unpaid, self::Partial, self::Paid];
    }

    /**
     * @return array<string, array<string, string>> value => [direction => label]
     */
    public static function entryOptions(): array
    {
        return collect(self::atEntry())
            ->mapWithKeys(fn (self $status): array => [$status->value => collect(InvoiceDirection::cases())
                ->mapWithKeys(fn (InvoiceDirection $direction): array => [$direction->value => $status->label($direction)])
                ->all()])
            ->all();
    }

    public static function for(float $total, float $paid): self
    {
        return match (true) {
            $paid <= 0 => self::Unpaid,
            $paid < $total => self::Partial,
            default => self::Paid,
        };
    }

    public function label(InvoiceDirection $direction): string
    {
        return match ($direction) {
            InvoiceDirection::Purchase, InvoiceDirection::SaleReturn => match ($this) {
                self::Unpaid => 'Ödenmedi',
                self::Partial => 'Kısmen ödendi',
                self::Paid => 'Ödendi',
            },
            InvoiceDirection::Sale, InvoiceDirection::PurchaseReturn => match ($this) {
                self::Unpaid => 'Tahsil edilmedi',
                self::Partial => 'Kısmen tahsil edildi',
                self::Paid => 'Tahsil edildi',
            },
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unpaid => 'gider',
            self::Partial => 'ikisi',
            self::Paid => 'gelir',
        };
    }
}
