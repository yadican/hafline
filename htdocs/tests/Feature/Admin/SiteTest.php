<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Models\FuelStation;
use App\Models\HaulRoute;
use App\Models\Party;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected_from_the_site_list(): void
    {
        $this->get(route('admin.sites.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_a_site(): void
    {
        $this->actingAs($this->admin)->get(route('admin.sites.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.sites.store'), ['name' => 'KARTAL MERKEZ', 'active' => '1'])
            ->assertRedirect(route('admin.sites.index'))
            ->assertSessionHas('success');

        $site = Site::query()->where('name', 'KARTAL MERKEZ')->firstOrFail();
        $this->assertTrue($site->active);

        $this->actingAs($this->admin)->get(route('admin.sites.show', $site))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.sites.edit', $site))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.sites.update', $site), ['name' => 'KARTAL SAHİL'])
            ->assertRedirect(route('admin.sites.index'));

        $site->refresh();
        $this->assertSame('KARTAL SAHİL', $site->name);
        $this->assertFalse($site->active);

        $this->actingAs($this->admin)
            ->delete(route('admin.sites.destroy', $site))
            ->assertRedirect(route('admin.sites.index'));

        $this->assertSoftDeleted($site);
    }

    public function test_site_names_are_unique_among_active_records(): void
    {
        $taken = Site::factory()->create(['name' => 'PENDİK']);

        $this->actingAs($this->admin)
            ->from(route('admin.sites.create'))
            ->post(route('admin.sites.store'), ['name' => 'PENDİK'])
            ->assertSessionHasErrors('name');

        $taken->delete();

        $this->actingAs($this->admin)
            ->post(route('admin.sites.store'), ['name' => 'PENDİK'])
            ->assertSessionHasNoErrors();
    }

    public function test_site_with_routes_cannot_be_deleted(): void
    {
        $route = HaulRoute::factory()->create();

        $this->actingAs($this->admin)
            ->from(route('admin.sites.index'))
            ->delete(route('admin.sites.destroy', $route->site_id))
            ->assertRedirect(route('admin.sites.index'))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'güzergah'));

        $this->assertNotSoftDeleted('sites', ['id' => $route->site_id]);
    }

    public function test_list_filters_and_sorts_by_route_count(): void
    {
        $busy = Site::factory()->create(['name' => 'ATAŞEHİR']);
        HaulRoute::factory()->count(2)->create(['site_id' => $busy->id]);
        Site::factory()->create(['name' => 'BEYKOZ', 'active' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.sites.index', ['active' => '0']))
            ->assertOk()
            ->assertSee('BEYKOZ')
            ->assertDontSee('ATAŞEHİR');

        $this->actingAs($this->admin)
            ->get(route('admin.sites.index', ['sort' => 'routes', 'dir' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['BEYKOZ', 'ATAŞEHİR']);
    }

    public function test_site_links_many_parties_with_roles(): void
    {
        $employer = Party::factory()->company()->create(['name' => 'YAPI A.Ş.']);
        $supplier = Party::factory()->company()->create(['name' => 'KUM TEDARİK']);
        $both = Party::factory()->company()->create(['name' => 'ÇOK YÖNLÜ LTD']);

        $this->actingAs($this->admin)
            ->post(route('admin.sites.store'), [
                'name' => 'KARTAL',
                'active' => '1',
                'parties' => [
                    'customer' => [$employer->id, $both->id],
                    'supplier' => [$supplier->id, $both->id],
                ],
            ])
            ->assertSessionHasNoErrors();

        $kartal = Site::query()->where('name', 'KARTAL')->firstOrFail();
        $other = Site::factory()->create(['name' => 'PENDİK']);
        $this->actingAs($this->admin)
            ->post(route('admin.sites.store'), ['name' => 'TUZLA', 'parties' => ['supplier' => [$supplier->id]]])
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $kartal->parties()->count());
        $this->assertSame(2, $supplier->sites()->count(), 'A party can work on many sites.');
        $this->assertTrue($both->fresh()->hasRole(PartyRole::Customer) && $both->fresh()->hasRole(PartyRole::Supplier));

        $this->actingAs($this->admin)
            ->get(route('admin.sites.index', ['sort' => 'parties', 'dir' => 'desc']))
            ->assertSeeInOrder(['KARTAL', 'TUZLA', 'PENDİK']);

        $this->actingAs($this->admin)
            ->get(route('admin.parties.edit', $both))
            ->assertSee('Şantiyeleri')
            ->assertSee('Müşteri, Tedarikçi');

        $this->actingAs($this->admin)
            ->put(route('admin.sites.update', $kartal), ['name' => 'KARTAL', 'parties' => ['customer' => [$employer->id]]])
            ->assertSessionHasNoErrors();

        $this->assertSame([$employer->id], $kartal->parties()->pluck('parties.id')->all());
        $this->assertSame(0, $other->parties()->count());
    }

    public function test_linked_party_keeps_its_role_and_cannot_be_deleted(): void
    {
        $site = Site::factory()->create();
        $party = Party::factory()->company()->create();
        $party->assignRole(PartyRole::Customer);
        $site->parties()->attach($party->id, ['role' => 'customer']);

        $this->actingAs($this->admin)
            ->put(route('admin.parties.update', $party), ['title' => $party->name, 'roles' => []])
            ->assertSessionHasNoErrors();
        $this->assertTrue($party->fresh()->hasRole(PartyRole::Customer));

        $this->actingAs($this->admin)
            ->delete(route('admin.parties.destroy', $party))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($party);
    }

    public function test_edit_page_shows_dump_sites_and_fuel_stations(): void
    {
        $route = HaulRoute::factory()->create();
        FuelStation::factory()->hasAttached($route->site)->create(['name' => 'OPET MERKEZ']);

        $this->actingAs($this->admin)
            ->get(route('admin.sites.edit', $route->site_id))
            ->assertOk()
            ->assertSee($route->dumpSite->name)
            ->assertSee('OPET MERKEZ');
    }

    public function test_invalid_party_links_are_rejected(): void
    {
        $deleted = Party::factory()->create();
        $deleted->delete();

        $this->actingAs($this->admin)
            ->post(route('admin.sites.store'), ['name' => 'X', 'parties' => ['customer' => [$deleted->id, 'abc']]])
            ->assertSessionHasErrors(['parties.customer.0', 'parties.customer.1']);
    }
}
