<?php

namespace Tests\Feature\Admin;

use App\Models\Bank;
use App\Models\CashCategory;
use App\Models\DumpSite;
use App\Models\FuelStation;
use App\Models\HaulRoute;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\VehicleClass;
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
                'guard_name' => 'web',
                'permissions' => ['view-dashboard', 'view-haul'],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::query()->where('name', 'saha-ekibi')->where('guard_name', 'web')->firstOrFail();
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

    public function test_view_permission_can_show_bank_but_cannot_edit(): void
    {
        (new RolePermissionSeeder)->run();

        $user = User::factory()->create();
        $user->givePermissionTo(['view-bank', 'view-dashboard']);

        $bank = Bank::factory()->create(['name' => 'Görünen Banka']);

        $this->actingAs($user)
            ->get(route('admin.banks.show', $bank))
            ->assertOk()
            ->assertSee('Görünen Banka')
            ->assertDontSee('Düzenle');

        $this->actingAs($user)
            ->get(route('admin.banks.edit', $bank))
            ->assertForbidden();
    }

    public function test_view_permission_can_show_cash_category_but_cannot_edit(): void
    {
        (new RolePermissionSeeder)->run();

        $user = User::factory()->create();
        $user->givePermissionTo(['view-cash-category', 'view-dashboard']);

        $category = CashCategory::factory()->create(['name' => 'Görünen Kategori']);

        $this->actingAs($user)
            ->get(route('admin.cash-categories.show', $category))
            ->assertOk()
            ->assertSee('Görünen Kategori')
            ->assertDontSee('Düzenle');

        $this->actingAs($user)
            ->get(route('admin.cash-categories.edit', $category))
            ->assertForbidden();
    }

    public function test_view_permission_can_show_vehicle_class_but_cannot_edit(): void
    {
        (new RolePermissionSeeder)->run();

        $user = User::factory()->create();
        $user->givePermissionTo(['view-vehicle-class', 'view-dashboard']);

        $class = VehicleClass::factory()->create(['name' => 'Görünen Sınıf']);

        $this->actingAs($user)
            ->get(route('admin.vehicle-classes.show', $class))
            ->assertOk()
            ->assertSee('Görünen Sınıf')
            ->assertDontSee('Düzenle');

        $this->actingAs($user)
            ->get(route('admin.vehicle-classes.edit', $class))
            ->assertForbidden();
    }

    public function test_view_permission_can_show_site_but_cannot_edit(): void
    {
        (new RolePermissionSeeder)->run();

        $user = User::factory()->create();
        $user->givePermissionTo(['view-site', 'view-dashboard']);

        $site = Site::factory()->create(['name' => 'Görünen Şantiye']);

        $this->actingAs($user)
            ->get(route('admin.sites.show', $site))
            ->assertOk()
            ->assertSee('Görünen Şantiye')
            ->assertDontSee('Düzenle');

        $this->actingAs($user)
            ->get(route('admin.sites.edit', $site))
            ->assertForbidden();
    }

    public function test_view_permission_can_show_dump_site_but_cannot_edit(): void
    {
        (new RolePermissionSeeder)->run();

        $user = User::factory()->create();
        $user->givePermissionTo(['view-dump-site', 'view-dashboard']);

        $dumpSite = DumpSite::factory()->create(['name' => 'Görünen Döküm']);

        $this->actingAs($user)
            ->get(route('admin.dump-sites.show', $dumpSite))
            ->assertOk()
            ->assertSee('Görünen Döküm')
            ->assertDontSee('Düzenle');

        $this->actingAs($user)
            ->get(route('admin.dump-sites.edit', $dumpSite))
            ->assertForbidden();
    }

    public function test_view_permission_can_show_haul_route_but_cannot_edit(): void
    {
        (new RolePermissionSeeder)->run();

        $user = User::factory()->create();
        $user->givePermissionTo(['view-haul-route', 'view-dashboard']);

        $route = HaulRoute::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.haul-routes.show', $route))
            ->assertOk()
            ->assertDontSee('Düzenle');

        $this->actingAs($user)
            ->get(route('admin.haul-routes.edit', $route))
            ->assertForbidden();
    }

    public function test_view_permission_can_show_fuel_station_but_cannot_edit(): void
    {
        (new RolePermissionSeeder)->run();

        $user = User::factory()->create();
        $user->givePermissionTo(['view-fuel-station', 'view-dashboard']);

        $station = FuelStation::factory()->create(['name' => 'Görünen İstasyon']);

        $this->actingAs($user)
            ->get(route('admin.fuel-stations.show', $station))
            ->assertOk()
            ->assertSee('Görünen İstasyon')
            ->assertDontSee('Düzenle');

        $this->actingAs($user)
            ->get(route('admin.fuel-stations.edit', $station))
            ->assertForbidden();
    }
}
