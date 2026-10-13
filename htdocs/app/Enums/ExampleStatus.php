<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ExampleStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Active => 'Aktif',
            self::Closed => 'Kapalı',
        };
    }
}
