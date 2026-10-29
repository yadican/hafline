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
    case Opening = 'opening';
    case PartyTransfer = 'party_transfer';
    case SiteBill = 'site_bill';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Açılış Bakiyesi',
            self::PartyTransfer => 'Cari Virman',
            self::SiteBill => 'Şantiye Hakedişi',
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
     * Kinds that take any cari card, whatever its roles.
     */
    public function takesAnyParty(): bool
    {
        return in_array($this, [self::Opening, self::PartyTransfer], true);
    }

    /**
     * Kinds that record a balance, not money changing hands: no payment method, account or category.
     */
    public function movesMoney(): bool
    {
        return ! in_array($this, [self::Opening, self::PartyTransfer, self::SiteBill], true);
    }

    /**
     * choice: the form asks borç or alacak. Otherwise the direction is fixed.
     * A transfer always debits its first party and credits the second.
     */
    public function directionMode(): string
    {
        return match ($this) {
            self::VehiclePayment, self::OwnerPayment, self::DumpPayment, self::PartyTransfer, self::SiteBill => 'debit',
            self::VehicleReceivable => 'credit',
            default => 'choice',
        };
    }

    /**
     * What borç / alacak means for this kind, in the words the form shows.
     * Kasa Nakit is the drawer's own side (borç = giriş); cari and site entries are the party's side (alacak = giriş).
     *
     * @return array{debit: string, credit: string}
     */
    public function directionLabels(): array
    {
        return match ($this) {
            self::Cash => ['debit' => 'Kasa girişi', 'credit' => 'Kasa çıkışı'],
            self::Site => ['debit' => 'Kasa çıkışı (gider)', 'credit' => 'Kasa girişi (tahsilat)'],
            self::Opening => ['debit' => 'Cari bize borçlu', 'credit' => 'Biz cariye borçluyuz'],
            self::Supplier, self::Customer, self::Fuel, self::Market,
            self::VehiclePayment, self::OwnerPayment, self::DumpPayment => ['debit' => 'Kasa çıkışı (borç)', 'credit' => 'Kasa girişi (alacak)'],
            default => ['debit' => 'Borç', 'credit' => 'Alacak'],
        };
    }

    /**
     * Kinds whose cheque or note goes into the portfolio. A plate receivable records what the plate
     * earned, not a payment; Kasa Nakit has no payment method.
     */
    public function tracksInstruments(): bool
    {
        return $this->movesMoney() && ! in_array($this, [self::Cash, self::VehicleReceivable], true);
    }

    /**
     * A site entry posts only a collection, and only when the site has its own cari.
     */
    public function postsLedger(): bool
    {
        return $this !== self::Cash;
    }
}
