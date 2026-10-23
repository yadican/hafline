<?php

namespace App\Http\Controllers\Admin\Role;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionGroups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends AdminController
{
    protected array $permissionMap = [
        'index' => 'view-role',
        'show' => 'view-role',
        'create' => 'create-role',
        'store' => 'create-role',
        'edit' => 'edit-role',
        'update' => 'edit-role',
        'destroy' => 'delete-role',
    ];

    public function index(): View
    {

        return view('admin.roles.index', [
            'title' => 'Roller',
            'roles' => Role::query()->with('permissions')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {

        return view('admin.roles.create', [
            'title' => 'Yeni rol',
            'webPermissionGroups' => PermissionGroups::grouped(guard: 'web'),
            'apiPermissionGroups' => PermissionGroups::grouped(guard: 'api'),
            'selectedPermissions' => old('permissions', []),
            'selectedGuard' => old('guard_name', 'web'),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {

        return $this->complete(
            function () use ($request) {
                $role = Role::create([
                    'id' => (string) Str::uuid7(),
                    'name' => $request->validated('name'),
                    'guard_name' => $request->validated('guard_name'),
                ]);

                $this->syncRolePermissions($role, $request->validated('permissions', []));
            },
            'admin.roles.index',
            'Rol oluşturuldu.',
        );
    }

    public function show(Role $role): View
    {
        $role->load('permissions');

        return view('admin.roles.show', [
            'title' => 'Rol detayı',
            'role' => $role,
            'permissionGroups' => PermissionGroups::grouped(
                $role->permissions->where('guard_name', $role->guard_name)->values(),
            ),
        ]);
    }

    public function edit(Role $role): View
    {

        $role->load('permissions');

        return view('admin.roles.edit', [
            'title' => 'Rolü düzenle',
            'role' => $role,
            'permissionGroups' => PermissionGroups::grouped(guard: $role->guard_name),
            'selectedPermissions' => old(
                'permissions',
                $role->permissions
                    ->where('guard_name', $role->guard_name)
                    ->pluck('name')
                    ->all()
            ),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {

        return $this->complete(
            function () use ($request, $role) {
                $role->update(['name' => $request->validated('name')]);
                $this->syncRolePermissions($role, $request->validated('permissions', []));
                app()[PermissionRegistrar::class]->forgetCachedPermissions();
            },
            'admin.roles.index',
            'Rol güncellendi.',
        );
    }

    public function destroy(Role $role): RedirectResponse
    {

        return $this->complete(
            function () use ($role) {
                $role->syncPermissions([]);
                $role->delete();
                app()[PermissionRegistrar::class]->forgetCachedPermissions();
            },
            'admin.roles.index',
            'Rol silindi.',
        );
    }

    /**
     * @param  array<int, string>  $names
     */
    private function syncRolePermissions(Role $role, array $names): void
    {
        $allowed = Permission::query()
            ->where('guard_name', $role->guard_name)
            ->whereIn('name', array_values(array_unique($names)))
            ->pluck('name')
            ->all();

        $role->syncPermissions($allowed);
    }
}
