<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Quantity unit for a stock item card.
 */
enum StockItemUnit: string
{
    use HasLabel;

    case Piece = 'adet';
    case Liter = 'lt';
    case Kilogram = 'kg';

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
            self::Piece => 'Adet',
            self::Liter => 'Litre',
            self::Kilogram => 'Kilogram',
        };
    }
}
