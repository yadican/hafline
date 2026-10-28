<?php

namespace App\Support;

use App\Models\Permission;
use Illuminate\Support\Collection;

class PermissionGroups
{
    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'api' => 'API',
            'system' => 'Sistem',
            'hauls' => 'Taşıma fişleri',
            'machine-works' => 'Makine çalışma',
            'fuel-receipts' => 'Yakıt fişleri',
            'parties' => 'Cari kartlar',
            'cari-entries' => 'Cari hareketler',
            'cash-categories' => 'Kasa kategorileri',
            'banks' => 'Bankalar',
            'vehicles' => 'Araçlar',
            'vehicle-classes' => 'Araç sınıfları',
            'sites' => 'Şantiyeler',
            'dump-sites' => 'Döküm yerleri',
            'haul-routes' => 'Güzergahlar',
            'fuel-stations' => 'Yakıt kaynakları',
            'fuel-tanks' => 'Depo dolum ve sayımları',
            'shipments' => 'Nakliye seferleri',
            'employees' => 'Personel',
            'employee-salaries' => 'Maaş kartları',
            'employee-payments' => 'Personel ödemeleri',
            'employee-bonuses' => 'Personel primleri',
            'reports' => 'Raporlar',
            'users' => 'Kullanıcılar',
            'roles' => 'Roller & İzinler',
            'examples' => 'Örnek kayıtlar',
            'other' => 'Diğer',
        ];
    }

    public static function groupKey(string $permissionName, ?string $guardName = null): string
    {
        if ($guardName === ApiPermissions::GUARD || str_starts_with($permissionName, 'field-haul.')) {
            return 'api';
        }

        return match (true) {
            $permissionName === 'view-dashboard'
                || $permissionName === 'view-web-audit-log'
                || $permissionName === 'view-api-audit-log' => 'system',
            str_starts_with($permissionName, 'view-report-') || str_contains($permissionName, 'report') => 'reports',
            str_contains($permissionName, 'haul') && ! str_contains($permissionName, 'haul-route') => 'hauls',
            str_contains($permissionName, 'machine-work') => 'machine-works',
            str_contains($permissionName, 'fuel-receipt') => 'fuel-receipts',
            str_contains($permissionName, 'party') => 'parties',
            str_contains($permissionName, 'cari-entry') => 'cari-entries',
            str_contains($permissionName, 'cash-category') => 'cash-categories',
            str_contains($permissionName, 'bank') => 'banks',
            str_contains($permissionName, 'vehicle-class') => 'vehicle-classes',
            str_contains($permissionName, 'vehicle') => 'vehicles',
            str_contains($permissionName, 'dump-site') => 'dump-sites',
            str_contains($permissionName, 'haul-route') => 'haul-routes',
            str_contains($permissionName, 'fuel-station') => 'fuel-stations',
            str_contains($permissionName, 'fuel-tank') => 'fuel-tanks',
            str_contains($permissionName, 'shipment') => 'shipments',
            str_contains($permissionName, 'site') => 'sites',
            str_contains($permissionName, 'employee-salary') => 'employee-salaries',
            str_contains($permissionName, 'employee-payment') => 'employee-payments',
            str_contains($permissionName, 'employee-bonus') => 'employee-bonuses',
            str_contains($permissionName, 'employee') => 'employees',
            str_contains($permissionName, 'user') => 'users',
            str_contains($permissionName, 'role') || $permissionName === 'view-permission' => 'roles',
            str_contains($permissionName, 'example') => 'examples',
            default => 'other',
        };
    }

    /**
     * @return Collection<string, Collection<int, Permission>>
     */
    public static function grouped(?Collection $permissions = null, ?string $guard = null): Collection
    {
        $permissions ??= Permission::query()
            ->when($guard !== null, fn ($query) => $query->where('guard_name', $guard))
            ->orderBy('name')
            ->get();
        $labels = self::labels();

        return $permissions
            ->groupBy(fn (Permission $permission) => self::groupKey($permission->name, $permission->guard_name))
            ->sortBy(function (Collection $items, string $key) use ($labels) {
                $order = array_flip(array_keys($labels));

                return $order[$key] ?? 999;
            })
            ->mapWithKeys(function (Collection $items, string $key) {
                return [$key => $items->sortBy('name')->values()];
            });
    }

    public static function labelFor(string $key): string
    {
        return self::labels()[$key] ?? ucfirst($key);
    }
}
