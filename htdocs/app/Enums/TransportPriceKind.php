<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Quarry: what the quarry charges us per unit of a material. Sale: what a delivery address is
 * charged per unit of a material. Freight: what the carrier earns per unit from a quarry to an address.
 */
enum TransportPriceKind: string
{
    use HasLabel;

    case Quarry = 'quarry';
    case Sale = 'sale';
    case Freight = 'freight';

    public function label(): string
    {
        return match ($this) {
            self::Quarry => 'Ocak alış fiyatı',
            self::Sale => 'Müşteri satış fiyatı',
            self::Freight => 'Nakliyeci fiyatı',
        };
    }
}
