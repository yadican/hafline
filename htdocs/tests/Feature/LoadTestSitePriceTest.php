<?php

namespace Tests\Feature;

use App\Enums\PartyRole;
use App\Enums\Shift;
use App\Enums\SiteBilling;
use App\Models\DumpSite;
use App\Models\DumpSiteClassPrice;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\RouteClassPrice;
use App\Models\RouteSitePrice;
use App\Models\Site;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Services\Ledger\HaulLedger;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class LoadTestSitePriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeds_a_site_sale_price_above_the_class_price_on_every_route(): void
    {
        $catalog = $this->catalogWithRoutes();
        $routes = $catalog->allRoutes();

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $classPrices = RouteClassPrice::query()->where('route_id', $route->id)->get()->keyBy('vehicle_class_id');
            $sitePrices = RouteSitePrice::query()->where('route_id', $route->id)->get()->keyBy('vehicle_class_id');

            $this->assertSame(
                $classPrices->keys()->sort()->values()->all(),
                $sitePrices->keys()->sort()->values()->all(),
                'Her sınıf fiyatının şantiye satış fiyatı olmalı.',
            );

            foreach ($classPrices as $classId => $classPrice) {
                $this->assertGreaterThan(
                    (float) $classPrice->price,
                    (float) $sitePrices[$classId]->price,
                    'Şantiye satış fiyatı taşıyıcı sınıf fiyatının üstünde olmalı.',
                );
            }
        }

        $route = $routes->first();
        $classId = RouteClassPrice::query()->where('route_id', $route->id)->value('vehicle_class_id');
        $vehicle = Vehicle::factory()->create(['vehicle_class_id' => $classId]);
        $expected = RouteSitePrice::query()
            ->where('route_id', $route->id)
            ->where('vehicle_class_id', $classId)
            ->value('price');

        $this->assertEquals((float) $expected, (float) $catalog->quote($vehicle, $route)['site_price']);
    }

    public function test_operations_haul_debits_a_per_trip_site_and_skips_progress_sites(): void
    {
        $catalog = $this->catalogWithRoutes();
        $tir = VehicleClass::query()->where('name', 'TIR')->sole();
        $owner = Party::factory()->create();
        $vehicle = Vehicle::factory()->ownedBy($owner, '2026-01-01')->create([
            'vehicle_class_id' => $tir->id,
        ]);

        $perTripRoute = $catalog->allRoutes()->first(
            fn (HaulRoute $route) => $route->site->billing_mode === SiteBilling::PerTrip,
        );
        $progressRoute = $catalog->allRoutes()->first(
            fn (HaulRoute $route) => $route->site->billing_mode === SiteBilling::Progress,
        );

        $this->assertNotNull($perTripRoute);
        $this->assertNotNull($progressRoute);

        $perTripHaul = $this->seedHaulLikeOperations($catalog, $vehicle, $owner->id, $perTripRoute, '2026-03-10 09:00:00');
        $progressHaul = $this->seedHaulLikeOperations($catalog, $vehicle, $owner->id, $progressRoute, '2026-03-10 11:00:00');

        $this->assertSame($perTripRoute->site->party_id, $perTripHaul->site_party_id);
        $this->assertGreaterThan(0, (float) $perTripHaul->site_price);
        $this->assertNull($progressHaul->site_party_id);
        $this->assertGreaterThan(0, (float) $progressHaul->site_price);

        $siteDebit = LedgerEntry::query()
            ->where('party_id', $perTripRoute->site->party_id)
            ->where('source_type', 'haul')
            ->where('source_id', $perTripHaul->id)
            ->sole();

        $this->assertSame($perTripHaul->site_price, $siteDebit->debit);
        $this->assertSame('0.00', $siteDebit->credit);

        $this->assertFalse(
            LedgerEntry::query()
                ->where('party_id', $progressRoute->site->party_id)
                ->where('source_type', 'haul')
                ->exists(),
            'Hakediş şantiyesi taşıma fişinden borçlanmamalı.',
        );
    }

    private function catalogWithRoutes(): CatalogStage
    {
        $catalog = new CatalogStage(new Progress(null));

        $classes = collect([
            VehicleClass::factory()->create(['name' => 'TIR', 'hourly' => false]),
            VehicleClass::factory()->create(['name' => 'KAMYON', 'hourly' => false]),
            VehicleClass::factory()->hourly()->create(['name' => 'MAKİNE']),
        ])->keyBy('name');

        $sites = collect([
            $this->site(SiteBilling::PerTrip, 'Sefer Şantiyesi A'),
            $this->site(SiteBilling::PerTrip, 'Sefer Şantiyesi B'),
            $this->site(SiteBilling::Progress, 'Hakediş Şantiyesi A'),
            $this->site(SiteBilling::Progress, 'Hakediş Şantiyesi B'),
        ]);

        $dumps = DumpSite::factory()->count(3)->create();
        $dumpClassPrices = [];

        foreach ($dumps as $dump) {
            foreach ($classes->where('hourly', false) as $class) {
                $price = $class->name === 'TIR' ? 500 : 300;
                DumpSiteClassPrice::query()->create([
                    'dump_site_id' => $dump->id,
                    'vehicle_class_id' => $class->id,
                    'price' => $price,
                ]);
                $dumpClassPrices[$dump->id][$class->id] = (string) $price;
            }
        }

        (new ReflectionProperty(CatalogStage::class, 'classes'))->setValue($catalog, $classes);
        (new ReflectionProperty(CatalogStage::class, 'sites'))->setValue($catalog, $sites);
        (new ReflectionProperty(CatalogStage::class, 'dumpSites'))->setValue($catalog, $dumps);
        (new ReflectionProperty(CatalogStage::class, 'dumpClassPrices'))->setValue($catalog, $dumpClassPrices);

        (new ReflectionMethod(CatalogStage::class, 'seedRoutes'))->invoke($catalog);

        return $catalog;
    }

    private function site(SiteBilling $billing, string $name): Site
    {
        $party = Party::factory()->company()->withRole(PartyRole::Site)->create(['name' => $name]);

        return Site::query()->create([
            'name' => $name,
            'party_id' => $party->id,
            'billing_mode' => $billing->value,
        ]);
    }

    private function seedHaulLikeOperations(
        CatalogStage $catalog,
        Vehicle $vehicle,
        string $ownerId,
        HaulRoute $route,
        string $hauledAt,
    ): Haul {
        $at = Carbon::parse($hauledAt);
        $prices = $catalog->quote($vehicle, $route);

        $haul = Haul::query()->forceCreate([
            'id' => (string) Str::uuid7($at),
            'ticket_no' => 'T'.$at->format('Ym').fake()->unique()->numerify('######'),
            'vehicle_id' => $vehicle->id,
            'party_id' => $ownerId,
            'driver_name' => $vehicle->driver_name,
            'route_id' => $route->id,
            'site_id' => $route->site_id,
            'dump_site_id' => $route->dump_site_id,
            'dump_party_id' => $route->dumpSite->party_id,
            'site_party_id' => $route->site->perTripPartyId(),
            'tonnage' => 20,
            'shift' => Shift::Day,
            'hauled_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
            'price' => $prices['price'],
            'dump_price' => $prices['dump_price'],
            'site_price' => $prices['site_price'],
        ]);

        $haul->setRelation('site', $route->site);
        $haul->setRelation('dumpSite', $route->dumpSite);
        app(HaulLedger::class)->sync($haul);

        return $haul->fresh();
    }
}
