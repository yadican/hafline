<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Received: a party paid us with it (the party is credited). Given: we paid a party with it (the party is debited).
 */
enum InstrumentDirection: string
{
    use HasLabel;

    case Received = 'received';
    case Given = 'given';

    public static function fromLedgerSide(string $side): self
    {
        return $side === 'credit' ? self::Received : self::Given;
    }

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Alınan',
            self::Given => 'Verilen',
        };
    }

    public function initialStatus(): InstrumentStatus
    {
        return match ($this) {
            self::Received => InstrumentStatus::Portfolio,
            self::Given => InstrumentStatus::Issued,
        };
    }
}
