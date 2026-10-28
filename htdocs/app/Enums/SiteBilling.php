<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * How a site is charged: every haul debits it at the route's site price, or a progress bill does.
 */
enum SiteBilling: string
{
    use HasLabel;

    case PerTrip = 'per_trip';
    case Progress = 'progress';

    public function label(): string
    {
        return match ($this) {
            self::PerTrip => 'Sefer başına',
            self::Progress => 'Hakediş',
        };
    }
}
