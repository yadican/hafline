<?php

namespace Database\Seeders;

use App\Enums\CariEntryGroup;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * @return list<string>
     */
    public static function permissionNames(): array
    {
        $resources = [
            'haul',
            'machine-work',
            'fuel-receipt',
            'party',
            // Legacy monolithic resource — kept until 35a–37 authorize by group.
            'cari-entry',
            ...self::cariEntryGroupResources(),
            'invoice',
            'financial-instrument',
            'cash-category',
            'stock-item',
            'stock-usage',
            'stock-count',
            'bank',
            'vehicle',
            'vehicle-class',
            'site',
            'dump-site',
            'haul-route',
            'fuel-station',
            'fuel-tank',
            'shipment',
            'employee',
            'employee-salary',
            'employee-payment',
            'employee-bonus',
        ];

        $permissions = [
            'view-dashboard',
            'view-web-audit-log',
            'view-api-audit-log',
        ];

        foreach ($resources as $resource) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $permissions[] = "{$action}-{$resource}";
            }
        }

        foreach (self::reportPermissionNames() as $reportPermission) {
            $permissions[] = $reportPermission;
        }

        return array_merge($permissions, [
            'view-users',
            'view-user',
            'create-user',
            'edit-user',
            'delete-user',
            'view-role',
            'create-role',
            'edit-role',
            'delete-role',
            'view-permission',
        ]);
    }

    /**
     * @return list<string>
     */
    public static function cariEntryGroupResources(): array
    {
        return array_map(
            fn (CariEntryGroup $group) => $group->permissionResource(),
            CariEntryGroup::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function reportPermissionNames(): array
    {
        return [
            'view-report-monthly-vouchers',
            'view-report-site-exits',
            'view-report-dump-sites',
            'view-report-dump-site-payments',
            'view-report-fuel-stations',
            'view-report-fuel-stations-monthly',
            'view-report-ticket-control',
            'view-report-vehicle-monthly',
            'view-report-vehicle-periodic',
            'view-report-vehicle-site-monthly',
            'view-report-vehicle-balance',
            'view-report-owner-monthly',
            'view-report-owner-periodic',
            'view-report-owner-balance',
            'view-report-vehicle-cost',
            'view-report-vehicle-cost-owners',
            'view-report-company-balance',
            'view-report-company-ledger',
            'view-report-invoice-aging',
            'view-report-site-cari',
            'view-report-site-expense',
            'view-report-cash-movements',
            'view-report-employee-monthly',
            'view-report-employee-annual',
        ];
    }

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->seedWebCatalog();
        $this->seedApiCatalog();
        $this->purgeLegacyApiOnWebGuard();

        $admin = User::query()->where('email', 'admin@hafline.net')->first();
        if ($admin !== null) {
            $admin->syncRoles(
                Role::query()->where('name', 'yonetici')->where('guard_name', 'web')->get()
            );
        }
    }

    private function seedWebCatalog(): void
    {
        $permissions = self::permissionNames();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                ['id' => (string) Str::uuid7()]
            );
        }

        Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'view-reports')
            ->delete();

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'view-example',
                'create-example',
                'edit-example',
                'delete-example',
            ])
            ->delete();

        $this->expandLegacyCariEntryPermissions();

        $role = Role::firstOrCreate(
            ['name' => 'yonetici', 'guard_name' => 'web'],
            ['id' => (string) Str::uuid7()]
        );
        $role->syncPermissions($permissions);
    }

    /**
     * Roles that already hold monolithic *-cari-entry get the matching action
     * on every cari entry group resource.
     */
    private function expandLegacyCariEntryPermissions(): void
    {
        $groupResources = self::cariEntryGroupResources();
        $actions = ['view', 'create', 'edit', 'delete'];

        foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
            $toGive = [];

            foreach ($actions as $action) {
                if (! $role->hasPermissionTo("{$action}-cari-entry")) {
                    continue;
                }

                foreach ($groupResources as $resource) {
                    $toGive[] = "{$action}-{$resource}";
                }
            }

            if ($toGive !== []) {
                $role->givePermissionTo($toGive);
            }
        }
    }

    private function seedApiCatalog(): void
    {
        foreach (ApiPermissions::all() as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => ApiPermissions::GUARD],
                ['id' => (string) Str::uuid7()]
            );
        }

        $apiOperator = Role::firstOrCreate(
            ['name' => 'api-operator', 'guard_name' => ApiPermissions::GUARD],
            ['id' => (string) Str::uuid7()]
        );
        $apiOperator->syncPermissions(ApiPermissions::all());
    }

    /**
     * Earlier mistake: api-operator / operate-field-haul lived on the web guard.
     */
    private function purgeLegacyApiOnWebGuard(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['operate-field-haul', ApiPermissions::FIELD_HAUL_OPERATE])
            ->delete();

        $legacy = Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'api-operator')
            ->first();

        if ($legacy !== null) {
            $legacy->syncPermissions([]);
            $legacy->delete();
        }
    }
}
