<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
            'cari-entry',
            'cash-category',
            'bank',
            'vehicle',
            'vehicle-class',
            'site',
            'dump-site',
            'haul-route',
            'fuel-station',
            'employee',
            'employee-salary',
            'employee-payment',
            'employee-bonus',
            'example',
        ];

        $permissions = ['view-dashboard'];

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

        $role = Role::firstOrCreate(
            ['name' => 'yonetici', 'guard_name' => 'web'],
            ['id' => (string) Str::uuid7()]
        );
        $role->syncPermissions($permissions);

        $admin = User::query()->where('email', 'admin@hafline.net')->first();
        if ($admin !== null) {
            $admin->syncRoles(['yonetici']);
        }
    }
}
