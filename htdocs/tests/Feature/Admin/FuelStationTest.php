<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Models\FuelStation;
use App\Models\Party;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelStationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_guest_is_redirected_from_the_station_list(): void
    {
        $this->get(route('admin.fuel-stations.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_a_station(): void
    {
        $site = Site::factory()->create(['name' => 'TUZLA']);
        $second = Site::factory()->create(['name' => 'KARTAL']);
        $operator = Party::factory()->company()->create(['name' => 'TUZLA AKARYAKIT LTD']);
        $other = Party::factory()->company()->create(['name' => 'PENDİK PETROL AŞ']);

        $this->actingAs($this->admin)->get(route('admin.fuel-stations.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.fuel-stations.store'), [
                'name' => 'OPET TUZLA',
                'party_id' => $operator->id,
                'unit_price' => '47.1234',
                'site_ids' => [$site->id, $second->id],
                'active' => '1',
            ])
            ->assertRedirect(route('admin.fuel-stations.index'))
            ->assertSessionHas('success');

        $station = FuelStation::query()->where('name', 'OPET TUZLA')->firstOrFail();
        $this->assertSame('47.1234', $station->unit_price);
        $this->assertEqualsCanonicalizing([$site->id, $second->id], $station->sites->pluck('id')->all());
        $this->assertTrue($station->party_id === $operator->id && $operator->fresh()->hasRole(PartyRole::Fuel));

        $this->actingAs($this->admin)
            ->get(route('admin.fuel-stations.index'))
            ->assertSee('47,1234 TL/lt')
            ->assertSee('TUZLA AKARYAKIT LTD')
            ->assertSee('KARTAL, TUZLA');

        $this->actingAs($this->admin)->get(route('admin.fuel-stations.edit', $station))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.fuel-stations.update', $station), [
                'name' => 'OPET TUZLA',
                'party_id' => $other->id,
                'unit_price' => '48',
                'site_ids' => [$second->id],
                'active' => '1',
            ])
            ->assertRedirect(route('admin.fuel-stations.index'));

        $station->refresh();
        $this->assertSame('48.0000', $station->unit_price);
        $this->assertSame([$second->id], $station->sites()->pluck('sites.id')->all());
        $this->assertSame($other->id, $station->party_id);
        $this->assertTrue($other->fresh()->hasRole(PartyRole::Fuel));

        $this->actingAs($this->admin)
            ->delete(route('admin.fuel-stations.destroy', $station))
            ->assertRedirect(route('admin.fuel-stations.index'));

        $this->assertSoftDeleted($station);
    }

    public function test_invalid_station_is_not_stored(): void
    {
        FuelStation::factory()->create(['name' => 'SHELL']);

        $this->actingAs($this->admin)
            ->post(route('admin.fuel-stations.store'), ['name' => 'SHELL', 'unit_price' => '1.23456'])
            ->assertSessionHasErrors(['name', 'party_id', 'unit_price']);

        $this->actingAs($this->admin)
            ->post(route('admin.fuel-stations.store'), ['name' => 'BP', 'unit_price' => ''])
            ->assertSessionHasErrors('unit_price');

        $this->assertDatabaseCount('fuel_stations', 1);
    }

    public function test_list_filters_by_site(): void
    {
        $site = Site::factory()->create();
        FuelStation::factory()->hasAttached($site)->create(['name' => 'ŞANTİYE TANKI']);
        FuelStation::factory()->hasAttached(Site::factory())->create(['name' => 'DIŞ İSTASYON']);

        $this->actingAs($this->admin)
            ->get(route('admin.fuel-stations.index', ['site' => $site->id, 'sort' => 'unit_price', 'dir' => 'desc']))
            ->assertOk()
            ->assertSee('ŞANTİYE TANKI')
            ->assertDontSee('DIŞ İSTASYON');
    }

    public function test_list_filters_by_operator(): void
    {
        $operator = Party::factory()->company()->withRole(PartyRole::Fuel)->create(['name' => 'KUZEY PETROL']);
        FuelStation::factory()->create(['name' => 'KUZEY 1', 'party_id' => $operator->id]);
        FuelStation::factory()->create(['name' => 'GÜNEY 1']);

        $this->actingAs($this->admin)
            ->get(route('admin.fuel-stations.index', ['party' => $operator->id, 'sort' => 'party']))
            ->assertOk()
            ->assertSee('KUZEY 1')
            ->assertDontSee('GÜNEY 1');
    }

    public function test_operator_of_a_station_keeps_its_fuel_role_and_cannot_be_deleted(): void
    {
        $station = FuelStation::factory()->create();
        $operator = $station->party;

        $this->actingAs($this->admin)
            ->put(route('admin.parties.update', $operator), ['title' => $operator->name, 'roles' => []])
            ->assertSessionHasNoErrors();

        $this->assertTrue($operator->fresh()->hasRole(PartyRole::Fuel));

        $this->actingAs($this->admin)
            ->delete(route('admin.parties.destroy', $operator))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($operator);
    }

    public function test_a_site_can_have_many_stations_and_deleted_site_links_are_kept(): void
    {
        $site = Site::factory()->create();
        $closed = Site::factory()->create();
        $first = FuelStation::factory()->hasAttached($site)->hasAttached($closed)->create();
        $second = FuelStation::factory()->hasAttached($site)->create();
        $closed->delete();

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $site->fuelStations()->pluck('fuel_stations.id')->all());

        $this->actingAs($this->admin)
            ->put(route('admin.fuel-stations.update', $first), [
                'name' => $first->name,
                'party_id' => $first->party_id,
                'unit_price' => '45',
                'site_ids' => [],
                'active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([$closed->id], $first->sites()->pluck('sites.id')->all());

        $this->actingAs($this->admin)
            ->post(route('admin.fuel-stations.store'), ['name' => 'X', 'party_id' => $first->party_id, 'unit_price' => '1', 'site_ids' => [$closed->id]])
            ->assertSessionHasErrors('site_ids.0');
    }
}
