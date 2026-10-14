<?php

namespace Tests\Feature\Admin;

use App\Models\DumpSite;
use App\Models\DumpSiteClassPrice;
use App\Models\HaulRoute;
use App\Models\RouteClassPrice;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Services\HaulPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HaulRouteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_guest_is_redirected_from_the_route_list(): void
    {
        $this->get(route('admin.haul-routes.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_a_route_with_class_prices(): void
    {
        $site = Site::factory()->create();
        $dumpSite = DumpSite::factory()->create();
        $tir = VehicleClass::factory()->create(['name' => 'TIR']);
        $kamyon = VehicleClass::factory()->create(['name' => 'KAMYON']);

        $this->actingAs($this->admin)->get(route('admin.haul-routes.create'))
            ->assertOk()
            ->assertSee('class_prices['.$tir->id.']', false);

        $this->actingAs($this->admin)
            ->post(route('admin.haul-routes.store'), [
                'site_id' => $site->id,
                'dump_site_id' => $dumpSite->id,
                'price' => '',
                'bonus' => '150',
                'class_prices' => [$tir->id => '4200.50', $kamyon->id => ''],
                'active' => '1',
            ])
            ->assertRedirect(route('admin.haul-routes.index'))
            ->assertSessionHas('success');

        $route = HaulRoute::between($site->id, $dumpSite->id);
        $this->assertNotNull($route);
        $this->assertSame('0.00', $route->price);
        $this->assertSame('150.00', $route->bonus);
        $this->assertSame(['4200.50'], $route->classPrices()->pluck('price')->all());

        $this->actingAs($this->admin)
            ->get(route('admin.haul-routes.index'))
            ->assertSee('4.200,50 TL');
    }

    public function test_updating_class_prices_changes_removes_and_adds(): void
    {
        $route = HaulRoute::factory()->create();
        $tir = VehicleClass::factory()->create();
        $kamyon = VehicleClass::factory()->create();
        $damperli = VehicleClass::factory()->create();
        RouteClassPrice::query()->create(['route_id' => $route->id, 'vehicle_class_id' => $tir->id, 'price' => 4000]);
        RouteClassPrice::query()->create(['route_id' => $route->id, 'vehicle_class_id' => $kamyon->id, 'price' => 3000]);

        $this->actingAs($this->admin)->get(route('admin.haul-routes.edit', $route))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.haul-routes.update', $route), [
                'site_id' => $route->site_id,
                'dump_site_id' => $route->dump_site_id,
                'class_prices' => [$tir->id => '4500', $kamyon->id => '', $damperli->id => '2500'],
                'active' => '1',
            ])
            ->assertRedirect(route('admin.haul-routes.index'));

        $prices = $route->classPrices()->pluck('price', 'vehicle_class_id');
        $this->assertSame('4500.00', $prices[$tir->id]);
        $this->assertSame('2500.00', $prices[$damperli->id]);
        $this->assertArrayNotHasKey($kamyon->id, $prices->all());
    }

    public function test_a_site_and_dump_site_pair_can_only_have_one_route(): void
    {
        $existing = HaulRoute::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.haul-routes.store'), [
                'site_id' => $existing->site_id,
                'dump_site_id' => $existing->dump_site_id,
            ])
            ->assertSessionHasErrors(['dump_site_id' => 'Bu şantiye ile döküm yeri arasında zaten bir güzergah var.']);

        $this->actingAs($this->admin)
            ->put(route('admin.haul-routes.update', $existing), [
                'site_id' => $existing->site_id,
                'dump_site_id' => $existing->dump_site_id,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_invalid_prices_are_rejected(): void
    {
        $route = HaulRoute::factory()->create();
        $class = VehicleClass::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.haul-routes.update', $route), [
                'site_id' => $route->site_id,
                'dump_site_id' => $route->dump_site_id,
                'bonus' => '-5',
                'class_prices' => [$class->id => '12.345'],
            ])
            ->assertSessionHasErrors(['bonus', 'class_prices.'.$class->id]);
    }

    public function test_vehicle_prices_can_be_added_replaced_and_removed(): void
    {
        $route = HaulRoute::factory()->create();
        $vehicle = Vehicle::factory()->create(['plate' => '34 VP 100']);

        $this->actingAs($this->admin)
            ->post(route('admin.haul-routes.vehicle-prices.store', $route), ['vehicle_plate' => '34vp100', 'vehicle_price' => '3900'])
            ->assertRedirect(route('admin.haul-routes.edit', $route))
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post(route('admin.haul-routes.vehicle-prices.store', $route), ['vehicle_plate' => '34 VP 100', 'vehicle_price' => '4100'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['4100.00'], $route->vehiclePrices()->pluck('price')->all());

        $this->actingAs($this->admin)
            ->get(route('admin.haul-routes.edit', $route))
            ->assertSee('34 VP 100')
            ->assertSee('4.100,00 TL');

        $this->actingAs($this->admin)
            ->post(route('admin.haul-routes.vehicle-prices.store', $route), ['vehicle_plate' => '99 XX 999', 'vehicle_price' => '100'])
            ->assertSessionHasErrors('vehicle_plate');

        $price = $route->vehiclePrices()->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('admin.haul-routes.vehicle-prices.destroy', [$route, $price]))
            ->assertRedirect(route('admin.haul-routes.edit', $route));

        $this->assertSoftDeleted($price);
        $this->assertSame($vehicle->id, $price->vehicle_id);
    }

    public function test_vehicle_price_cannot_be_removed_through_another_route(): void
    {
        $route = HaulRoute::factory()->create();
        $other = HaulRoute::factory()->create();
        $price = $route->vehiclePrices()->create(['vehicle_id' => Vehicle::factory()->create()->id, 'price' => 100]);

        $this->actingAs($this->admin)
            ->delete(route('admin.haul-routes.vehicle-prices.destroy', [$other, $price]))
            ->assertNotFound();

        $this->assertNotSoftDeleted($price);
    }

    public function test_haul_pricing_uses_the_prices_entered_on_screen(): void
    {
        $route = HaulRoute::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $classId = $vehicle->vehicle_class_id;

        $this->actingAs($this->admin)->put(route('admin.haul-routes.update', $route), [
            'site_id' => $route->site_id,
            'dump_site_id' => $route->dump_site_id,
            'class_prices' => [$classId => '3000'],
            'active' => '1',
        ]);

        $this->actingAs($this->admin)->put(route('admin.dump-sites.update', $route->dump_site_id), [
            'name' => $route->dumpSite->name,
            'class_prices' => [$classId => '450'],
            'active' => '1',
        ])->assertSessionHasNoErrors();

        $pricing = app(HaulPricing::class);
        $this->assertSame(['price' => '3000.00', 'dump_price' => '450.00'], $pricing->quote($vehicle, $route->fresh()));

        $this->actingAs($this->admin)->post(route('admin.haul-routes.vehicle-prices.store', $route), [
            'vehicle_plate' => $vehicle->plate,
            'vehicle_price' => '3600',
        ]);

        $this->assertSame('3600.00', $pricing->quote($vehicle, $route->fresh())['price']);
        $this->assertSame(1, DumpSiteClassPrice::query()->count());
    }

    public function test_list_filters_by_site_and_deletes_routes(): void
    {
        $route = HaulRoute::factory()->create();
        $other = HaulRoute::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.haul-routes.index', ['site' => $route->site_id, 'sort' => 'price', 'dir' => 'desc']))
            ->assertOk()
            ->assertViewHas('haulRoutes', fn ($routes) => $routes->pluck('id')->all() === [$route->id]);

        $this->assertNotSame($route->site_id, $other->site_id);

        $this->actingAs($this->admin)
            ->delete(route('admin.haul-routes.destroy', $route))
            ->assertRedirect(route('admin.haul-routes.index'));

        $this->assertSoftDeleted($route);
    }
}
