<?php

namespace Database\Seeders\LoadTest;

use App\Enums\PartyRole;
use App\Models\Bank;
use App\Models\DumpSite;
use App\Models\DumpSiteClassPrice;
use App\Models\FuelStation;
use App\Models\HaulRoute;
use App\Models\Party;
use App\Models\RouteClassPrice;
use App\Models\RouteVehiclePrice;
use App\Models\Site;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Database\Seeders\LoadTest\Support\Progress;
use Database\Seeders\LoadTest\Support\TurkishData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Master data: banks, parties, sites, dumps, routes, fuel stations, vehicles + ownership history.
 */
final class CatalogStage
{
    public const SITE_COUNT = 90;

    public const DUMP_COUNT = 45;

    public const HAUL_VEHICLE_COUNT = 200;

    public const MACHINE_COUNT = 50;

    /** @var array<string, Collection<int, Party>> */
    private array $partiesByRole = [];

    /** @var Collection<int, Site> */
    private Collection $sites;

    /** @var Collection<int, DumpSite> */
    private Collection $dumpSites;

    /** @var Collection<int, HaulRoute> */
    private Collection $routes;

    /** @var Collection<int, FuelStation> */
    private Collection $stations;

    /** @var Collection<int, Vehicle> */
    private Collection $haulVehicles;

    /** @var Collection<int, Vehicle> */
    private Collection $machines;

    /** @var Collection<int, VehicleClass> */
    private Collection $classes;

    /** @var array<string, list<array{party_id: string, started_on: string, ended_on: ?string}>> */
    private array $ownershipIndex = [];

    /** @var array<string, list<array{hourly_rate: string, started_on: string, ended_on: ?string}>> */
    private array $rateIndex = [];

    /** @var array<string, array<string, string>> routeId => classId => price */
    private array $routeClassPrices = [];

    /** @var array<string, array<string, string>> dumpId => classId => price */
    private array $dumpClassPrices = [];

    /** @var array<string, array<string, string>> routeId => vehicleId => price */
    private array $routeVehiclePrices = [];

    /** @var array<string, true> */
    private array $usedPlates = [];

    public function __construct(private Progress $progress) {}

    public function run(Carbon $fleetSince): self
    {
        $this->classes = VehicleClass::query()->get()->keyBy('name');
        $this->seedBanks();
        $this->seedParties();
        $this->seedSites();
        $this->seedDumpSites();
        $this->seedRoutes();
        $this->seedFuelStations();
        $this->seedVehicles($fleetSince);

        return $this;
    }

    /** @return Collection<int, Party> */
    public function partiesFor(PartyRole $role): Collection
    {
        return $this->partiesByRole[$role->value] ?? collect();
    }

    /** @return Collection<int, Site> */
    public function allSites(): Collection
    {
        return $this->sites;
    }

    /** @return Collection<int, DumpSite> */
    public function allDumpSites(): Collection
    {
        return $this->dumpSites;
    }

    /** @return Collection<int, HaulRoute> */
    public function allRoutes(): Collection
    {
        return $this->routes;
    }

    /** @return Collection<int, FuelStation> */
    public function allStations(): Collection
    {
        return $this->stations;
    }

    /** @return Collection<int, Vehicle> */
    public function haulVehicles(): Collection
    {
        return $this->haulVehicles;
    }

    /** @return Collection<int, Vehicle> */
    public function machines(): Collection
    {
        return $this->machines;
    }

    /** @return Collection<int, Vehicle> */
    public function allVehicles(): Collection
    {
        return $this->haulVehicles->concat($this->machines)->values();
    }

    public function ownerOn(string $vehicleId, Carbon $day): ?string
    {
        $date = $day->toDateString();

        foreach ($this->ownershipIndex[$vehicleId] ?? [] as $row) {
            if ($row['started_on'] <= $date && ($row['ended_on'] === null || $row['ended_on'] >= $date)) {
                return $row['party_id'];
            }
        }

        return null;
    }

    public function rateOn(string $vehicleId, Carbon $day): ?string
    {
        $date = $day->toDateString();

        foreach ($this->rateIndex[$vehicleId] ?? [] as $row) {
            if ($row['started_on'] <= $date && ($row['ended_on'] === null || $row['ended_on'] >= $date)) {
                return $row['hourly_rate'];
            }
        }

        return null;
    }

    /**
     * @return array{price: string, dump_price: string}
     */
    public function quote(Vehicle $vehicle, HaulRoute $route): array
    {
        $vehiclePrice = $this->routeVehiclePrices[$route->id][$vehicle->id] ?? null;
        $classPrice = $vehiclePrice ?? ($this->routeClassPrices[$route->id][$vehicle->vehicle_class_id] ?? '0');
        $dumpPrice = $this->dumpClassPrices[$route->dump_site_id][$vehicle->vehicle_class_id] ?? '0';

        return [
            'price' => (string) ($vehiclePrice ?? $classPrice),
            'dump_price' => (string) $dumpPrice,
        ];
    }

    private function seedBanks(): void
    {
        $this->progress->stage('Bankalar (TR)');

        $names = TurkishData::banks();
        foreach ($names as $index => $name) {
            Bank::query()->firstOrCreate(['name' => $name], [
                'representative' => TurkishData::personName(),
                'phone' => fake()->numerify('0212#######'),
                'account_no' => sprintf('TR%02d%022d', ($index % 90) + 10, 100000000000000000 + $index),
            ]);
            $this->progress->batch($index + 1, count($names), 'banka');
        }

        $this->progress->done(count($names).' banka');
    }

    private function seedParties(): void
    {
        $this->progress->stage('Cari kişiler ve firmalar');

        $personTargets = [
            PartyRole::VehicleOwner->value => 70,
            PartyRole::Employee->value => 55,
            PartyRole::MachineOperator->value => 50,
        ];

        $companyTargets = [
            PartyRole::Customer->value => 70,
            PartyRole::Supplier->value => 55,
            PartyRole::DumpOperator->value => 40,
            PartyRole::Fuel->value => 35,
            PartyRole::Market->value => 40,
            PartyRole::VehicleOwner->value => 65,
        ];

        foreach ($personTargets as $role => $count) {
            $this->partiesByRole[$role] = collect();
            for ($i = 0; $i < $count; $i++) {
                $party = Party::factory()->person()->withRole(PartyRole::from($role))->create([
                    'name' => TurkishData::personName(),
                    'phone' => fake()->numerify('05#########'),
                    'address' => fake()->randomElement(TurkishData::cityAreas()).' / Türkiye',
                ]);
                $this->partiesByRole[$role]->push($party);
            }
            $this->progress->note("kişi · {$role}: {$count}");
        }

        // Mantıklı çoklu roller: bazı araç sahipleri operatör, bazı personel operatör.
        foreach ($this->partiesByRole[PartyRole::VehicleOwner->value]->random(12) as $owner) {
            $owner->assignRole(PartyRole::MachineOperator);
            $this->partiesByRole[PartyRole::MachineOperator->value]->push($owner);
        }

        foreach ($this->partiesByRole[PartyRole::Employee->value]->random(10) as $employee) {
            $employee->assignRole(PartyRole::MachineOperator);
            $this->partiesByRole[PartyRole::MachineOperator->value]->push($employee);
        }

        $this->partiesByRole[PartyRole::MachineOperator->value] = $this->partiesByRole[PartyRole::MachineOperator->value]->unique('id')->values();

        foreach ($companyTargets as $role => $count) {
            $this->partiesByRole[$role] = ($this->partiesByRole[$role] ?? collect());
            $sector = match ($role) {
                PartyRole::Customer->value => 'YAPI',
                PartyRole::Supplier->value => 'TEDARİK',
                PartyRole::DumpOperator->value => 'DÖKÜM',
                PartyRole::Fuel->value => 'AKARYAKIT',
                PartyRole::Market->value => 'PİYASA',
                PartyRole::VehicleOwner->value => 'NAKLİYE',
                default => '',
            };

            for ($i = 0; $i < $count; $i++) {
                $party = Party::factory()->company()->withRole(PartyRole::from($role))->create([
                    'name' => TurkishData::companyTitle($sector),
                    'phone' => fake()->numerify('021########'),
                    'address' => fake()->randomElement(TurkishData::cityAreas()).' OSB / Türkiye',
                ]);

                $party->company?->update([
                    'tax_office' => fake()->randomElement(TurkishData::cityAreas()),
                    'tax_number' => str_pad((string) fake()->unique()->numberBetween(1000000000, 1999999999), 10, '0', STR_PAD_LEFT),
                ]);

                $this->partiesByRole[$role]->push($party);
            }

            $this->progress->note("firma · {$role}: {$count}");
        }

        // Bazı firmalar mantıklı şekilde birden fazla rolde.
        foreach ($this->partiesByRole[PartyRole::Supplier->value]->random(8) as $supplier) {
            $supplier->assignRole(PartyRole::Market);
            $this->partiesByRole[PartyRole::Market->value]->push($supplier);
        }

        foreach ($this->partiesByRole[PartyRole::Fuel->value]->random(5) as $fuel) {
            $fuel->assignRole(PartyRole::Supplier);
            $this->partiesByRole[PartyRole::Supplier->value]->push($fuel);
        }

        foreach ([PartyRole::Supplier, PartyRole::Market, PartyRole::VehicleOwner] as $role) {
            $this->partiesByRole[$role->value] = $this->partiesByRole[$role->value]->unique('id')->values();
        }

        $this->companyGroups();
        $this->progress->done('cari master hazır');
    }

    private function companyGroups(): void
    {
        $rootId = (string) Str::uuid7();
        $now = now();

        DB::table('company_groups')->insert([
            'id' => $rootId,
            'parent_id' => null,
            'name' => 'Hafline Operasyon Grubu',
            'sort_order' => 0,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $children = collect(['Nakliye', 'Döküm', 'Akaryakıt', 'Tedarik'])->map(function (string $name, int $i) use ($rootId, $now) {
            $id = (string) Str::uuid7();
            DB::table('company_groups')->insert([
                'id' => $id,
                'parent_id' => $rootId,
                'name' => $name,
                'sort_order' => $i + 1,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $id;
        });

        Party::query()->where('type', 'company')->whereHas('company', fn ($q) => $q->whereNull('company_group_id'))
            ->with('company')
            ->each(function (Party $party, int $index) use ($children): void {
                $party->company?->update(['company_group_id' => $children[$index % $children->count()]]);
            });
    }

    private function seedSites(): void
    {
        $this->progress->stage('Şantiyeler');

        $names = collect(TurkishData::siteNames())->shuffle()->take(self::SITE_COUNT)->values();
        $this->sites = collect();

        $customers = $this->partiesFor(PartyRole::Customer);
        $suppliers = $this->partiesFor(PartyRole::Supplier);
        $fuels = $this->partiesFor(PartyRole::Fuel);
        $markets = $this->partiesFor(PartyRole::Market);

        foreach ($names as $index => $name) {
            $site = Site::query()->create(['name' => $name]);

            $site->parties()->attach($customers->random()->id, ['role' => PartyRole::Customer->value]);
            $site->parties()->attach($suppliers->random()->id, ['role' => PartyRole::Supplier->value]);
            $site->parties()->attach($fuels->random()->id, ['role' => PartyRole::Fuel->value]);

            if (fake()->boolean(70)) {
                $site->parties()->attach($markets->random()->id, ['role' => PartyRole::Market->value]);
            }

            $this->sites->push($site);
            $this->progress->batch($index + 1, self::SITE_COUNT, 'şantiye');
        }

        $this->progress->done($this->sites->count().' şantiye');
    }

    private function seedDumpSites(): void
    {
        $this->progress->stage('Döküm yerleri');

        $names = collect(TurkishData::dumpSiteNames())->shuffle()->take(self::DUMP_COUNT)->values();
        $operators = $this->partiesFor(PartyRole::DumpOperator);
        $haulClasses = $this->classes->where('hourly', false);
        $this->dumpSites = collect();

        foreach ($names as $index => $name) {
            $dump = DumpSite::query()->create([
                'name' => $name,
                'party_id' => $operators[$index % $operators->count()]->id,
            ]);

            foreach ($haulClasses as $class) {
                $price = match ($class->name) {
                    'TIR' => fake()->numberBetween(450, 950),
                    'KIRKAYAK' => fake()->numberBetween(350, 750),
                    'ON TEKER' => fake()->numberBetween(280, 620),
                    default => fake()->numberBetween(150, 400),
                };

                DumpSiteClassPrice::query()->create([
                    'dump_site_id' => $dump->id,
                    'vehicle_class_id' => $class->id,
                    'price' => $price,
                ]);

                $this->dumpClassPrices[$dump->id][$class->id] = (string) $price;
            }

            $this->dumpSites->push($dump);
            $this->progress->batch($index + 1, self::DUMP_COUNT, 'döküm');
        }

        $this->progress->done($this->dumpSites->count().' döküm yeri');
    }

    private function seedRoutes(): void
    {
        $this->progress->stage('Güzergahlar ve fiyatlar');

        $this->routes = collect();
        $haulClasses = $this->classes->where('hourly', false);
        $dumpChunks = $this->dumpSites->chunk((int) ceil($this->dumpSites->count() / 6))->values();
        $total = 0;
        $target = 0;

        foreach ($this->sites as $siteIndex => $site) {
            $region = $dumpChunks[$siteIndex % $dumpChunks->count()];
            $linked = $region->random(min(fake()->numberBetween(2, 4), $region->count()));
            $target += $linked->count();
        }

        foreach ($this->sites as $siteIndex => $site) {
            $region = $dumpChunks[$siteIndex % $dumpChunks->count()];
            $linked = $region->random(min(fake()->numberBetween(2, 4), $region->count()));

            foreach ($linked as $dump) {
                $route = HaulRoute::query()->create([
                    'site_id' => $site->id,
                    'dump_site_id' => $dump->id,
                    'bonus' => fake()->boolean(15) ? fake()->numberBetween(50, 200) : 0,
                ]);

                foreach ($haulClasses as $class) {
                    $price = match ($class->name) {
                        'TIR' => fake()->numberBetween(3200, 5200),
                        'KIRKAYAK' => fake()->numberBetween(2600, 4000),
                        'ON TEKER' => fake()->numberBetween(2100, 3400),
                        default => fake()->numberBetween(1200, 2200),
                    };

                    RouteClassPrice::query()->create([
                        'route_id' => $route->id,
                        'vehicle_class_id' => $class->id,
                        'price' => $price,
                    ]);

                    $this->routeClassPrices[$route->id][$class->id] = (string) $price;
                }

                $route->setRelation('dumpSite', $dump);
                $route->setRelation('site', $site);
                $this->routes->push($route);
                $total++;
                $this->progress->batch($total, max($target, 1), 'güzergah');
            }
        }

        $this->progress->done($this->routes->count().' güzergah (her şantiye 2–4 döküm)');
    }

    private function seedFuelStations(): void
    {
        $this->progress->stage('Akaryakıt istasyonları');

        $this->stations = collect();
        $fuelFirms = $this->partiesFor(PartyRole::Fuel);
        $created = 0;

        foreach ($fuelFirms as $index => $firm) {
            $stationCount = match (true) {
                $index < 6 => fake()->numberBetween(3, 5),
                $index < 14 => fake()->numberBetween(2, 3),
                default => 1,
            };

            for ($n = 1; $n <= $stationCount; $n++) {
                $brand = fake()->randomElement(TurkishData::fuelBrandNames());
                $onSite = fake()->boolean(55);
                $station = FuelStation::query()->create([
                    'name' => sprintf('%s %s %s', $brand, $onSite ? 'ŞANTİYE' : 'DIŞ', $n === 1 ? 'MERKEZ' : chr(64 + $n)),
                    'party_id' => $firm->id,
                    'unit_price' => fake()->randomFloat(2, 44.50, 52.80),
                ]);

                if ($onSite) {
                    $linkedSites = $this->sites->random(min(fake()->numberBetween(1, 4), $this->sites->count()));
                    $station->sites()->attach($linkedSites->pluck('id'));
                    $station->setRelation('sites', $linkedSites->values());
                } else {
                    $station->setRelation('sites', collect());
                }

                $this->stations->push($station);
                $created++;
            }
        }

        $this->progress->done($created.' istasyon (şantiye içi + dışı)');
    }

    private function seedVehicles(Carbon $fleetSince): void
    {
        $this->progress->stage('Araçlar ve sahiplik geçmişi');

        $owners = $this->partiesFor(PartyRole::VehicleOwner)->values();
        $haulClasses = $this->classes->whereIn('name', ['TIR', 'ON TEKER', 'KIRKAYAK', 'TAKSİ'])->values();
        $machineClass = $this->classes->get('MAKİNE');

        $this->haulVehicles = collect();
        $weights = ['TIR' => 70, 'ON TEKER' => 55, 'KIRKAYAK' => 45, 'TAKSİ' => 30];
        $classPool = collect();
        foreach ($weights as $name => $count) {
            $class = $haulClasses->firstWhere('name', $name);
            if ($class) {
                $classPool = $classPool->concat(array_fill(0, $count, $class));
            }
        }
        $classPool = $classPool->shuffle()->values();

        for ($i = 0; $i < self::HAUL_VEHICLE_COUNT; $i++) {
            $class = $classPool[$i % $classPool->count()];
            $owner = $owners[$i % $owners->count()];
            $started = $fleetSince->copy()->subDays(fake()->numberBetween(0, 40));

            $vehicle = Vehicle::factory()->ownedBy($owner, $started->toDateString())->create([
                'plate' => $this->uniquePlate(),
                'vehicle_class_id' => $class->id,
                'driver_name' => TurkishData::personName(),
            ]);
            $vehicle->setRelation('vehicleClass', $class);

            $this->ownershipIndex[$vehicle->id] = [[
                'party_id' => $owner->id,
                'started_on' => $started->toDateString(),
                'ended_on' => null,
            ]];

            if (fake()->boolean(28)) {
                $this->transfer($vehicle, $owners->random(), $fleetSince->copy()->addDays(fake()->numberBetween(40, 120)));
            }

            if (fake()->boolean(8)) {
                $this->transfer($vehicle, $owners->random(), $fleetSince->copy()->addDays(fake()->numberBetween(130, 160)));
            }

            $this->haulVehicles->push($vehicle);
            $this->progress->batch($i + 1, self::HAUL_VEHICLE_COUNT + self::MACHINE_COUNT, 'araç');
        }

        // Birkaç araca özel güzergah fiyatı
        foreach ($this->haulVehicles->random(min(24, $this->haulVehicles->count())) as $vehicle) {
            $route = $this->routes->random();
            $price = fake()->numberBetween(2500, 4800);
            RouteVehiclePrice::query()->create([
                'route_id' => $route->id,
                'vehicle_id' => $vehicle->id,
                'price' => $price,
            ]);
            $this->routeVehiclePrices[$route->id][$vehicle->id] = (string) $price;
        }

        $this->machines = collect();
        $machineOwners = $owners->shuffle()->values();

        for ($i = 0; $i < self::MACHINE_COUNT; $i++) {
            $owner = $machineOwners[$i % $machineOwners->count()];
            $started = $fleetSince->copy()->subDays(fake()->numberBetween(0, 30));
            $oldRate = fake()->randomElement([1100, 1300, 1500, 1700, 1900]);

            $machine = Vehicle::factory()->ownedBy($owner, $started->toDateString())->create([
                'plate' => $this->uniquePlate(),
                'vehicle_class_id' => $machineClass->id,
                'driver_name' => null,
            ]);
            $machine->setRelation('vehicleClass', $machineClass);

            $mid = $fleetSince->copy()->addMonths(3)->subDays(fake()->numberBetween(0, 10));
            $newRate = $oldRate + fake()->randomElement([150, 200, 250, 300]);
            $machine->rates()->create([
                'hourly_rate' => $oldRate,
                'started_on' => $started->toDateString(),
                'ended_on' => $mid->copy()->subDay()->toDateString(),
            ]);
            $machine->rates()->create([
                'hourly_rate' => $newRate,
                'started_on' => $mid->toDateString(),
            ]);

            $this->ownershipIndex[$machine->id] = [[
                'party_id' => $owner->id,
                'started_on' => $started->toDateString(),
                'ended_on' => null,
            ]];

            $this->rateIndex[$machine->id] = [
                [
                    'hourly_rate' => (string) $oldRate,
                    'started_on' => $started->toDateString(),
                    'ended_on' => $mid->copy()->subDay()->toDateString(),
                ],
                [
                    'hourly_rate' => (string) $newRate,
                    'started_on' => $mid->toDateString(),
                    'ended_on' => null,
                ],
            ];

            if (fake()->boolean(20)) {
                $this->transfer($machine, $owners->random(), $fleetSince->copy()->addDays(fake()->numberBetween(60, 140)));
            }

            $this->machines->push($machine);
            $this->progress->batch(self::HAUL_VEHICLE_COUNT + $i + 1, self::HAUL_VEHICLE_COUNT + self::MACHINE_COUNT, 'araç');
        }

        $this->progress->done(sprintf('%d taşıma + %d makine', $this->haulVehicles->count(), $this->machines->count()));
    }

    private function uniquePlate(?string $cityCode = null): string
    {
        $plate = '';
        $key = '';

        for ($attempt = 0; $attempt < 120; $attempt++) {
            $plate = TurkishData::plate($cityCode);
            $key = Vehicle::normalizePlate($plate);

            if (! isset($this->usedPlates[$key]) && ! $this->plateLooksSimilar($key)) {
                break;
            }
        }

        // Fallback: unique only (similarity relaxed after many attempts).
        while (isset($this->usedPlates[$key])) {
            $plate = TurkishData::plate($cityCode);
            $key = Vehicle::normalizePlate($plate);
        }

        $this->usedPlates[$key] = true;

        return $plate;
    }

    /**
     * Reject near-duplicates like 34 ABC 120 / 34 ABC 125 or same digits under same city.
     */
    private function plateLooksSimilar(string $key): bool
    {
        if (! preg_match('/^(\d{2})([A-Z]+)(\d+)$/', $key, $parts)) {
            return false;
        }

        [, $city, $letters, $digits] = $parts;
        $digitValue = (int) $digits;

        foreach (array_keys($this->usedPlates) as $existing) {
            if (! preg_match('/^(\d{2})([A-Z]+)(\d+)$/', $existing, $other)) {
                continue;
            }

            [, $otherCity, $otherLetters, $otherDigits] = $other;

            if ($city !== $otherCity) {
                continue;
            }

            // Same city + same letter block + close numbers → looks sequential.
            if ($letters === $otherLetters && abs($digitValue - (int) $otherDigits) < 40) {
                return true;
            }

            // Same city + identical number block + similar letters.
            if ($digits === $otherDigits && levenshtein($letters, $otherLetters) <= 1) {
                return true;
            }
        }

        return false;
    }

    private function transfer(Vehicle $vehicle, Party $to, Carbon $on): void
    {
        $current = $this->ownershipIndex[$vehicle->id] ?? [];
        if ($current === []) {
            return;
        }

        $last = count($current) - 1;
        $ended = $on->copy()->subDay()->toDateString();

        if ($current[$last]['started_on'] > $ended) {
            return;
        }

        $current[$last]['ended_on'] = $ended;
        $vehicle->ownerships()->whereNull('ended_on')->update(['ended_on' => $ended]);

        $to->assignRole(PartyRole::VehicleOwner);
        $vehicle->ownerships()->create([
            'party_id' => $to->id,
            'started_on' => $on->toDateString(),
        ]);

        $current[] = [
            'party_id' => $to->id,
            'started_on' => $on->toDateString(),
            'ended_on' => null,
        ];

        $this->ownershipIndex[$vehicle->id] = $current;
    }
}
