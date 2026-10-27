<?php

namespace App\Enums;

/**
 * Ticket types numbered by the server; the value is the ticket number prefix.
 */
enum TicketKind: string
{
    case Haul = 'T';
    case MachineWork = 'M';
    case FuelReceipt = 'Y';

    public function label(): string
    {
        return match ($this) {
            self::Haul => 'Taşıma fişi',
            self::MachineWork => 'Makine fişi',
            self::FuelReceipt => 'Yakıt fişi',
        };
    }

    public function table(): string
    {
        return match ($this) {
            self::Haul => 'hauls',
            self::MachineWork => 'machine_works',
            self::FuelReceipt => 'fuel_receipts',
        };
    }
}
