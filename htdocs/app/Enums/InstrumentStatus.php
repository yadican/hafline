<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Where a cheque or note stands. Received ones wait in the portfolio until collected, endorsed or
 * returned unpaid; given ones wait until paid from our account or returned unpaid.
 */
enum InstrumentStatus: string
{
    use HasLabel;

    case Portfolio = 'portfolio';
    case InCollection = 'in_collection';
    case Collected = 'collected';
    case Endorsed = 'endorsed';
    case Bounced = 'bounced';
    case Issued = 'issued';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Portfolio => 'Portföyde',
            self::InCollection => 'Tahsilde',
            self::Collected => 'Tahsil edildi',
            self::Endorsed => 'Ciro edildi',
            self::Bounced => 'Karşılıksız',
            self::Issued => 'Ödenecek',
            self::Paid => 'Ödendi',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Portfolio => 'fa-solid fa-folder-open',
            self::InCollection => 'fa-solid fa-building-columns',
            self::Collected => 'fa-solid fa-circle-check',
            self::Endorsed => 'fa-solid fa-share',
            self::Bounced => 'fa-solid fa-ban',
            self::Issued => 'fa-solid fa-hourglass-half',
            self::Paid => 'fa-solid fa-circle-check',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Collected, self::Paid => 'panel-badge-gelir',
            self::Bounced => 'panel-badge-gider',
            self::Endorsed => 'panel-badge-pasif',
            default => 'panel-badge-ikisi',
        };
    }

    /**
     * Still waiting for its due date: counts in the open portfolio totals and the overdue warning.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Portfolio, self::InCollection, self::Issued], true);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isOpen()));
    }

    /**
     * @return list<InstrumentMovementType>
     */
    public function allows(): array
    {
        return match ($this) {
            self::Portfolio => [InstrumentMovementType::Deposit, InstrumentMovementType::Collect, InstrumentMovementType::Endorse, InstrumentMovementType::Bounce],
            self::InCollection => [InstrumentMovementType::Collect, InstrumentMovementType::Bounce],
            self::Endorsed => [InstrumentMovementType::Bounce],
            self::Issued => [InstrumentMovementType::Pay, InstrumentMovementType::Bounce],
            default => [],
        };
    }
}
