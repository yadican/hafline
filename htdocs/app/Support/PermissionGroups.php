<?php

namespace App\Support;

use App\Enums\CariEntryGroup;
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
            'invoices' => 'Faturalar',
            'financial-instruments' => 'Çek / senet',
            'cash-categories' => 'Kasa kategorileri',
            'stock-items' => 'Stok',
            'stock-usages' => 'Stok kullanımı',
            'stock-counts' => 'Stok sayımı',
            'banks' => 'Bankalar',
            'vehicles' => 'Araçlar',
            'vehicle-classes' => 'Araç sınıfları',
            'sites' => 'Şantiyeler',
            'dump-sites' => 'Döküm yerleri',
            'haul-routes' => 'Güzergahlar',
            'fuel-stations' => 'Yakıt yerleri',
            'fuel-tanks' => 'Yakıt dolum ve sayımları',
            'shipments' => 'Nakliye seferleri',
            'employees' => 'Personel',
            'employee-salaries' => 'Maaş kartları',
            'employee-payments' => 'Personel ödemeleri',
            'employee-bonuses' => 'Personel primleri',
            'users' => 'Kullanıcılar',
            'roles' => 'Roller & İzinler',
            'other' => 'Diğer',
            // Full-width rows at the bottom of the role UI.
            'cari-entries' => 'Cari işlemleri',
            'reports' => 'Raporlar',
        ];
    }

    public static function isFullWidthGroup(string $key): bool
    {
        return in_array($key, ['cari-entries', 'reports'], true);
    }

    public static function actionLabel(string $action): string
    {
        return self::actionLabels()[$action] ?? $action;
    }

    public static function resourceLabel(string $resource): string
    {
        return self::resourceLabels()[$resource] ?? str_replace('-', ' ', $resource);
    }

    /**
     * Group CRUD permissions by resource key for compact row layouts.
     *
     * @param  Collection<int, Permission>  $permissions
     * @return Collection<string, Collection<string, Permission>>
     */
    public static function byResource(Collection $permissions): Collection
    {
        $order = [];
        foreach (CariEntryGroup::cases() as $group) {
            $order[$group->permissionResource()] = count($order);
        }
        $order['cari-entry'] = count($order);

        return $permissions
            ->mapToGroups(function (Permission $permission) {
                if (preg_match('/^(view|create|edit|delete)-(.+)$/', $permission->name, $matches) !== 1) {
                    return [$permission->name => $permission];
                }

                return [$matches[2] => $permission];
            })
            ->map(function (Collection $items) {
                return $items->keyBy(function (Permission $permission) {
                    if (preg_match('/^(view|create|edit|delete)-/', $permission->name, $matches) === 1) {
                        return $matches[1];
                    }

                    return $permission->name;
                });
            })
            ->sortBy(fn (Collection $items, string $key) => $order[$key] ?? 1000);
    }

    /**
     * Human-readable Turkish label for a Spatie permission name (UI only).
     */
    public static function permissionLabel(string $name): string
    {
        $exact = self::exactPermissionLabels();
        if (isset($exact[$name])) {
            return $exact[$name];
        }

        if (preg_match('/^(view|create|edit|delete)-(.+)$/', $name, $matches) === 1) {
            $action = self::actionLabels()[$matches[1]] ?? $matches[1];
            $resource = self::resourceLabels()[$matches[2]] ?? str_replace('-', ' ', $matches[2]);

            return "{$resource} — {$action}";
        }

        return $name;
    }

    /**
     * @return array<string, string>
     */
    private static function exactPermissionLabels(): array
    {
        return [
            'view-dashboard' => 'Gösterge paneli',
            'view-web-audit-log' => 'Web denetim kaydı',
            'view-api-audit-log' => 'API denetim kaydı',
            'view-users' => 'Kullanıcıları görüntüle',
            'view-user' => 'Kullanıcı görüntüle',
            'create-user' => 'Kullanıcı oluştur',
            'edit-user' => 'Kullanıcı düzenle',
            'delete-user' => 'Kullanıcı sil',
            'view-role' => 'Rol görüntüle',
            'create-role' => 'Rol oluştur',
            'edit-role' => 'Rol düzenle',
            'delete-role' => 'Rol sil',
            'view-permission' => 'İzinleri görüntüle',
            ApiPermissions::FIELD_HAUL_OPERATE => 'Saha taşıma işlemi',
            'view-report-monthly-vouchers' => 'Rapor: Aylık koçanlar',
            'view-report-site-exits' => 'Rapor: Şantiye çıkış',
            'view-report-dump-sites' => 'Rapor: Döküm yerleri',
            'view-report-dump-site-payments' => 'Rapor: Döküm yeri ödeme',
            'view-report-fuel-stations' => 'Rapor: Yakıt yerleri',
            'view-report-fuel-stations-monthly' => 'Rapor: Yakıt yeri aylık',
            'view-report-ticket-control' => 'Rapor: Hareket fişi kontrol',
            'view-report-vehicle-monthly' => 'Rapor: Plaka aylık döküm',
            'view-report-vehicle-periodic' => 'Rapor: Plaka dönemsel',
            'view-report-vehicle-site-monthly' => 'Rapor: Plaka şantiye döküm',
            'view-report-vehicle-balance' => 'Rapor: Plaka bakiye',
            'view-report-owner-monthly' => 'Rapor: Plaka sahibi aylık',
            'view-report-owner-periodic' => 'Rapor: Plaka sahibi dönemsel',
            'view-report-owner-balance' => 'Rapor: Plaka sahibi bakiye',
            'view-report-vehicle-cost' => 'Rapor: Araç maliyet',
            'view-report-vehicle-cost-owners' => 'Rapor: Plaka sahibi özet',
            'view-report-company-balance' => 'Rapor: Firma bakiye',
            'view-report-company-ledger' => 'Rapor: Firma cari',
            'view-report-invoice-aging' => 'Rapor: Fatura yaşlandırma',
            'view-report-site-cari' => 'Rapor: Şantiye cari',
            'view-report-site-expense' => 'Rapor: Şantiye gider',
            'view-report-cash-movements' => 'Rapor: Kasa hareketleri',
            'view-report-employee-monthly' => 'Rapor: Personel aylık döküm',
            'view-report-employee-annual' => 'Rapor: Personel yıllık döküm',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function actionLabels(): array
    {
        return [
            'view' => 'Görüntüle',
            'create' => 'Oluştur',
            'edit' => 'Düzenle',
            'delete' => 'Sil',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function resourceLabels(): array
    {
        $labels = [
            'haul' => 'Taşıma fişi',
            'machine-work' => 'Makine çalışma',
            'fuel-receipt' => 'Yakıt fişi',
            'party' => 'Cari kart',
            'cari-entry' => 'Cari işlem',
            'invoice' => 'Fatura',
            'financial-instrument' => 'Çek / senet',
            'cash-category' => 'Kasa kategorisi',
            'stock-item' => 'Stok',
            'stock-usage' => 'Stok kullanımı',
            'stock-count' => 'Stok sayımı',
            'bank' => 'Banka',
            'vehicle' => 'Araç',
            'vehicle-class' => 'Araç sınıfı',
            'site' => 'Şantiye',
            'dump-site' => 'Döküm yeri',
            'haul-route' => 'Güzergah',
            'fuel-station' => 'Yakıt yeri',
            'fuel-tank' => 'Yakıt dolum / sayım',
            'shipment' => 'Nakliye seferi',
            'employee' => 'Personel',
            'employee-salary' => 'Maaş kartı',
            'employee-payment' => 'Personel ödemesi',
            'employee-bonus' => 'Personel primi',
            'user' => 'Kullanıcı',
            'role' => 'Rol',
            'permission' => 'İzin',
        ];

        foreach (CariEntryGroup::cases() as $group) {
            $labels[$group->permissionResource()] = $group->label();
        }

        return $labels;
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
            str_contains($permissionName, 'cari-entry') => 'cari-entries',
            str_contains($permissionName, 'party') => 'parties',
            str_contains($permissionName, 'invoice') => 'invoices',
            str_contains($permissionName, 'financial-instrument') => 'financial-instruments',
            str_contains($permissionName, 'cash-category') => 'cash-categories',
            str_contains($permissionName, 'stock-item') => 'stock-items',
            str_contains($permissionName, 'stock-usage') => 'stock-usages',
            str_contains($permissionName, 'stock-count') => 'stock-counts',
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
