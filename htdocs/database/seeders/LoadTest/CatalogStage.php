<?php

namespace Database\Seeders\LoadTest;

use App\Enums\AccountType;
use App\Enums\CariEntryKind;
use App\Enums\CashDirection;
use App\Enums\FuelSourceType;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Enums\SiteBilling;
use App\Enums\StockItemUnit;
use App\Models\Bank;
use App\Models\CashCategory;
use App\Models\DumpSite;
use App\Models\DumpSiteClassPrice;
use App\Models\FuelStation;
use App\Models\HaulRoute;
use App\Models\Party;
use App\Models\RouteClassPrice;
use App\Models\RouteSitePrice;
use App\Models\RouteVehiclePrice;
use App\Models\Site;
use App\Models\StockItem;
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
    public const SITE_COUNT = 40;

    public const DUMP_COUNT = 15;

    /** Site groups that share nearby dump sites. */
    private const DUMP_REGIONS = 5;

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

    /** @var Collection<int, FuelStation> */
    private Collection $offSiteStations;

    /** @var Collection<int, Vehicle> */
    private Collection $haulVehicles;

    /** @var Collection<int, Vehicle> */
    private Collection $machines;

    /** @var Collection<int, VehicleClass> */
    private Collection $classes;

    /** @var Collection<int, Bank> */
    private Collection $banks;

    /** @var Collection<int, CashCategory>|null */
    private ?Collection $categories = null;

    /** @var Collection<int, StockItem> */
    private Collection $stockItems;

    private int $instrumentDoc = 0;

    /** @var array<string, list<array{party_id: string, started_on: string, ended_on: ?string}>> */
    private array $ownershipIndex = [];

    /** @var array<string, list<array{hourly_rate: string, started_on: string, ended_on: ?string}>> */
    private array $rateIndex = [];

    /** @var array<string, array<string, string>> routeId => classId => price */
    private array $routeClassPrices = [];

    /** @var array<string, array<string, string>> routeId => classId => site sale price */
    private array $routeSitePrices = [];

    /** @var array<string, array<string, string>> dumpId => classId => price */
    private array $dumpClassPrices = [];

    /** @var array<string, array<string, string>> routeId => vehicleId => price */
    private array $routeVehiclePrices = [];

    /** @var array<string, string> vehicleId => routeId with a negotiated price */
    private array $preferredRoutes = [];

    /** @var array<string, int> routeId => round-trip minutes (load, haul, dump, return) */
    private array $routeCycles = [];

    /** @var array<string, list<FuelStation>> siteId => on-site stations */
    private array $siteStations = [];

    /** @var array<string, true> */
    private array $usedPlates = [];

    public function __construct(private Progress $progress) {}

    public function run(Carbon $fleetSince, Carbon $from, Carbon $to): self
    {
        $this->classes = VehicleClass::query()->get()->keyBy('name');
        $this->seedBanks($from);
        $this->seedParties();
        $this->seedSites();
        $this->seedDumpSites();
        $this->seedRoutes();
        $this->seedFuelStations();
        $this->seedVehicles($fleetSince, $from, $to);
        $this->seedStockItems();

        return $this;
    }

    public function cycleMinutes(string $routeId): int
    {
        return $this->routeCycles[$routeId] ?? 80;
    }

    public function preferredRouteId(string $vehicleId): ?string
    {
        return $this->preferredRoutes[$vehicleId] ?? null;
    }

    /**
     * Station a vehicle fuels at while working on a site; off-site regulars keep their own pump.
     */
    public function stationFor(string $siteId, int $vehicleIndex, bool $offSiteRegular): FuelStation
    {
        if ($offSiteRegular && $this->offSiteStations->isNotEmpty()) {
            return $this->offSiteStations[$vehicleIndex % $this->offSiteStations->count()];
        }

        $onSite = $this->siteStations[$siteId] ?? [];

        return $onSite !== []
            ? $onSite[$vehicleIndex % count($onSite)]
            : $this->stations[$vehicleIndex % $this->stations->count()];
    }

    /** @return Collection<int, Party> */
    public function partiesFor(PartyRole $role): Collection
    {
        return $this->partiesByRole[$role->value] ?? collect();
    }

    /** @return Collection<int, Bank> */
    public function allBanks(): Collection
    {
        return $this->banks;
    }

    /** @return Collection<int, Bank> */
    public function banksOf(AccountType $type): Collection
    {
        return $this->banks->where('type', $type)->values();
    }

    /**
     * Account whose type the payment method moves money through (cash→kasa, transfer→banka, card→POS/kart).
     * Null when the method parks money elsewhere (çek/senet) or moves none (mahsup).
     */
    public function bankFor(PaymentMethod $method): ?Bank
    {
        $pool = collect($method->accountTypes())
            ->flatMap(fn (AccountType $type) => $this->banksOf($type))
            ->values();

        return $pool->isEmpty() ? null : $pool->random();
    }

    /**
     * Active cash categories that can be picked for money going out (expense) or coming in (income).
     *
     * @return Collection<int, CashCategory>
     */
    public function categoriesOf(CashDirection $direction): Collection
    {
        $this->categories ??= CashCategory::query()->where('active', true)->get();

        return $this->categories
            ->filter(fn (CashCategory $category) => in_array($category->direction, [$direction, CashDirection::Both], true))
            ->values();
    }

    /**
     * Category whose direction matches the movement (expense/income), including "both".
     */
    public function categoryFor(CashDirection $direction): ?CashCategory
    {
        $pool = $this->categoriesOf($direction);

        return $pool->isEmpty() ? null : $pool->random();
    }

    /**
     * Which category side a cari kind + debit/credit direction expects (kasa nakit is inverted).
     */
    public function categoryDirectionFor(string $kind, string $direction): CashDirection
    {
        if ($kind === CariEntryKind::Cash->value) {
            return $direction === 'debit' ? CashDirection::Income : CashDirection::Expense;
        }

        return $direction === 'credit' ? CashDirection::Income : CashDirection::Expense;
    }

    /**
     * Attach cash_category_id when the payload has a direction and no category yet.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function withCashCategory(array $payload): array
    {
        if (! empty($payload['cash_category_id']) || empty($payload['direction'])) {
            return $payload;
        }

        $payload['cash_category_id'] = $this->categoryFor(
            $this->categoryDirectionFor((string) ($payload['kind'] ?? ''), (string) $payload['direction']),
        )?->id;

        return $payload;
    }

    /**
     * Attach bank_id when the payload names a money-moving payment method and has no account yet.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function withPaymentAccount(array $payload): array
    {
        if (! empty($payload['bank_id'])) {
            return $payload;
        }

        $method = PaymentMethod::tryFrom((string) ($payload['payment_method'] ?? ''));

        if ($method === null || $method->accountTypes() === []) {
            return $payload;
        }

        $payload['bank_id'] = $this->bankFor($method)?->id;

        return $payload;
    }

    /**
     * Fill cheque_no and due_on when the payment is a cheque or promissory note.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function withInstrumentDetails(array $payload): array
    {
        $method = PaymentMethod::tryFrom((string) ($payload['payment_method'] ?? ''));

        if ($method === null || ! $method->hasDueDate()) {
            return $payload;
        }

        if (empty($payload['cheque_no'])) {
            $prefix = $method === PaymentMethod::PromissoryNote ? 'SN' : 'CK';
            $payload['cheque_no'] = $prefix.str_pad((string) (++$this->instrumentDoc), 6, '0', STR_PAD_LEFT);
        }

        if (empty($payload['due_on'])) {
            $occurred = Carbon::parse((string) ($payload['occurred_on'] ?? 'today'));
            $payload['due_on'] = $occurred->copy()->addDays(fake()->numberBetween(7, 90))->toDateString();
        }

        return $payload;
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

    /** @return Collection<int, FuelStation> */
    public function allTanks(): Collection
    {
        return $this->stations->filter(fn (FuelStation $station) => $station->isTank())->values();
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

    /** @return Collection<int, StockItem> */
    public function stockItems(): Collection
    {
        return $this->stockItems;
    }

    /** @return Collection<int, StockItem> */
    public function trackedStockItems(): Collection
    {
        return $this->stockItems->where('tracks_stock', true)->values();
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
     * @return array{price: string, dump_price: string, site_price: string}
     */
    public function quote(Vehicle $vehicle, HaulRoute $route): array
    {
        $vehiclePrice = $this->routeVehiclePrices[$route->id][$vehicle->id] ?? null;
        $classPrice = $vehiclePrice ?? ($this->routeClassPrices[$route->id][$vehicle->vehicle_class_id] ?? '0');
        $dumpPrice = $this->dumpClassPrices[$route->dump_site_id][$vehicle->vehicle_class_id] ?? '0';
        $sitePrice = $this->routeSitePrices[$route->id][$vehicle->vehicle_class_id] ?? '0';

        return [
            'price' => (string) ($vehiclePrice ?? $classPrice),
            'dump_price' => (string) $dumpPrice,
            'site_price' => (string) $sitePrice,
        ];
    }

    private function seedBanks(Carbon $openedOn): void
    {
        $this->progress->stage('Hesaplar (kasa / banka / POS / kart)');
        $this->banks = collect();

        $definitions = [
            ...$this->cashAccountDefinitions(),
            ...$this->bankAccountDefinitions(),
            ...$this->posAccountDefinitions(),
            ...$this->creditCardAccountDefinitions(),
        ];

        foreach ($definitions as $index => $definition) {
            $bank = Bank::query()->firstOrCreate(['name' => $definition['name']], [
                ...$definition,
                'opened_on' => $openedOn->toDateString(),
                'active' => true,
            ]);
            $this->banks->push($bank);
            $this->progress->batch($index + 1, count($definitions), 'hesap');
        }

        $counts = collect(AccountType::cases())
            ->map(fn (AccountType $type) => mb_strtolower($type->label(), 'UTF-8').': '.$this->banksOf($type)->count())
            ->implode(' · ');

        $this->progress->done($this->banks->count().' hesap ('.$counts.')');
    }

    /**
     * @return list<array{type: AccountType, name: string, opening_balance: string, phone?: string}>
     */
    private function cashAccountDefinitions(): array
    {
        return [
            ['type' => AccountType::Cash, 'name' => 'Merkez Kasa', 'opening_balance' => '125000.00', 'phone' => fake()->numerify('0216#######')],
            ['type' => AccountType::Cash, 'name' => 'Şantiye Kasası', 'opening_balance' => '42000.00', 'phone' => fake()->numerify('0262#######')],
            ['type' => AccountType::Cash, 'name' => 'Ofis Kasası', 'opening_balance' => '18500.00', 'phone' => fake()->numerify('0212#######')],
        ];
    }

    /**
     * @return list<array{type: AccountType, name: string, opening_balance: string, representative: string, phone: string, account_no: string, iban: string, branch: string}>
     */
    private function bankAccountDefinitions(): array
    {
        $names = TurkishData::banks();

        return array_map(fn (string $name, int $index) => [
            'type' => AccountType::Bank,
            'name' => $name,
            'branch' => fake()->randomElement(['Gebze', 'Pendik', 'Kadıköy', 'Ümraniye', 'Başakşehir', 'Merkez']),
            'representative' => TurkishData::personName(),
            'phone' => fake()->numerify('0212#######'),
            'account_no' => sprintf('%06d%04d', 100000 + $index, 1000 + ($index % 9000)),
            'iban' => sprintf('TR%02d%022d', ($index % 90) + 10, 100000000000000000 + $index),
            'opening_balance' => number_format(75000 + ($index * 12500), 2, '.', ''),
        ], $names, array_keys($names));
    }

    /**
     * @return list<array{type: AccountType, name: string, opening_balance: string, representative: string, phone: string, branch: string}>
     */
    private function posAccountDefinitions(): array
    {
        return [
            ['type' => AccountType::Pos, 'name' => 'Ziraat POS · Merkez', 'branch' => 'Gebze', 'representative' => TurkishData::personName(), 'phone' => fake()->numerify('0216#######'), 'opening_balance' => '0.00'],
            ['type' => AccountType::Pos, 'name' => 'İş Bankası POS · Şantiye', 'branch' => 'Pendik', 'representative' => TurkishData::personName(), 'phone' => fake()->numerify('0216#######'), 'opening_balance' => '0.00'],
            ['type' => AccountType::Pos, 'name' => 'Garanti POS · Ofis', 'branch' => 'Kadıköy', 'representative' => TurkishData::personName(), 'phone' => fake()->numerify('0216#######'), 'opening_balance' => '1250.00'],
        ];
    }

    /**
     * @return list<array{type: AccountType, name: string, opening_balance: string, representative: string, phone: string, account_no: string, branch: string}>
     */
    private function creditCardAccountDefinitions(): array
    {
        return [
            ['type' => AccountType::CreditCard, 'name' => 'Yapı Kredi Business', 'branch' => 'Kadıköy', 'representative' => TurkishData::personName(), 'phone' => fake()->numerify('0216#######'), 'account_no' => fake()->numerify('############'), 'opening_balance' => '-48500.00'],
            ['type' => AccountType::CreditCard, 'name' => 'Akbank Axess Ticari', 'branch' => 'Ümraniye', 'representative' => TurkishData::personName(), 'phone' => fake()->numerify('0216#######'), 'account_no' => fake()->numerify('############'), 'opening_balance' => '-27300.00'],
            ['type' => AccountType::CreditCard, 'name' => 'Garanti Bonus Business', 'branch' => 'Başakşehir', 'representative' => TurkishData::personName(), 'phone' => fake()->numerify('0216#######'), 'account_no' => fake()->numerify('############'), 'opening_balance' => '-15200.00'],
        ];
    }

    private function seedParties(): void
    {
        $this->progress->stage('Cari kişiler ve firmalar');

        $personTargets = [
            PartyRole::VehicleOwner->value => 70,
            PartyRole::Employee->value => 55,
            PartyRole::MachineOperator->value => 50,
        ];

        // Sized to 40 sites / 15 dumps so every firm has real activity.
        $companyTargets = [
            PartyRole::Customer->value => 28,
            PartyRole::Supplier->value => 30,
            PartyRole::DumpOperator->value => 10,
            PartyRole::Fuel->value => 14,
            PartyRole::Market->value => 20,
            PartyRole::VehicleOwner->value => 65,
            PartyRole::Commission->value => 18,
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
                PartyRole::Commission->value => 'REFERANS',
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

        // Bazı piyasa / müşteri firmaları aynı zamanda komisyoncu (referans).
        foreach ($this->partiesByRole[PartyRole::Market->value]->random(4) as $market) {
            $market->assignRole(PartyRole::Commission);
            $this->partiesByRole[PartyRole::Commission->value]->push($market);
        }

        foreach ($this->partiesByRole[PartyRole::Customer->value]->random(3) as $customer) {
            $customer->assignRole(PartyRole::Commission);
            $this->partiesByRole[PartyRole::Commission->value]->push($customer);
        }

        foreach ([PartyRole::Supplier, PartyRole::Market, PartyRole::VehicleOwner, PartyRole::Commission] as $role) {
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
        $this->partiesByRole[PartyRole::Site->value] = $this->partiesByRole[PartyRole::Site->value] ?? collect();

        $customers = $this->partiesFor(PartyRole::Customer)->values();
        $suppliers = $this->partiesFor(PartyRole::Supplier);
        $markets = $this->partiesFor(PartyRole::Market);

        foreach ($names as $index => $name) {
            // Own cari per site (company + site role); ~1/3 billed per trip, rest by progress bill.
            $cari = Party::factory()->company()->withRole(PartyRole::Site)->create([
                'name' => $name,
                'phone' => fake()->numerify('021########'),
                'address' => fake()->randomElement(TurkishData::cityAreas()).' / Türkiye',
            ]);
            $cari->company?->update([
                'tax_office' => fake()->randomElement(TurkishData::cityAreas()),
                'tax_number' => str_pad((string) fake()->unique()->numberBetween(1000000000, 1999999999), 10, '0', STR_PAD_LEFT),
            ]);
            $this->partiesByRole[PartyRole::Site->value]->push($cari);

            $site = Site::query()->create([
                'name' => $name,
                'party_id' => $cari->id,
                'billing_mode' => ($index % 3 === 0 ? SiteBilling::PerTrip : SiteBilling::Progress)->value,
            ]);

            // Round-robin so every customer owns at least one site; fuel firms follow their stations.
            $customerId = $customers[$index % $customers->count()]->id;
            $site->parties()->attach($customerId, ['role' => PartyRole::Customer->value]);
            $site->parties()->attach($suppliers->random()->id, ['role' => PartyRole::Supplier->value]);

            if (fake()->boolean(70)) {
                $site->parties()->attach($markets->random()->id, ['role' => PartyRole::Market->value]);
            }

            $this->sites->push($site);
            $this->progress->batch($index + 1, self::SITE_COUNT, 'şantiye');
        }

        $this->progress->done($this->sites->count().' şantiye (cari + faturalama)');
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
        $regions = $this->dumpSites->chunk((int) ceil($this->dumpSites->count() / self::DUMP_REGIONS))->values();
        $links = $this->sites->values()->map(function (Site $site, int $siteIndex) use ($regions): Collection {
            $region = $regions[$siteIndex % $regions->count()];

            return $region->random(min(fake()->numberBetween(2, 3), $region->count()));
        });
        $target = $links->sum(fn (Collection $linked) => $linked->count());
        $total = 0;

        foreach ($this->sites->values() as $siteIndex => $site) {
            foreach ($links[$siteIndex] as $dump) {
                $route = HaulRoute::query()->create([
                    'site_id' => $site->id,
                    'dump_site_id' => $dump->id,
                    'bonus' => fake()->boolean(15) ? fake()->numberBetween(50, 200) : 0,
                ]);

                $cycle = fake()->numberBetween(50, 110);
                $this->routeCycles[$route->id] = $cycle;

                foreach ($haulClasses as $class) {
                    $price = $this->routePrice($class->name, $cycle);
                    $sitePrice = $this->siteSalePrice($price);

                    RouteClassPrice::query()->create([
                        'route_id' => $route->id,
                        'vehicle_class_id' => $class->id,
                        'price' => $price,
                    ]);

                    RouteSitePrice::query()->create([
                        'route_id' => $route->id,
                        'vehicle_class_id' => $class->id,
                        'price' => $sitePrice,
                    ]);

                    $this->routeClassPrices[$route->id][$class->id] = (string) $price;
                    $this->routeSitePrices[$route->id][$class->id] = (string) $sitePrice;
                }

                $route->setRelation('dumpSite', $dump);
                $route->setRelation('site', $site);
                $this->routes->push($route);
                $total++;
                $this->progress->batch($total, max($target, 1), 'güzergah');
            }
        }

        $this->progress->done($this->routes->count().' güzergah (her şantiye 2–3 döküm, tur 50–110 dk)');
    }

    /**
     * Longer round trips pay more; heavier classes pay more per minute.
     */
    private function routePrice(string $className, int $cycleMinutes): int
    {
        [$base, $perMinute] = match ($className) {
            'TIR' => [2200, 26],
            'KIRKAYAK' => [1800, 20],
            'ON TEKER' => [1400, 17],
            default => [800, 11],
        };

        return (int) round(($base + $cycleMinutes * $perMinute) * fake()->randomFloat(2, 0.95, 1.05), -1);
    }

    /**
     * What the site is billed per trip; always above the carrier's class price.
     */
    private function siteSalePrice(int $classPrice): int
    {
        return (int) round($classPrice * fake()->randomFloat(2, 1.15, 1.40), -1);
    }

    private function seedFuelStations(): void
    {
        $this->progress->stage('Akaryakıt istasyonları');

        $this->stations = collect();
        $fuelFirms = $this->partiesFor(PartyRole::Fuel);
        $created = 0;

        foreach ($fuelFirms->values() as $index => $firm) {
            $stationCount = match (true) {
                $index < 4 => fake()->numberBetween(2, 3),
                $index < 9 => fake()->numberBetween(1, 2),
                default => 1,
            };

            for ($n = 1; $n <= $stationCount; $n++) {
                $brand = fake()->randomElement(TurkishData::fuelBrandNames());
                // Every firm keeps at least one on-site pump so it shows up on site reports.
                $onSite = $n === 1 || fake()->boolean(45);
                $unitPrice = fake()->randomFloat(2, 44.50, 52.80);
                // Purchase (firm credit on each receipt) sits below the sale price charged to the owner.
                $purchasePrice = round($unitPrice * fake()->randomFloat(2, 0.86, 0.94), 2);
                $station = FuelStation::query()->create([
                    'name' => sprintf('%s %s %s', $brand, $onSite ? 'ŞANTİYE' : 'DIŞ', $n === 1 ? 'MERKEZ' : chr(64 + $n)),
                    'type' => FuelSourceType::Station,
                    'party_id' => $firm->id,
                    'purchase_price' => $purchasePrice,
                    'unit_price' => $unitPrice,
                ]);

                if ($onSite) {
                    $linkedSites = $this->sites->random(min(fake()->numberBetween(1, 2), $this->sites->count()))->values();
                    $station->sites()->attach($linkedSites->pluck('id'));
                    $station->setRelation('sites', $linkedSites);
                } else {
                    $station->setRelation('sites', collect());
                }

                $this->stations->push($station);
                $created++;
            }
        }

        $onSiteStations = $this->stations->filter(fn (FuelStation $station) => $station->sites->isNotEmpty())->values();

        foreach ($this->sites as $site) {
            $serving = $onSiteStations->filter(fn (FuelStation $station) => $station->sites->contains('id', $site->id));

            if ($serving->isEmpty()) {
                $station = $onSiteStations->random();
                $station->sites()->attach($site->id);
                $station->setRelation('sites', $station->sites->push($site));
                $serving = collect([$station]);
            }

            $this->siteStations[$site->id] = $serving->values()->all();

            foreach ($serving->pluck('party_id')->unique() as $fuelPartyId) {
                $site->parties()->attach($fuelPartyId, ['role' => PartyRole::Fuel->value]);
            }
        }

        $this->offSiteStations = $this->stations->filter(
            fn (FuelStation $station) => ! $station->isTank() && $station->sites->isEmpty(),
        )->values();

        $tanks = $this->seedFieldPumps();

        $this->progress->done(sprintf(
            '%d istasyon (%d şantiye içi + %d dış) · %d saha pompası',
            $created,
            $onSiteStations->count(),
            $this->offSiteStations->count(),
            $tanks,
        ));
    }

    /**
     * Our own on-site pumps: no firm; stock comes from fills. Linked to a few sites each so
     * stationFor can hand vehicles a tank alongside firm stations.
     */
    private function seedFieldPumps(): int
    {
        $count = min(12, max(4, (int) floor($this->sites->count() / 4)));
        $pool = $this->sites->shuffle()->values();
        $cursor = 0;

        for ($i = 0; $i < $count; $i++) {
            $unitPrice = fake()->randomFloat(2, 46.80, 53.20);
            $tank = FuelStation::query()->create([
                'name' => sprintf('SAHA POMPASI %s', $i === 0 ? 'MERKEZ' : chr(64 + $i)),
                'type' => FuelSourceType::Tank,
                'party_id' => null,
                'purchase_price' => 0,
                'unit_price' => $unitPrice,
            ]);

            $linkCount = min(fake()->numberBetween(1, 3), $pool->count());
            $linked = collect();

            for ($n = 0; $n < $linkCount; $n++) {
                $linked->push($pool[$cursor % $pool->count()]);
                $cursor++;
            }

            $linked = $linked->unique('id')->values();
            $tank->sites()->attach($linked->pluck('id'));
            $tank->setRelation('sites', $linked);

            foreach ($linked as $site) {
                $this->siteStations[$site->id] = [
                    ...($this->siteStations[$site->id] ?? []),
                    $tank,
                ];
            }

            $this->stations->push($tank);
        }

        return $count;
    }

    private function seedVehicles(Carbon $fleetSince, Carbon $from, Carbon $to): void
    {
        $this->progress->stage('Araçlar ve sahiplik geçmişi');

        $owners = $this->partiesFor(PartyRole::VehicleOwner)->values();
        $window = max(1, (int) $from->diffInDays($to));
        $dayInWindow = fn (float $min, float $max): Carbon => $from->copy()->addDays(
            fake()->numberBetween((int) ($window * $min), (int) ($window * $max)),
        );
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

            if (fake()->boolean(20)) {
                $this->transfer($vehicle, $owners, $dayInWindow(0.15, 0.6));
            }

            if (fake()->boolean(5)) {
                $this->transfer($vehicle, $owners, $dayInWindow(0.7, 0.9));
            }

            $this->haulVehicles->push($vehicle);
            $this->progress->batch($i + 1, self::HAUL_VEHICLE_COUNT + self::MACHINE_COUNT, 'araç');
        }

        // Birkaç araca özel güzergah fiyatı; bu araçlar o güzergahta çalışır.
        foreach ($this->haulVehicles->random(min(24, $this->haulVehicles->count())) as $vehicle) {
            $route = $this->routes->random();
            $classPrice = (float) ($this->routeClassPrices[$route->id][$vehicle->vehicle_class_id] ?? 3000);
            $price = (int) round($classPrice * fake()->randomFloat(2, 1.03, 1.12), -1);
            RouteVehiclePrice::query()->create([
                'route_id' => $route->id,
                'vehicle_id' => $vehicle->id,
                'price' => $price,
            ]);
            $this->routeVehiclePrices[$route->id][$vehicle->id] = (string) $price;
            $this->preferredRoutes[$vehicle->id] = $route->id;
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

            $mid = $dayInWindow(0.35, 0.6);
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

            if (fake()->boolean(15)) {
                $this->transfer($machine, $owners, $dayInWindow(0.25, 0.8));
            }

            $this->machines->push($machine);
            $this->progress->batch(self::HAUL_VEHICLE_COUNT + $i + 1, self::HAUL_VEHICLE_COUNT + self::MACHINE_COUNT, 'araç');
        }

        $this->progress->done(sprintf('%d taşıma + %d makine', $this->haulVehicles->count(), $this->machines->count()));
    }

    /**
     * Spare parts and materials for purchase / sale / plate-sale invoices (45). One untracked
     * service line so non-stock invoice lines also appear.
     */
    private function seedStockItems(): void
    {
        $this->progress->stage('Stok kalemleri');

        $byName = CashCategory::query()->where('active', true)->get()->keyBy('name');

        $defs = [
            ['name' => 'Hava filtresi', 'unit' => StockItemUnit::Piece, 'sales_price' => '850.00', 'category' => 'Yedek Parça'],
            ['name' => 'Yağ filtresi', 'unit' => StockItemUnit::Piece, 'sales_price' => '320.00', 'category' => 'Yedek Parça'],
            ['name' => 'Mazot filtresi', 'unit' => StockItemUnit::Piece, 'sales_price' => '480.00', 'category' => 'Yedek Parça'],
            ['name' => 'Lastik 315/80', 'unit' => StockItemUnit::Piece, 'sales_price' => '8500.00', 'category' => 'Lastik'],
            ['name' => 'Madeni yağ 15W40', 'unit' => StockItemUnit::Liter, 'sales_price' => '185.00', 'category' => 'Yedek Parça'],
            ['name' => 'Hidrolik yağ', 'unit' => StockItemUnit::Liter, 'sales_price' => '95.00', 'category' => 'Yedek Parça'],
            ['name' => 'Fren balatası', 'unit' => StockItemUnit::Piece, 'sales_price' => '1200.00', 'category' => 'Tamir ve Bakım'],
            ['name' => 'Cıvata seti', 'unit' => StockItemUnit::Piece, 'sales_price' => '45.00', 'category' => 'Hırdavat'],
            ['name' => 'Kayış', 'unit' => StockItemUnit::Piece, 'sales_price' => '650.00', 'category' => 'Yedek Parça'],
            ['name' => 'Akü 180Ah', 'unit' => StockItemUnit::Piece, 'sales_price' => '4200.00', 'category' => 'Yedek Parça'],
            ['name' => 'Çimento', 'unit' => StockItemUnit::Kilogram, 'sales_price' => '4.50', 'category' => 'Hırdavat'],
            ['name' => 'Gres', 'unit' => StockItemUnit::Kilogram, 'sales_price' => '120.00', 'category' => 'Yedek Parça'],
            ['name' => 'İşçilik / montaj', 'unit' => StockItemUnit::Piece, 'sales_price' => '1500.00', 'category' => 'Hizmet Alımı', 'tracks_stock' => false],
        ];

        $this->stockItems = collect();

        foreach ($defs as $def) {
            $item = StockItem::query()->create([
                'name' => $def['name'],
                'unit' => $def['unit'],
                'tracks_stock' => $def['tracks_stock'] ?? true,
                'cash_category_id' => $byName->get($def['category'])?->id,
                'sales_price' => $def['sales_price'],
                'active' => true,
            ]);
            $this->stockItems->push($item);
        }

        $this->progress->done($this->stockItems->count().' stok kalemi');
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

    /**
     * @param  Collection<int, Party>  $owners
     */
    private function transfer(Vehicle $vehicle, Collection $owners, Carbon $on): void
    {
        $current = $this->ownershipIndex[$vehicle->id] ?? [];
        if ($current === []) {
            return;
        }

        $last = count($current) - 1;
        $to = $owners->reject(fn (Party $owner) => $owner->id === $current[$last]['party_id'])->random();
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
