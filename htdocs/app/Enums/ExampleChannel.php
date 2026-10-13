<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ExampleChannel: string
{
    use HasLabel;

    case Panel = 'panel';
    case Field = 'field';
    case Report = 'report';

    public function label(): string
    {
        return match ($this) {
            self::Panel => 'Panel',
            self::Field => 'Saha',
            self::Report => 'Rapor',
        };
    }
}
