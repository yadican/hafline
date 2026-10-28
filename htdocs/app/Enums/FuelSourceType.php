<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Who owns the fuel: a station's firm (credited per receipt) or us (kept as tank stock).
 */
enum FuelSourceType: string
{
    use HasLabel;

    case Station = 'station';
    case Tank = 'tank';

    public function label(): string
    {
        return match ($this) {
            self::Station => 'İstasyon',
            self::Tank => 'Kendi depomuz',
        };
    }
}
