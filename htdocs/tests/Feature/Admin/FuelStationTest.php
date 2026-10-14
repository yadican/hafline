<?php

namespace Tests\Feature\Admin;

use App\Models\FuelStation;
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

        $this->actingAs($this->admin)->get(route('admin.fuel-stations.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.fuel-stations.store'), [
                'name' => 'OPET TUZLA',
                'unit_price' => '47.1234',
                'site_id' => $site->id,
                'active' => '1',
            ])
            ->assertRedirect(route('admin.fuel-stations.index'))
            ->assertSessionHas('success');

        $station = FuelStation::query()->where('name', 'OPET TUZLA')->firstOrFail();
        $this->assertSame('47.1234', $station->unit_price);

        $this->actingAs($this->admin)
            ->get(route('admin.fuel-stations.index'))
            ->assertSee('47,1234 TL/lt')
            ->assertSee('TUZLA');

        $this->actingAs($this->admin)->get(route('admin.fuel-stations.edit', $station))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.fuel-stations.update', $station), [
                'name' => 'OPET TUZLA',
                'unit_price' => '48',
                'site_id' => '',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.fuel-stations.index'));

        $station->refresh();
        $this->assertSame('48.0000', $station->unit_price);
        $this->assertNull($station->site_id);

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
            ->assertSessionHasErrors(['name', 'unit_price']);

        $this->actingAs($this->admin)
            ->post(route('admin.fuel-stations.store'), ['name' => 'BP', 'unit_price' => ''])
            ->assertSessionHasErrors('unit_price');

        $this->assertDatabaseCount('fuel_stations', 1);
    }

    public function test_list_filters_by_site(): void
    {
        $site = Site::factory()->create();
        FuelStation::factory()->create(['name' => 'ŞANTİYE TANKI', 'site_id' => $site->id]);
        FuelStation::factory()->create(['name' => 'DIŞ İSTASYON']);

        $this->actingAs($this->admin)
            ->get(route('admin.fuel-stations.index', ['site' => $site->id, 'sort' => 'unit_price', 'dir' => 'desc']))
            ->assertOk()
            ->assertSee('ŞANTİYE TANKI')
            ->assertDontSee('DIŞ İSTASYON');
    }
}
