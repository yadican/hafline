<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum FuelProduct: string
{
    use HasLabel;

    case Diesel = 'diesel';
    case AdBlue = 'adblue';

    public function label(): string
    {
        return match ($this) {
            self::Diesel => 'Mazot',
            self::AdBlue => 'AdBlue',
        };
    }
}
