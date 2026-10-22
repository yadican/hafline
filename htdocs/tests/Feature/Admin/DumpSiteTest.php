<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Models\DumpSite;
use App\Models\HaulRoute;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DumpSiteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected_from_the_dump_site_list(): void
    {
        $this->get(route('admin.dump-sites.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_a_dump_site(): void
    {
        $operator = Party::factory()->company()->create();
        $other = Party::factory()->company()->create();

        $this->actingAs($this->admin)->get(route('admin.dump-sites.create'))->assertOk()->assertSee($operator->name);

        $this->actingAs($this->admin)
            ->post(route('admin.dump-sites.store'), ['name' => 'KEMERBURGAZ', 'party_id' => $operator->id, 'active' => '1'])
            ->assertRedirect(route('admin.dump-sites.index'))
            ->assertSessionHas('success');

        $dumpSite = DumpSite::query()->where('name', 'KEMERBURGAZ')->firstOrFail();
        $this->assertSame($operator->id, $dumpSite->party_id);
        $this->assertTrue($operator->fresh()->hasRole(PartyRole::DumpOperator));

        $this->actingAs($this->admin)->get(route('admin.dump-sites.edit', $dumpSite))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.dump-sites.update', $dumpSite), ['name' => 'KEMERBURGAZ', 'party_id' => $other->id, 'active' => '0'])
            ->assertRedirect(route('admin.dump-sites.index'));

        $this->assertFalse($dumpSite->refresh()->active);
        $this->assertSame($other->id, $dumpSite->party_id);

        $this->actingAs($this->admin)
            ->get(route('admin.dump-sites.index'))
            ->assertSee($other->name);

        $this->actingAs($this->admin)
            ->delete(route('admin.dump-sites.destroy', $dumpSite))
            ->assertRedirect(route('admin.dump-sites.index'));

        $this->assertSoftDeleted($dumpSite);
    }

    public function test_dump_site_names_are_unique_and_required(): void
    {
        DumpSite::factory()->create(['name' => 'ÇATALCA']);

        $this->actingAs($this->admin)
            ->post(route('admin.dump-sites.store'), ['name' => 'ÇATALCA'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->post(route('admin.dump-sites.store'), ['name' => ''])
            ->assertSessionHasErrors(['name', 'party_id']);

        $this->assertDatabaseCount('dump_sites', 1);
    }

    public function test_dump_site_with_routes_cannot_be_deleted(): void
    {
        $route = HaulRoute::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.dump-sites.destroy', $route->dump_site_id))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('dump_sites', ['id' => $route->dump_site_id]);
    }

    public function test_list_searches_by_name(): void
    {
        DumpSite::factory()->create(['name' => 'ŞİLE']);
        DumpSite::factory()->create(['name' => 'ARNAVUTKÖY']);

        $this->actingAs($this->admin)
            ->get(route('admin.dump-sites.index', ['q' => 'ARNAV']))
            ->assertOk()
            ->assertSee('ARNAVUTKÖY')
            ->assertDontSee('ŞİLE');
    }
}
