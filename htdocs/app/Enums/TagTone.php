<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum TagTone: string
{
    use HasLabel;

    case Neutral = 'neutral';
    case Green = 'green';
    case Red = 'red';
    case Blue = 'blue';
    case Orange = 'orange';

    public function label(): string
    {
        return match ($this) {
            self::Neutral => 'Gri',
            self::Green => 'Yeşil',
            self::Red => 'Kırmızı',
            self::Blue => 'Mavi',
            self::Orange => 'Turuncu',
        };
    }
}
