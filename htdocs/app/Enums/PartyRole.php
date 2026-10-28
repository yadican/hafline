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
    case MachineOperator = 'machine_operator';
    case Site = 'site';
    case Quarry = 'quarry';
    case Commission = 'commission';

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
            self::MachineOperator => 'Makine operatörü',
            self::Site => 'Şantiye',
            self::Quarry => 'Kum ocağı',
            self::Commission => 'Komisyoncu',
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

    /**
     * Staff are people; operators of a site, dump, quarry or fuel station are companies.
     * Null: both kinds of party may hold the role.
     */
    public function onlyFor(): ?PartyType
    {
        return match ($this) {
            self::Employee, self::MachineOperator => PartyType::Person,
            self::DumpOperator, self::Fuel, self::Site, self::Quarry => PartyType::Company,
            default => null,
        };
    }

    public function allowedFor(PartyType $type): bool
    {
        return in_array($this->onlyFor(), [null, $type], true);
    }

    /**
     * @return list<self>
     */
    public static function forType(PartyType $type): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role) => $role->allowedFor($type),
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function optionsFor(PartyType $type): array
    {
        $options = [];

        foreach (self::forType($type) as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
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
            self::MachineOperator => 'Makine operatörleri',
            self::Site => 'Şantiyeler',
            self::Quarry => 'Kum ocakları',
            self::Commission => 'Komisyoncular',
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
            self::MachineOperator => 'fa-solid fa-helmet-safety',
            self::Site => 'fa-solid fa-person-digging',
            self::Quarry => 'fa-solid fa-mountain',
            self::Commission => 'fa-solid fa-handshake-angle',
        };
    }

    /**
     * Checking this role on Cari opens a domain card and cannot be undone from Cari.
     */
    public function requiresAssignmentConfirm(): bool
    {
        return $this === self::Employee;
    }

    public function assignmentConfirmTitle(): string
    {
        return match ($this) {
            self::Employee => 'Personel rolü açılsın mı?',
            default => 'Emin misiniz?',
        };
    }

    public function assignmentConfirmText(): string
    {
        return match ($this) {
            self::Employee => 'Bu kişi adına Personel kartı açılacak. Rol Cari ekranından kaldırılamaz; kaldırmak için Personel menüsünü kullanmanız gerekir. Maaş, ödeme veya prim kaydı varsa kart silinemez — yalnızca pasife alınabilir. Bu işlem sorumluluk gerektirir. Emin misiniz?',
            default => '',
        };
    }

    /**
     * Tooltip when the role checkbox is locked on the Cari form.
     */
    public function lockedHint(): string
    {
        return match ($this) {
            self::Employee => 'Personel kartı açık. Rolü Cari’den kaldıramazsınız; Personel menüsünü kullanın.',
            self::VehicleOwner => 'Araç sahipliği bağlı. Kaldırmak için Filo → Araçlar üzerinden sahipliği kapatın.',
            self::DumpOperator => 'Döküm yeri bağlı. Kaldırmak için ilgili döküm yeri kartını kullanın.',
            self::Fuel => 'Akaryakıt istasyonu bağlı. Kaldırmak için ilgili istasyon kartını kullanın.',
            self::MachineOperator => 'Makine çalışma fişinde operatör olarak kullanılmış. Cari’den kaldırılamaz.',
            self::Customer, self::Supplier, self::Market => 'Şantiye veya cari hareket bağlantısı var. Cari’den kaldırılamaz.',
            self::Site => 'Şantiye kartı bağlı. Kaldırmak için ilgili şantiye kartını kullanın.',
            self::Quarry => 'Kum ocağı kartı bağlı. Kaldırmak için ilgili ocak kartını kullanın.',
            self::Commission => 'Kayıtlarda komisyoncu olarak kullanılmış. Cari’den kaldırılamaz.',
        };
    }
}
