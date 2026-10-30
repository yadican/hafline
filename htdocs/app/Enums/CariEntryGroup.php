<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Permission / menu buckets for cari entry kinds. Each kind belongs to exactly one group.
 */
enum CariEntryGroup: string
{
    use HasLabel;

    case FirmPayment = 'firm_payment';
    case VehiclePayment = 'vehicle_payment';
    case VehicleReceivable = 'vehicle_receivable';
    case OwnerPayment = 'owner_payment';
    case DumpPayment = 'dump_payment';
    case SiteBill = 'site_bill';
    case Site = 'site';
    case Cash = 'cash';
    case AccountTransfer = 'account_transfer';
    case Opening = 'opening';
    case PartyTransfer = 'party_transfer';

    public function label(): string
    {
        return match ($this) {
            self::FirmPayment => 'Firma ödeme/tahsilat',
            self::VehiclePayment => 'Plaka ödeme',
            self::VehicleReceivable => 'Plaka alacak',
            self::OwnerPayment => 'Araç sahibi ödemesi',
            self::DumpPayment => 'Döküm yeri ödemesi',
            self::SiteBill => 'Şantiye hakedişi',
            self::Site => 'Şantiye tahsilat/gider',
            self::Cash => 'Kasa hareketi',
            self::AccountTransfer => 'Hesaplar arası virman',
            self::Opening => 'Açılış bakiyesi',
            self::PartyTransfer => 'Cari virman',
        };
    }

    /**
     * Spatie permission resource suffix, e.g. firm-payment-cari-entry.
     */
    public function permissionResource(): string
    {
        return str_replace('_', '-', $this->value).'-cari-entry';
    }
}
