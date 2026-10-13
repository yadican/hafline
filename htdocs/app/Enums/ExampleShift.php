<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ExampleShift: string
{
    use HasLabel;

    case Day = 'day';
    case Night = 'night';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Gündüz',
            self::Night => 'Gece',
        };
    }
}
