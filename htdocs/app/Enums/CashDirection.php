<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Which side of the cash box a category can be picked for.
 */
enum CashDirection: string
{
    use HasLabel;

    case Expense = 'expense';
    case Income = 'income';
    case Both = 'both';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Gider',
            self::Income => 'Gelir',
            self::Both => 'Gider ve gelir',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Expense => 'fa-solid fa-arrow-up',
            self::Income => 'fa-solid fa-arrow-down',
            self::Both => 'fa-solid fa-arrows-up-down',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Expense => 'panel-badge-gider',
            self::Income => 'panel-badge-gelir',
            self::Both => 'panel-badge-ikisi',
        };
    }
}
