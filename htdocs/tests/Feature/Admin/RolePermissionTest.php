<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_roles(): void
    {
        $this->get(route('admin.roles.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_user_without_permission_cannot_view_roles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }

    public function test_yonetici_can_manage_roles_and_users(): void
    {
        $admin = User::factory()->yonetici()->create();

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.permissions.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), [
                'name' => 'saha-ekibi',
                'permissions' => ['view-dashboard', 'view-haul'],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::query()->where('name', 'saha-ekibi')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('view-haul'));
        $this->assertFalse($role->hasPermissionTo('view-users'));
    }

    public function test_role_update_ignores_permissions_from_another_guard(): void
    {
        (new RolePermissionSeeder)->run();

        Permission::firstOrCreate(
            ['name' => 'view-order', 'guard_name' => 'api'],
            ['id' => (string) Str::uuid7()]
        );

        $user = User::factory()->create();
        $user->givePermissionTo(['view-role', 'edit-role']);

        $role = Role::create([
            'id' => (string) Str::uuid7(),
            'name' => 'web-rol',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo('view-dashboard');

        $this->actingAs($user)
            ->put(route('admin.roles.update', $role), [
                'name' => 'web-rol',
                'permissions' => ['view-dashboard', 'view-order'],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('view-dashboard'));
        $this->assertFalse(
            $role->permissions()->where('name', 'view-order')->where('guard_name', 'api')->exists()
        );
        $this->assertSame(['view-dashboard'], $role->permissions->pluck('name')->all());
    }

    public function test_user_can_be_created_with_roles(): void
    {
        $admin = User::factory()->yonetici()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Saha Operatör',
                'username' => 'saha.op',
                'email' => 'saha@hafline.test',
                'password' => 'HafLine34!',
                'password_confirmation' => 'HafLine34!',
                'active' => '1',
                'roles' => ['yonetici'],
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('username', 'saha.op')->firstOrFail();
        $this->assertTrue($created->hasRole('yonetici'));
    }

    public function test_each_report_requires_its_own_permission(): void
    {
        (new RolePermissionSeeder)->run();

        $user = User::factory()->create();
        $user->givePermissionTo('view-report-monthly-vouchers');

        $this->actingAs($user)
            ->get(route('admin.reports.monthly-vouchers'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.reports.site-exits'))
            ->assertForbidden();
    }

    public function test_view_permission_cannot_create_via_known_route(): void
    {
        (new RolePermissionSeeder)->run();

        $user = User::factory()->create();
        $user->givePermissionTo(['view-bank', 'view-dashboard']);

        $this->actingAs($user)
            ->get(route('admin.banks.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.banks.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.banks.store'), ['name' => 'Hack Bank', 'active' => '1'])
            ->assertForbidden();
    }
}
