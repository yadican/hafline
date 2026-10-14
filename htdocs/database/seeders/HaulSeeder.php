<?php

namespace Database\Seeders;

use App\Enums\HaulShift;
use App\Models\DumpSite;
use App\Models\DumpSiteClassPrice;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\RouteClassPrice;
use App\Models\RouteVehiclePrice;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Models\VehicleOwner;
use App\Services\HaulPricing;
use Illuminate\Database\Seeder;

class HaulSeeder extends Seeder
{
    public function run(HaulPricing $pricing): void
    {
        $classes = collect(['TIR', 'KAMYON', 'DAMPERLİ'])
            ->mapWithKeys(fn (string $name) => [$name => VehicleClass::query()->create(['name' => $name])]);

        $sites = collect(['HABİPLER MEKAN', 'CENNET', 'YEŞİLPINAR DOSTLAR BİNEVLER', 'ABDULLAH MERTER'])
            ->map(fn (string $name) => Site::query()->create(['name' => $name]));

        $dumpSites = collect(['BOLLUCA', 'DÜNYA AĞAÇLI ESKİ', 'ALK'])
            ->map(fn (string $name) => DumpSite::query()->create(['name' => $name]));

        foreach ($dumpSites as $dumpSite) {
            foreach ($classes as $class) {
                DumpSiteClassPrice::query()->create([
                    'dump_site_id' => $dumpSite->id,
                    'vehicle_class_id' => $class->id,
                    'price' => fake()->numberBetween(200, 900),
                ]);
            }
        }

        $routes = collect();

        foreach ($sites as $site) {
            foreach ($dumpSites->random(2) as $dumpSite) {
                $route = HaulRoute::query()->create(['site_id' => $site->id, 'dump_site_id' => $dumpSite->id]);

                foreach ($classes as $name => $class) {
                    RouteClassPrice::query()->create([
                        'route_id' => $route->id,
                        'vehicle_class_id' => $class->id,
                        'price' => match ($name) {
                            'TIR' => fake()->numberBetween(3000, 5000),
                            'KAMYON' => fake()->numberBetween(2000, 3200),
                            default => fake()->numberBetween(1500, 2500),
                        },
                    ]);
                }

                $routes->push($route);
            }
        }

        $owners = collect([
            ['Hasan', 'Demir'], ['Hatice', 'Öztürk'], ['Fatma', 'Demir'],
            ['Emine', 'Şahin'], ['Mehmet', 'Kaya'], ['Ayşe', 'Yıldız'],
        ])->map(fn (array $name) => VehicleOwner::factory()->create(['first_name' => $name[0], 'last_name' => $name[1]]));

        $vehicles = collect([
            '34 ACM 61', '34 AL 4927', '34 BL 5918', '34 TA 1081', '34 KR 2210', '34 HD 7733',
            '34 ZE 4402', '34 NM 9150', '34 PT 3384', '34 YC 6271', '34 FG 1906', '34 SV 8845',
        ])->map(fn (string $plate) => Vehicle::query()->create([
            'plate' => $plate,
            'owner_id' => $owners->random()->id,
            'vehicle_class_id' => $classes->random()->id,
            'driver_name' => fake()->name(),
        ]));

        foreach ($vehicles->random(3) as $vehicle) {
            RouteVehiclePrice::query()->create([
                'route_id' => $routes->random()->id,
                'vehicle_id' => $vehicle->id,
                'price' => fake()->numberBetween(2500, 4500),
            ]);
        }

        $creator = User::query()->first();

        for ($i = 0; $i < 120; $i++) {
            $vehicle = $vehicles->random();
            $route = $routes->random();
            $enteredAt = now()->subMinutes(fake()->numberBetween(5, 60 * 24 * 12));

            Haul::query()->forceCreate([
                'ticket_no' => 'T'.$enteredAt->format('Ym').fake()->unique()->numerify('######'),
                'vehicle_id' => $vehicle->id,
                'owner_id' => $vehicle->owner_id,
                'driver_name' => $vehicle->driver_name,
                'route_id' => $route->id,
                'site_id' => $route->site_id,
                'dump_site_id' => $route->dump_site_id,
                'tonnage' => fake()->numberBetween(8, 30),
                'shift' => in_array((int) $enteredAt->format('G'), range(8, 19), true) ? HaulShift::Day : HaulShift::Night,
                'hauled_at' => $enteredAt->copy()->subMinutes(fake()->numberBetween(0, 90)),
                'created_by' => $creator?->id,
                'created_at' => $enteredAt,
                'updated_at' => $enteredAt,
                ...$pricing->quote($vehicle, $route),
            ]);
        }
    }
}
