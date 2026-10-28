<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Material sale: we buy the material from the quarry and sell it delivered. Transport: the
 * customer's own material, we only carry it.
 */
enum ShipmentKind: string
{
    use HasLabel;

    case MaterialSale = 'material_sale';
    case Transport = 'transport';

    public function label(): string
    {
        return match ($this) {
            self::MaterialSale => 'Malzeme satışlı',
            self::Transport => 'Sadece nakliye',
        };
    }
}
