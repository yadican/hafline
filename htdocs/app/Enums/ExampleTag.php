<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ExampleTag: string
{
    use HasLabel;

    case Cash = 'cash';
    case Trip = 'trip';
    case Site = 'site';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Nakit',
            self::Trip => 'Sefer',
            self::Site => 'Şantiye',
        };
    }
}
