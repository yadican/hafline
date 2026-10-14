<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PaymentKind: string
{
    use HasLabel;

    case Cash = 'cash';
    case Cheque = 'cheque';
    case Bank = 'bank';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Nakit',
            self::Cheque => 'Çek',
            self::Bank => 'Banka',
            self::Other => 'Diğer',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cash => 'fa-solid fa-money-bill-wave',
            self::Cheque => 'fa-solid fa-money-check',
            self::Bank => 'fa-solid fa-building-columns',
            self::Other => 'fa-solid fa-ellipsis',
        };
    }
}
