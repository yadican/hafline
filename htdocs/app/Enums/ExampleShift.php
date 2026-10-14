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

    public function icon(): string
    {
        return match ($this) {
            self::Day => 'fa-solid fa-sun',
            self::Night => 'fa-solid fa-moon',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Day => 'panel-badge-gunduz',
            self::Night => 'panel-badge-gece',
        };
    }
}
