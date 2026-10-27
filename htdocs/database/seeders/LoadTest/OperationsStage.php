<?php

namespace Database\Seeders\LoadTest;

use App\Enums\PartyRole;
use App\Enums\Shift;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\MachineWork;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Ledger\FuelReceiptLedger;
use App\Services\Ledger\HaulLedger;
use App\Services\Ledger\MachineWorkLedger;
use Database\Seeders\LoadTest\Support\Progress;
use Database\Seeders\LoadTest\Support\TurkishData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Operational tickets derived from per-vehicle daily capacity.
 *
 * A truck works one route for the whole day; the route's round-trip time decides how many
 * trips fit in the shift. Trucks refuel in the morning and hand in the haul tickets they
 * collected since the previous fill, so fuel litres and ticket counts follow real work.
 */
final class OperationsStage
{
    public const MONTHS = 12;

    private const WEEKDAY = 0;

    private const SATURDAY = 1;

    private const SUNDAY = 2;

    /** Idle chance (%) by day type: maintenance, rain, no work order. */
    private const HAUL_IDLE = [8, 25, 65];

    private const MACHINE_IDLE = [15, 35, 70];

    /** Truck shift length in minutes by day type. */
    private const HAUL_SHIFT_MINUTES = [[600, 720], [480, 600], [300, 420]];

    private const MACHINE_HOURS = [
        [7, 8, 8, 9, 9, 9.5, 10, 10.5, 11],
        [5, 6, 7, 8, 8],
        [4, 5, 6],
    ];

    /** Chance (%) per working day that a truck moves to another route / a machine to another site. */
    private const ROUTE_CHANGE = 10;

    private const MACHINE_SITE_CHANGE = 6;

    /** Litres per minute of a round trip, and tank size, by class. */
    private const LITRES_PER_TRIP_MINUTE = ['TIR' => 0.24, 'KIRKAYAK' => 0.2, 'ON TEKER' => 0.16, 'TAKSİ' => 0.09];

    private const TANK_LITRES = ['TIR' => 400, 'KIRKAYAK' => 350, 'ON TEKER' => 300, 'TAKSİ' => 160, 'MAKİNE' => 400];

    public function __construct(
        private Progress $progress,
        private HaulLedger $haulLedger,
        private MachineWorkLedger $machineLedger,
        private FuelReceiptLedger $fuelLedger,
    ) {}

    public function run(CatalogStage $catalog, Carbon $from, Carbon $to): void
    {
        $creatorId = User::query()->value('id');
        $days = $this->calendarDays($from, $to);

        $this->progress->note(sprintf(
            'Kapasite: %d kamyon · %d makine · %d gün (kamyon günde tek güzergah, sefer = vardiya / tur süresi)',
            $catalog->haulVehicles()->count(),
            $catalog->machines()->count(),
            count($days),
        ));

        $this->seedHaulsAndFuel($catalog, $days, $creatorId);
        $this->seedMachineWorksAndFuel($catalog, $days, $creatorId);
    }

    /**
     * @return list<Carbon>
     */
    private function calendarDays(Carbon $from, Carbon $to): array
    {
        $days = [];
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @param  list<Carbon>  $days
     */
    private function seedHaulsAndFuel(CatalogStage $catalog, array $days, ?string $creatorId): void
    {
        $vehicles = $catalog->haulVehicles()->values();
        $routes = $catalog->allRoutes()->values();
        $routesBySite = $routes->groupBy('site_id');
        $estHauls = (int) max(1, $vehicles->count() * count($days) * 6);

        $this->progress->stage('Taşıma + yakıt (araç kapasitesi)');
        $this->progress->note('Kamyon gün boyu aynı güzergahta; yakıt sabah, önceki fişlerle birlikte');

        /** @var array<string, HaulRoute> $assigned */
        $assigned = [];
        /** @var array<string, array{owner: ?string, site: ?string, litres: float, tickets: int, threshold: float, offSite: bool, km: int}> $tank */
        $tank = [];

        foreach ($vehicles as $index => $vehicle) {
            $preferred = $catalog->preferredRouteId($vehicle->id);
            $assigned[$vehicle->id] = ($preferred ? $routes->firstWhere('id', $preferred) : null)
                ?? $routes[$index % $routes->count()];

            $capacity = self::TANK_LITRES[$vehicle->vehicleClass?->name] ?? 300;
            $tank[$vehicle->id] = [
                'owner' => null,
                'site' => null,
                'litres' => 0.0,
                'tickets' => 0,
                'threshold' => $capacity * fake()->randomFloat(2, 0.55, 0.75),
                'offSite' => fake()->boolean(25),
                'km' => fake()->numberBetween(80_000, 280_000),
            ];
        }

        $haulsDone = 0;
        $fuelDone = 0;
        $haulSeq = 0;
        $fuelSeq = 0;

        foreach ($days as $dayIndex => $day) {
            DB::transaction(function () use (
                $catalog,
                $vehicles,
                $routes,
                $routesBySite,
                $day,
                $creatorId,
                &$assigned,
                &$tank,
                &$haulsDone,
                &$fuelDone,
                &$haulSeq,
                &$fuelSeq,
            ): void {
                $dayType = $this->dayType($day);

                foreach ($vehicles as $vIndex => $vehicle) {
                    $ownerId = $catalog->ownerOn($vehicle->id, $day);

                    if (! $ownerId || fake()->boolean(self::HAUL_IDLE[$dayType])) {
                        continue;
                    }

                    // Tickets of a previous owner are settled outside the system.
                    if ($tank[$vehicle->id]['owner'] !== $ownerId) {
                        $tank[$vehicle->id] = [...$tank[$vehicle->id], 'owner' => $ownerId, 'litres' => 0.0, 'tickets' => 0];
                    }

                    $route = $this->nextRoute($assigned[$vehicle->id], $routes, $routesBySite);
                    $start = $day->copy()->setTime(8, 15)->addMinutes(fake()->numberBetween(0, 45));
                    $leavingSite = $tank[$vehicle->id]['site'] !== null && $tank[$vehicle->id]['site'] !== $route->site_id;

                    if (
                        $tank[$vehicle->id]['litres'] >= $tank[$vehicle->id]['threshold']
                        || ($leavingSite && $tank[$vehicle->id]['litres'] >= 60)
                    ) {
                        $station = $catalog->stationFor($tank[$vehicle->id]['site'], $vIndex, $tank[$vehicle->id]['offSite']);
                        $fuelSeq++;
                        if ($this->createFuel($vehicle, $station, $tank[$vehicle->id], $start->copy()->subMinutes(fake()->numberBetween(5, 15)), 'Y'.$day->format('Ym').str_pad((string) $fuelSeq, 6, '0', STR_PAD_LEFT), $vehicle->driver_name, $creatorId)) {
                            $tank[$vehicle->id] = [...$tank[$vehicle->id], 'litres' => 0.0, 'tickets' => 0];
                            $fuelDone++;
                        }
                    }

                    $assigned[$vehicle->id] = $route;
                    $cycle = $catalog->cycleMinutes($route->id);
                    [$minShift, $maxShift] = self::HAUL_SHIFT_MINUTES[$dayType];
                    $trips = max(1, intdiv(fake()->numberBetween($minShift, $maxShift), $cycle) - fake()->numberBetween(0, 1));
                    $prices = $catalog->quote($vehicle, $route);
                    $litresPerTrip = $cycle * (self::LITRES_PER_TRIP_MINUTE[$vehicle->vehicleClass?->name] ?? 0.16);

                    for ($t = 0; $t < $trips; $t++) {
                        $hauledAt = $start->copy()->addMinutes($t * $cycle + fake()->numberBetween(0, 12));

                        if ($hauledAt->isFuture()) {
                            break;
                        }

                        $enteredAt = $hauledAt->copy()->addMinutes(fake()->numberBetween(5, 75));

                        if ($enteredAt->isFuture()) {
                            $enteredAt = now();
                        }

                        $haulSeq++;
                        $haul = Haul::query()->forceCreate([
                            'id' => (string) Str::uuid7($enteredAt),
                            'ticket_no' => 'T'.$hauledAt->format('Ym').str_pad((string) $haulSeq, 6, '0', STR_PAD_LEFT),
                            'vehicle_id' => $vehicle->id,
                            'party_id' => $ownerId,
                            'driver_name' => $vehicle->driver_name,
                            'route_id' => $route->id,
                            'site_id' => $route->site_id,
                            'dump_site_id' => $route->dump_site_id,
                            'dump_party_id' => $route->dumpSite->party_id,
                            'tonnage' => match ($vehicle->vehicleClass?->name) {
                                'TIR' => fake()->numberBetween(22, 30),
                                'KIRKAYAK' => fake()->numberBetween(16, 24),
                                'ON TEKER' => fake()->numberBetween(10, 18),
                                default => fake()->numberBetween(4, 10),
                            },
                            'shift' => $hauledAt->hour >= 8 && $hauledAt->hour <= 19 ? Shift::Day : Shift::Night,
                            'hauled_at' => $hauledAt,
                            'created_by' => $creatorId,
                            'created_at' => $enteredAt,
                            'updated_at' => $enteredAt,
                            'price' => $prices['price'],
                            'dump_price' => $prices['dump_price'],
                        ]);

                        $haul->setRelation('site', $route->site);
                        $haul->setRelation('dumpSite', $route->dumpSite);
                        $this->haulLedger->sync($haul);
                        $haulsDone++;

                        $tank[$vehicle->id]['litres'] += $litresPerTrip;
                        $tank[$vehicle->id]['tickets']++;
                        $tank[$vehicle->id]['km'] += (int) round($cycle * fake()->randomFloat(2, 0.35, 0.5));
                    }

                    $tank[$vehicle->id]['site'] = $route->site_id;
                }
            });

            if ($dayIndex % 7 === 0 || $dayIndex === count($days) - 1) {
                $this->progress->batch(min($haulsDone, $estHauls), $estHauls, 'taşıma');
            }
        }

        $this->progress->done(sprintf(
            '%s taşıma fişi · %s yakıt (kamyon)',
            number_format($haulsDone),
            number_format($fuelDone),
        ));
    }

    /**
     * @param  list<Carbon>  $days
     */
    private function seedMachineWorksAndFuel(CatalogStage $catalog, array $days, ?string $creatorId): void
    {
        $machines = $catalog->machines()->values();
        $sites = $catalog->allSites()->values();
        $operators = $catalog->partiesFor(PartyRole::MachineOperator)->shuffle()->values();
        $estWorks = (int) max(1, $machines->count() * count($days) * 0.7);

        $this->progress->stage('Makine + yakıt (saat kapasitesi)');
        $this->progress->note('Makine bir şantiyede kalır, operatörü sabit; yakıt sabah, çalışılan saate göre');

        /** @var array<string, array{site: string, operator: ?string, operatorRate: ?int}> $crew */
        $crew = [];
        /** @var array<string, array{owner: ?string, site: ?string, litres: float, tickets: int, threshold: float, offSite: bool, km: int, perHour: int}> $tank */
        $tank = [];

        foreach ($machines as $index => $machine) {
            // One operator per machine, so nobody works two machines on the same day.
            $operator = $index < $operators->count() && fake()->boolean(82) ? $operators[$index] : null;
            $isStaff = $operator && $operator->hasRole(PartyRole::Employee) && fake()->boolean(40);

            $crew[$machine->id] = [
                'site' => $sites[$index % $sites->count()]->id,
                'operator' => $operator?->id,
                'operatorRate' => $operator && ! $isStaff ? fake()->randomElement([220, 250, 280, 300, 320, 350]) : null,
            ];
            $tank[$machine->id] = [
                'owner' => null,
                'site' => null,
                'litres' => 0.0,
                'tickets' => 0,
                'threshold' => self::TANK_LITRES['MAKİNE'] * fake()->randomFloat(2, 0.5, 0.7),
                'offSite' => false,
                'km' => fake()->numberBetween(5_000, 40_000),
                'perHour' => fake()->numberBetween(11, 17),
            ];
        }

        $sitesById = $sites->keyBy('id');
        $worksDone = 0;
        $fuelDone = 0;
        $workSeq = 0;
        $fuelSeq = 0;

        foreach ($days as $dayIndex => $day) {
            DB::transaction(function () use (
                $catalog,
                $machines,
                $sites,
                $sitesById,
                $day,
                $creatorId,
                &$crew,
                &$tank,
                &$worksDone,
                &$fuelDone,
                &$workSeq,
                &$fuelSeq,
            ): void {
                $dayType = $this->dayType($day);

                foreach ($machines as $mIndex => $machine) {
                    $ownerId = $catalog->ownerOn($machine->id, $day);
                    $rate = $catalog->rateOn($machine->id, $day);

                    if (! $ownerId || $rate === null || fake()->boolean(self::MACHINE_IDLE[$dayType])) {
                        continue;
                    }

                    if ($tank[$machine->id]['owner'] !== $ownerId) {
                        $tank[$machine->id] = [...$tank[$machine->id], 'owner' => $ownerId, 'litres' => 0.0, 'tickets' => 0];
                    }

                    if (fake()->boolean(self::MACHINE_SITE_CHANGE)) {
                        $crew[$machine->id]['site'] = $sites->reject(fn ($site) => $site->id === $crew[$machine->id]['site'])->random()->id;
                    }

                    $site = $sitesById[$crew[$machine->id]['site']];
                    $leavingSite = $tank[$machine->id]['site'] !== null && $tank[$machine->id]['site'] !== $site->id;

                    if (
                        $tank[$machine->id]['litres'] >= $tank[$machine->id]['threshold']
                        || ($leavingSite && $tank[$machine->id]['litres'] >= 60)
                    ) {
                        $station = $catalog->stationFor($tank[$machine->id]['site'], $mIndex, false);
                        $fuelSeq++;
                        $fuelAt = $day->copy()->setTime(fake()->numberBetween(8, 9), fake()->numberBetween(0, 59));
                        if ($this->createFuel($machine, $station, [...$tank[$machine->id], 'tickets' => 0], $fuelAt, 'YM'.$day->format('Ym').str_pad((string) $fuelSeq, 5, '0', STR_PAD_LEFT), TurkishData::personName(), $creatorId)) {
                            $tank[$machine->id] = [...$tank[$machine->id], 'litres' => 0.0, 'tickets' => 0];
                            $fuelDone++;
                        }
                    }

                    $hours = (float) fake()->randomElement(self::MACHINE_HOURS[$dayType]);

                    // A ticket is written once the shift is over; today's unfinished shifts are not in yet.
                    if ($day->copy()->setTime(8, 0)->addMinutes((int) round($hours * 60))->isFuture()) {
                        continue;
                    }
                    $enteredAt = $day->copy()->setTime(fake()->numberBetween(17, 22), fake()->numberBetween(0, 59));

                    if ($enteredAt->isFuture()) {
                        $enteredAt = now();
                    }

                    $workSeq++;
                    $work = MachineWork::query()->forceCreate([
                        'id' => (string) Str::uuid7($enteredAt),
                        'ticket_no' => 'M'.$day->format('Ym').str_pad((string) $workSeq, 6, '0', STR_PAD_LEFT),
                        'vehicle_id' => $machine->id,
                        'party_id' => $ownerId,
                        'site_id' => $site->id,
                        'operator_id' => $crew[$machine->id]['operator'],
                        'operator_rate' => $crew[$machine->id]['operatorRate'],
                        'shift' => fake()->boolean(85) ? Shift::Day : Shift::Night,
                        'hours' => $hours,
                        'hourly_rate' => $rate,
                        'worked_on' => $day->toDateString(),
                        'created_by' => $creatorId,
                        'created_at' => $enteredAt,
                        'updated_at' => $enteredAt,
                    ]);

                    $work->setRelation('site', $site);
                    $this->machineLedger->sync($work);
                    $worksDone++;

                    $tank[$machine->id]['litres'] += $hours * $tank[$machine->id]['perHour'];
                    $tank[$machine->id]['km'] += (int) ceil($hours);
                    $tank[$machine->id]['site'] = $site->id;
                }
            });

            if ($dayIndex % 7 === 0 || $dayIndex === count($days) - 1) {
                $this->progress->batch(min($worksDone, $estWorks), $estWorks, 'makine');
            }
        }

        $this->progress->done(sprintf(
            '%s makine fişi · %s yakıt (makine)',
            number_format($worksDone),
            number_format($fuelDone),
        ));
    }

    /**
     * @param  array{site: ?string, litres: float, tickets: int, km: int}  $tank
     */
    private function createFuel(Vehicle $vehicle, FuelStation $station, array $tank, Carbon $at, string $ticketNo, ?string $receivedBy, ?string $creatorId): bool
    {
        // A fill that has not happened yet is not written; the tank keeps its litres for the next one.
        if ($at->isFuture()) {
            return false;
        }

        $receipt = FuelReceipt::query()->forceCreate([
            'id' => (string) Str::uuid7($at),
            'ticket_no' => $ticketNo,
            'vehicle_id' => $vehicle->id,
            'party_id' => $tank['owner'],
            'fuel_station_id' => $station->id,
            'fuel_party_id' => $station->party_id,
            'site_id' => $tank['site'],
            'quantity' => min(self::TANK_LITRES[$vehicle->vehicleClass?->name] ?? 400, (int) round($tank['litres'])),
            'unit_price' => $station->unit_price,
            'issued_on' => $at->toDateString(),
            'km' => $tank['km'],
            'received_by' => $receivedBy ?? TurkishData::personName(),
            'shift' => $at->hour >= 8 && $at->hour <= 19 ? Shift::Day : Shift::Night,
            'haul_ticket_count' => $tank['tickets'],
            'created_by' => $creatorId,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        $receipt->setRelation('fuelStation', $station);
        $this->fuelLedger->sync($receipt);

        return true;
    }

    /**
     * Usually the same route; sometimes another dump of the same site, rarely another site.
     *
     * @param  Collection<int, HaulRoute>  $routes
     * @param  Collection<string, Collection<int, HaulRoute>>  $routesBySite
     */
    private function nextRoute(HaulRoute $current, Collection $routes, Collection $routesBySite): HaulRoute
    {
        if (! fake()->boolean(self::ROUTE_CHANGE)) {
            return $current;
        }

        $sameSite = $routesBySite[$current->site_id]->reject(fn (HaulRoute $route) => $route->id === $current->id);

        if ($sameSite->isNotEmpty() && fake()->boolean(65)) {
            return $sameSite->random();
        }

        return $routes->reject(fn (HaulRoute $route) => $route->id === $current->id)->random();
    }

    private function dayType(Carbon $day): int
    {
        return match ($day->dayOfWeek) {
            Carbon::SATURDAY => self::SATURDAY,
            Carbon::SUNDAY => self::SUNDAY,
            default => self::WEEKDAY,
        };
    }
}
