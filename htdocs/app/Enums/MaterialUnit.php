<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum MaterialUnit: string
{
    use HasLabel;

    case Ton = 'ton';
    case CubicMetre = 'm3';

    public function label(): string
    {
        return match ($this) {
            self::Ton => 'ton',
            self::CubicMetre => 'm³',
        };
    }
}
