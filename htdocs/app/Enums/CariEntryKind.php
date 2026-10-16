<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * One screen covers the legacy cash-menu entry actions that post money.
 * Ticket handovers, fuel/dump checks, fines and payroll stay on their own screens.
 */
enum CariEntryKind: string
{
    use HasLabel;

    case Supplier = 'supplier';
    case Customer = 'customer';
    case Fuel = 'fuel';
    case Market = 'market';
    case VehiclePayment = 'vehicle_payment';
    case VehicleReceivable = 'vehicle_receivable';
    case OwnerPayment = 'owner_payment';
    case DumpPayment = 'dump_payment';
    case Site = 'site';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Supplier => 'Satıcı Cari',
            self::Customer => 'Alıcı Cari',
            self::Fuel => 'Satıcı Petrol',
            self::Market => 'Piyasa',
            self::VehiclePayment => 'Plaka Ödeme',
            self::VehicleReceivable => 'Plaka Alacak',
            self::OwnerPayment => 'Araç Sahibi Ödemesi',
            self::DumpPayment => 'Döküm Yeri Ödemesi',
            self::Site => 'Şantiye Gelir / Gider',
            self::Cash => 'Kasa Nakit',
        };
    }

    public function partyRole(): ?PartyRole
    {
        return match ($this) {
            self::Supplier => PartyRole::Supplier,
            self::Customer => PartyRole::Customer,
            self::Fuel => PartyRole::Fuel,
            self::Market => PartyRole::Market,
            self::OwnerPayment => PartyRole::VehicleOwner,
            default => null,
        };
    }

    /**
     * choice: the form asks borç or alacak. Otherwise the direction is fixed.
     */
    public function directionMode(): string
    {
        return match ($this) {
            self::VehiclePayment, self::OwnerPayment, self::DumpPayment => 'debit',
            self::VehicleReceivable => 'credit',
            default => 'choice',
        };
    }

    public function postsLedger(): bool
    {
        return ! in_array($this, [self::Site, self::Cash], true);
    }
}
