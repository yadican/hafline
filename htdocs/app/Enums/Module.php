<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The line of business a cari movement belongs to. A party has one balance across all of them.
 */
enum Module: string
{
    use HasLabel;

    case Excavation = 'excavation';
    case Transport = 'transport';
    case Fuel = 'fuel';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Excavation => 'Hafriyat',
            self::Transport => 'Nakliye',
            self::Fuel => 'Yakıt',
            self::General => 'Genel',
        };
    }
}
