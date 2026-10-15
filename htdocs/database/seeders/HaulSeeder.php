<?php

namespace Database\Seeders;

use App\Enums\PartyRole;
use App\Enums\Shift;
use App\Models\DumpSite;
use App\Models\DumpSiteClassPrice;
use App\Models\FuelStation;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Party;
use App\Models\RouteClassPrice;
use App\Models\RouteVehiclePrice;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Services\HaulPricing;
use App\Services\Ledger\HaulLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class HaulSeeder extends Seeder
{
    public function run(HaulPricing $pricing, HaulLedger $ledger): void
    {
        $this->callSilent(VehicleClassSeeder::class);
        $classes = VehicleClass::query()->whereIn('name', ['TIR', 'ON TEKER', 'KIRKAYAK'])->get()->keyBy('name');

        $sites = collect(['HABİPLER MEKAN', 'CENNET', 'YEŞİLPINAR DOSTLAR BİNEVLER', 'ABDULLAH MERTER'])
            ->map(fn (string $name) => Site::query()->create(['name' => $name]));

        $operators = collect(['BOLLUCA DÖKÜM SAHASI A.Ş.', 'AĞAÇLI MADENCİLİK LTD. ŞTİ.', 'ALK HAFRİYAT LTD. ŞTİ.'])
            ->map(fn (string $title) => Party::factory()->company()->withRole(PartyRole::DumpOperator)->create(['name' => $title]));

        $dumpSites = collect(['BOLLUCA', 'DÜNYA AĞAÇLI ESKİ', 'ALK'])
            ->map(fn (string $name, int $index) => DumpSite::query()->create(['name' => $name, 'party_id' => $operators[$index]->id]));

        $employers = collect(['KUZEY YAPI A.Ş.', 'MERTER KONUT LTD. ŞTİ.'])
            ->map(fn (string $title) => Party::factory()->company()->withRole(PartyRole::Customer)->create(['name' => $title]));
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create(['name' => 'KUM ÇAKIL TEDARİK LTD. ŞTİ.']);
        $fuel = Party::factory()->company()->withRole(PartyRole::Fuel)->create(['name' => 'ÖZ AKARYAKIT LTD. ŞTİ.']);

        foreach ($sites as $index => $site) {
            $site->parties()->attach($employers[$index % 2]->id, ['role' => PartyRole::Customer->value]);
            $site->parties()->attach($supplier->id, ['role' => PartyRole::Supplier->value]);
            $site->parties()->attach($fuel->id, ['role' => PartyRole::Fuel->value]);
        }

        FuelStation::query()->create(['name' => 'ÖZ AKARYAKIT MERKEZ', 'party_id' => $fuel->id, 'unit_price' => 47.25])
            ->sites()->attach($sites->take(2)->pluck('id'));
        FuelStation::query()->create(['name' => 'ÖZ AKARYAKIT SAHİL', 'party_id' => $fuel->id, 'unit_price' => 47.40])
            ->sites()->attach($sites->skip(1)->pluck('id'));

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
                            'KIRKAYAK' => fake()->numberBetween(2500, 3800),
                            default => fake()->numberBetween(2000, 3200),
                        },
                    ]);
                }

                $routes->push($route);
            }
        }

        $owners = collect(['Hasan Demir', 'Hatice Öztürk', 'Fatma Demir', 'Emine Şahin', 'Mehmet Kaya', 'Ayşe Yıldız'])
            ->map(fn (string $name) => Party::factory()->person()->create(['name' => $name]))
            ->push(Party::factory()->company()->create(['name' => 'DEMİR NAKLİYAT LTD. ŞTİ.']));

        $since = today()->subDays(30);

        $vehicles = collect([
            '34 ACM 61', '34 AL 4927', '34 BL 5918', '34 TA 1081', '34 KR 2210', '34 HD 7733',
            '34 ZE 4402', '34 NM 9150', '34 PT 3384', '34 YC 6271', '34 FG 1906', '34 SV 8845',
        ])->map(fn (string $plate, int $index) => Vehicle::factory()->ownedBy($owners[$index % $owners->count()], $since->toDateString())->create([
            'plate' => $plate,
            'vehicle_class_id' => $classes->random()->id,
            'driver_name' => fake()->name(),
        ]));

        $this->transfer($vehicles->first(), $owners->last(), today()->subDays(6)->toDateString());

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
            $hauledAt = $enteredAt->copy()->subMinutes(fake()->numberBetween(0, 90));

            $haul = Haul::query()->forceCreate([
                'id' => (string) Str::uuid7($enteredAt),
                'ticket_no' => 'T'.$enteredAt->format('Ym').fake()->unique()->numerify('######'),
                'vehicle_id' => $vehicle->id,
                'party_id' => $vehicle->ownershipOn($hauledAt)->party_id,
                'driver_name' => $vehicle->driver_name,
                'route_id' => $route->id,
                'site_id' => $route->site_id,
                'dump_site_id' => $route->dump_site_id,
                'dump_party_id' => $route->dumpSite->party_id,
                'tonnage' => fake()->numberBetween(8, 30),
                'shift' => in_array((int) $enteredAt->format('G'), range(8, 19), true) ? Shift::Day : Shift::Night,
                'hauled_at' => $hauledAt,
                'created_by' => $creator?->id,
                'created_at' => $enteredAt,
                'updated_at' => $enteredAt,
                ...$pricing->quote($vehicle, $route),
            ]);

            $ledger->sync($haul);
        }
    }

    private function transfer(Vehicle $vehicle, Party $to, string $on): void
    {
        $vehicle->currentOwnership->update(['ended_on' => Carbon::parse($on)->subDay()->toDateString()]);
        $to->assignRole(PartyRole::VehicleOwner);
        $vehicle->ownerships()->create(['party_id' => $to->id, 'started_on' => $on]);
    }
}
