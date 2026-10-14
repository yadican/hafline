<?php

namespace Tests\Feature\Admin;

use App\Models\DumpSite;
use App\Models\DumpSiteClassPrice;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\RouteClassPrice;
use App\Models\RouteVehiclePrice;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HaulTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Vehicle $vehicle;

    private HaulRoute $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->vehicle = Vehicle::factory()->create(['plate' => '34 ABC 123']);
        $this->route = HaulRoute::factory()->create();

        RouteClassPrice::query()->create([
            'route_id' => $this->route->id,
            'vehicle_class_id' => $this->vehicle->vehicle_class_id,
            'price' => 2500,
        ]);

        DumpSiteClassPrice::query()->create([
            'dump_site_id' => $this->route->dump_site_id,
            'vehicle_class_id' => $this->vehicle->vehicle_class_id,
            'price' => 400,
        ]);
    }

    public function test_guest_is_redirected_from_the_haul_page(): void
    {
        $this->get(route('admin.hauls.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_each_trip_is_stored_with_the_class_price(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload(['plate' => '34abc 123', 'trips' => 3]))
            ->assertRedirect(route('admin.hauls.index'))
            ->assertSessionHas('success', '3 sefer kaydedildi.');

        $hauls = Haul::query()->where('ticket_no', 'F-100')->get();

        $this->assertCount(3, $hauls);
        $this->assertTrue($hauls->every(fn (Haul $haul) => $haul->vehicle_id === $this->vehicle->id
            && $haul->price === '2500.00'
            && $haul->dump_price === '400.00'
            && $haul->owner_id === $this->vehicle->owner_id
            && $haul->created_by === $this->admin->id));
    }

    public function test_vehicle_specific_route_price_wins_over_the_class_price(): void
    {
        RouteVehiclePrice::query()->create([
            'route_id' => $this->route->id,
            'vehicle_id' => $this->vehicle->id,
            'price' => 3100,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload());

        $this->assertSame('3100.00', Haul::query()->sole()->price);
    }

    public function test_duplicate_ticket_unknown_plate_and_missing_route_are_rejected(): void
    {
        Haul::factory()->create(['ticket_no' => 'F-100']);
        $otherDumpSite = DumpSite::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload([
                'plate' => '06 ZZ 999',
                'dump_site_id' => $otherDumpSite->id,
            ]))
            ->assertSessionHasErrors(['ticket_no', 'plate', 'dump_site_id']);

        $this->assertDatabaseCount('hauls', 1);
    }

    public function test_update_requotes_the_price(): void
    {
        $haul = Haul::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'route_id' => $this->route->id,
            'price' => 10,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.hauls.update', $haul), $this->payload([
                'ticket_no' => $haul->ticket_no,
                'tonnage' => 22,
            ]))
            ->assertRedirect(route('admin.hauls.index'))
            ->assertSessionHas('success');

        $haul->refresh();
        $this->assertSame('2500.00', $haul->price);
        $this->assertSame(22, $haul->tonnage);
    }

    public function test_admin_can_delete_a_haul(): void
    {
        $haul = Haul::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.hauls.destroy', $haul))
            ->assertRedirect(route('admin.hauls.index'));

        $this->assertSoftDeleted($haul);
    }

    public function test_list_shows_recent_hauls_by_default_and_filters_on_search(): void
    {
        Haul::factory()->create(['ticket_no' => 'RECENT-1', 'vehicle_id' => $this->vehicle->id]);
        Haul::factory()->create(['ticket_no' => 'OLD-1', 'created_at' => now()->subDays(10)]);

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index'))
            ->assertOk()
            ->assertSee('RECENT-1')
            ->assertDontSee('OLD-1');

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index', ['f' => ['ticket' => 'OLD%']]))
            ->assertOk()
            ->assertSee('OLD-1')
            ->assertDontSee('RECENT-1');

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index', ['f' => ['plate' => 'abc12']]))
            ->assertOk()
            ->assertSee('RECENT-1')
            ->assertDontSee('OLD-1');
    }

    public function test_list_sorts_by_a_whitelisted_column_in_both_directions(): void
    {
        Haul::factory()->create(['ticket_no' => 'CHEAP', 'price' => 1000]);
        Haul::factory()->create(['ticket_no' => 'PRICEY', 'price' => 9000]);
        Haul::factory()->create(['ticket_no' => 'MIDDLE', 'price' => 5000]);

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index', ['sort' => 'price', 'dir' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['CHEAP', 'MIDDLE', 'PRICEY'])
            ->assertSee('aria-sort="ascending"', false);

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index', ['sort' => 'price', 'dir' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['PRICEY', 'MIDDLE', 'CHEAP'])
            ->assertSee('aria-sort="descending"', false);
    }

    public function test_list_sorts_by_a_related_column(): void
    {
        $zeta = HaulRoute::factory()->create(['site_id' => Site::factory()->create(['name' => 'ZETA ŞANTİYE'])->id]);
        $alfa = HaulRoute::factory()->create(['site_id' => Site::factory()->create(['name' => 'ALFA ŞANTİYE'])->id]);

        Haul::factory()->create(['ticket_no' => 'AT-ZETA', 'route_id' => $zeta->id]);
        Haul::factory()->create(['ticket_no' => 'AT-ALFA', 'route_id' => $alfa->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index', ['sort' => 'site']))
            ->assertOk()
            ->assertSeeInOrder(['AT-ALFA', 'AT-ZETA']);
    }

    public function test_unknown_sort_column_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index', ['sort' => 'driver_name; drop table hauls']))
            ->assertRedirect(route('admin.hauls.index'));
    }

    public function test_create_page_is_displayed(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.hauls.create'))
            ->assertOk()
            ->assertSee('Yeni taşıma fişi')
            ->assertSee($this->route->site->name);
    }

    public function test_edit_page_prefills_the_form(): void
    {
        $haul = Haul::factory()->create(['vehicle_id' => $this->vehicle->id, 'route_id' => $this->route->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.edit', $haul))
            ->assertOk()
            ->assertSee($haul->ticket_no.' numaralı fişi güncelleyin', false)
            ->assertSee('value="34 ABC 123"', false);
    }

    public function test_lookups_return_dump_sites_for_a_site_and_matching_vehicles(): void
    {
        HaulRoute::factory()->create(['site_id' => Site::factory()->create()->id]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.hauls.lookups.dump-sites', ['site' => $this->route->site_id]))
            ->assertOk()
            ->assertExactJson([['id' => $this->route->dump_site_id, 'name' => $this->route->dumpSite->name]]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.hauls.lookups.vehicles', ['q' => '34 abc']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.value', '34 ABC 123')
            ->assertJsonStructure([['value', 'meta']]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'ticket_no' => 'F-100',
            'plate' => '34 ABC 123',
            'trips' => 1,
            'hauled_at' => now()->format('Y-m-d\TH:i'),
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->route->dump_site_id,
            'tonnage' => 18,
            'shift' => 'day',
            ...$overrides,
        ];
    }
}
