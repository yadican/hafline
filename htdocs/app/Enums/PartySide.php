<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Groups cari roles by which way money usually flows, like a customer / supplier split.
 */
enum PartySide: string
{
    use HasLabel;

    case Receivable = 'receivable';
    case Payable = 'payable';

    public function label(): string
    {
        return match ($this) {
            self::Receivable => 'Müşteri tarafı (bize borçlanır)',
            self::Payable => 'Tedarikçi tarafı (biz borçlanırız)',
        };
    }

    /**
     * @return list<PartyRole>
     */
    public function roles(): array
    {
        return array_values(array_filter(
            PartyRole::cases(),
            fn (PartyRole $role) => in_array($this, $role->sides(), true),
        ));
    }
}
