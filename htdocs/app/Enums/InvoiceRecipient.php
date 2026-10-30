<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Who a sale invoice is billed to: a cari directly, or a plate (owner that day, cari line on the plate).
 */
enum InvoiceRecipient: string
{
    use HasLabel;

    case Party = 'party';
    case Plate = 'plate';

    public function label(): string
    {
        return match ($this) {
            self::Party => 'Cari',
            self::Plate => 'Plaka',
        };
    }
}
