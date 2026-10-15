<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PartyRole: string
{
    use HasLabel;

    case VehicleOwner = 'vehicle_owner';
    case DumpOperator = 'dump_operator';
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Fuel = 'fuel';
    case Market = 'market';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::VehicleOwner => 'Araç sahibi',
            self::DumpOperator => 'Döküm işletmecisi',
            self::Customer => 'Müşteri',
            self::Supplier => 'Tedarikçi',
            self::Fuel => 'Akaryakıt',
            self::Market => 'Piyasa',
            self::Employee => 'Personel',
        };
    }

    /**
     * Roles a party can be linked to a site with.
     *
     * @return list<self>
     */
    public static function forSites(): array
    {
        return [self::Customer, self::Supplier, self::Fuel, self::Market];
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::VehicleOwner => 'Araç sahipleri',
            self::DumpOperator => 'Döküm işletmecileri',
            self::Customer => 'Müşteriler (işveren)',
            self::Supplier => 'Tedarikçiler',
            self::Fuel => 'Akaryakıt firmaları',
            self::Market => 'Piyasa firmaları',
            self::Employee => 'Personel',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::VehicleOwner => 'fa-solid fa-truck',
            self::DumpOperator => 'fa-solid fa-dumpster',
            self::Customer => 'fa-solid fa-handshake',
            self::Supplier => 'fa-solid fa-boxes-stacked',
            self::Fuel => 'fa-solid fa-gas-pump',
            self::Market => 'fa-solid fa-store',
            self::Employee => 'fa-solid fa-user-tie',
        };
    }
}
