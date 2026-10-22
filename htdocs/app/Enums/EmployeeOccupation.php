<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum EmployeeOccupation: string
{
    use HasLabel;

    case Driver = 'driver';
    case Operator = 'operator';
    case Foreman = 'foreman';
    case Craftsman = 'craftsman';
    case Guard = 'guard';
    case Accounting = 'accounting';
    case Warehouse = 'warehouse';
    case Office = 'office';
    case Timekeeper = 'timekeeper';
    case SiteSupervisor = 'site_supervisor';

    public function label(): string
    {
        return match ($this) {
            self::Driver => 'Şoför',
            self::Operator => 'Operatör',
            self::Foreman => 'Formen',
            self::Craftsman => 'Usta',
            self::Guard => 'Bekçi',
            self::Accounting => 'Muhasebe',
            self::Warehouse => 'Depo',
            self::Office => 'Ofis',
            self::Timekeeper => 'Puantör',
            self::SiteSupervisor => 'Saha sorumlusu',
        };
    }
}
