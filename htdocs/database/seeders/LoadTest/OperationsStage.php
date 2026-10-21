<?php

namespace Database\Seeders\LoadTest;

use App\Enums\PartyRole;
use App\Enums\Shift;
use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\MachineWork;
use App\Models\User;
use App\Services\Ledger\FuelReceiptLedger;
use App\Services\Ledger\HaulLedger;
use App\Services\Ledger\MachineWorkLedger;
use Database\Seeders\LoadTest\Support\Progress;
use Database\Seeders\LoadTest\Support\TurkishData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 1-year operational tickets derived from per-vehicle daily capacity
 * (trips, fuel fills, machine hours) — not fixed monthly quotas.
 */
final class OperationsStage
{
    public const MONTHS = 12;

    /** Weekday trip choices weighted around 6–7. */
    private const WEEKDAY_TRIPS = [4, 5, 5, 6, 6, 6, 7, 7, 7, 8, 8, 9];

    private const WEEKEND_TRIPS = [2, 3, 3, 4, 4, 5];

    private const WEEKDAY_MACHINE_HOURS = [6, 7, 8, 8, 8, 8, 9, 9.5, 10];

    private const WEEKEND_MACHINE_HOURS = [4, 5, 6, 7, 8];

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

        $haulCount = $catalog->haulVehicles()->count();
        $machineCount = $catalog->machines()->count();
        $dayCount = count($days);

        $this->progress->note(sprintf(
            'Kapasite: %d kamyon · %d makine · %d gün (hafta içi ~6.5 sefer, makine ~8 saat)',
            $haulCount,
            $machineCount,
            $dayCount,
        ));
        $this->progress->note(sprintf(
            'Tahmini: ~%s taşıma · ~%s yakıt · ~%s makine fişi',
            number_format((int) ($haulCount * $dayCount * 5.2)),
            number_format((int) (($haulCount + $machineCount) * $dayCount * 0.55)),
            number_format((int) ($machineCount * $dayCount * 0.62)),
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
        $stations = $catalog->allStations()->values();

        $estHauls = (int) max(1, $vehicles->count() * count($days) * 5.2);
        $estFuel = (int) max(1, $vehicles->count() * count($days) * 0.55);

        $this->progress->stage('Taşıma + yakıt (araç kapasitesi)');
        $this->progress->note('Her kamyon için günlük sefer; yakıt sefer gününe bağlı');

        $haulsDone = 0;
        $fuelDone = 0;
        $haulSeq = 0;
        $fuelSeq = 0;
        $kmBase = [];
        $stationCursor = 0;

        foreach ($days as $dayIndex => $day) {
            DB::transaction(function () use (
                $catalog,
                $vehicles,
                $routes,
                $stations,
                $day,
                $creatorId,
                &$haulsDone,
                &$fuelDone,
                &$haulSeq,
                &$fuelSeq,
                &$kmBase,
                &$stationCursor,
            ): void {
                foreach ($vehicles as $vIndex => $vehicle) {
                    $ownerId = $catalog->ownerOn($vehicle->id, $day);

                    if (! $ownerId) {
                        continue;
                    }

                    $trips = $this->dailyTrips($day);

                    if ($trips === 0) {
                        continue;
                    }

                    $tripHours = [];

                    for ($t = 0; $t < $trips; $t++) {
                        $route = $routes->get(($vIndex * 7 + $t * 3 + $haulsDone) % $routes->count());
                        $hour = fake()->boolean(78)
                            ? fake()->numberBetween(7, 19)
                            : fake()->numberBetween(20, 23);
                        $hauledAt = $day->copy()->setTime($hour, fake()->numberBetween(0, 59), fake()->numberBetween(0, 59));
                        $enteredAt = $hauledAt->copy()->addMinutes(fake()->numberBetween(5, 75));

                        if ($enteredAt->isFuture()) {
                            $enteredAt = now();
                        }

                        $haulSeq++;
                        $prices = $catalog->quote($vehicle, $route);

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
                            'shift' => $hour >= 8 && $hour <= 19 ? Shift::Day : Shift::Night,
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
                        $tripHours[] = $hour;
                    }

                    if (! $this->shouldRefuelHaul($trips)) {
                        continue;
                    }

                    $station = $stations->get($stationCursor % $stations->count());
                    $stationCursor++;
                    $siteId = $station->sites->isNotEmpty()
                        ? $station->sites->random()->id
                        : null;

                    $fuelHour = $tripHours !== []
                        ? min($tripHours) + fake()->numberBetween(0, 2)
                        : fake()->numberBetween(7, 20);
                    $fuelHour = max(6, min(22, $fuelHour));
                    $enteredAt = $day->copy()->setTime($fuelHour, fake()->numberBetween(0, 59));

                    if ($enteredAt->isFuture()) {
                        $enteredAt = now();
                    }

                    $liters = min(200, max(40, $trips * fake()->numberBetween(12, 22)));
                    $kmBase[$vehicle->id] = ($kmBase[$vehicle->id] ?? fake()->numberBetween(80_000, 280_000))
                        + ($trips * fake()->numberBetween(18, 45));

                    $fuelSeq++;
                    $receipt = FuelReceipt::query()->forceCreate([
                        'id' => (string) Str::uuid7($enteredAt),
                        'ticket_no' => 'Y'.$day->format('Ym').str_pad((string) $fuelSeq, 6, '0', STR_PAD_LEFT),
                        'vehicle_id' => $vehicle->id,
                        'party_id' => $ownerId,
                        'fuel_station_id' => $station->id,
                        'fuel_party_id' => $station->party_id,
                        'site_id' => $siteId,
                        'quantity' => $liters,
                        'unit_price' => $station->unit_price,
                        'issued_on' => $day->toDateString(),
                        'km' => $kmBase[$vehicle->id],
                        'received_by' => $vehicle->driver_name ?? TurkishData::personName(),
                        'shift' => $fuelHour >= 8 && $fuelHour <= 19 ? Shift::Day : Shift::Night,
                        'haul_ticket_count' => $trips,
                        'created_by' => $creatorId,
                        'created_at' => $enteredAt,
                        'updated_at' => $enteredAt,
                    ]);

                    $receipt->setRelation('fuelStation', $station);
                    $this->fuelLedger->sync($receipt);
                    $fuelDone++;
                }
            });

            if ($dayIndex % 7 === 0 || $dayIndex === count($days) - 1) {
                $this->progress->batch(min($haulsDone, $estHauls), $estHauls, 'taşıma');
            }
        }

        $this->progress->done(sprintf(
            '%s taşıma fişi · %s yakıt (kamyon) — hedef ~%s / ~%s',
            number_format($haulsDone),
            number_format($fuelDone),
            number_format($estHauls),
            number_format($estFuel),
        ));
    }

    /**
     * @param  list<Carbon>  $days
     */
    private function seedMachineWorksAndFuel(CatalogStage $catalog, array $days, ?string $creatorId): void
    {
        $machines = $catalog->machines()->values();
        $sites = $catalog->allSites()->values();
        $stations = $catalog->allStations()->values();
        $operators = $catalog->partiesFor(PartyRole::MachineOperator)->values();

        $estWorks = (int) max(1, $machines->count() * count($days) * 0.62);
        $estFuel = (int) max(1, $machines->count() * count($days) * 0.40);

        $this->progress->stage('Makine + yakıt (saat kapasitesi)');
        $this->progress->note('Her makine için günlük çalışma saati; yakıt çalışma gününe bağlı');

        $worksDone = 0;
        $fuelDone = 0;
        $workSeq = 0;
        $fuelSeq = 0;
        $kmBase = [];
        $stationCursor = 0;

        foreach ($days as $dayIndex => $day) {
            DB::transaction(function () use (
                $catalog,
                $machines,
                $sites,
                $stations,
                $operators,
                $day,
                $creatorId,
                &$worksDone,
                &$fuelDone,
                &$workSeq,
                &$fuelSeq,
                &$kmBase,
                &$stationCursor,
            ): void {
                foreach ($machines as $mIndex => $machine) {
                    $ownerId = $catalog->ownerOn($machine->id, $day);
                    $rate = $catalog->rateOn($machine->id, $day);

                    if (! $ownerId || $rate === null) {
                        continue;
                    }

                    $hours = $this->dailyMachineHours($day);

                    if ($hours <= 0) {
                        continue;
                    }

                    $operator = $operators->isNotEmpty() && fake()->boolean(82)
                        ? $operators->get(($worksDone + $mIndex) % $operators->count())
                        : null;
                    $isStaff = $operator && $operator->hasRole(PartyRole::Employee) && fake()->boolean(40);
                    $operatorRate = $operator && ! $isStaff
                        ? fake()->randomElement([220, 250, 280, 300, 320, 350])
                        : null;

                    $enteredAt = $day->copy()->setTime(fake()->numberBetween(17, 22), fake()->numberBetween(0, 59));

                    if ($enteredAt->isFuture()) {
                        $enteredAt = now();
                    }

                    $site = $sites->get(($worksDone + $mIndex) % $sites->count());
                    $workSeq++;

                    $work = MachineWork::query()->forceCreate([
                        'id' => (string) Str::uuid7($enteredAt),
                        'ticket_no' => 'M'.$day->format('Ym').str_pad((string) $workSeq, 6, '0', STR_PAD_LEFT),
                        'vehicle_id' => $machine->id,
                        'party_id' => $ownerId,
                        'site_id' => $site->id,
                        'operator_id' => $operator?->id,
                        'operator_rate' => $operatorRate,
                        'shift' => fake()->boolean(80) ? Shift::Day : Shift::Night,
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

                    if (! $this->shouldRefuelMachine($hours)) {
                        continue;
                    }

                    $station = $stations->get($stationCursor % $stations->count());
                    $stationCursor++;
                    $siteId = $station->sites->isNotEmpty()
                        ? $station->sites->random()->id
                        : $site->id;

                    $fuelAt = $day->copy()->setTime(fake()->numberBetween(7, 12), fake()->numberBetween(0, 59));

                    if ($fuelAt->isFuture()) {
                        $fuelAt = now();
                    }

                    $liters = min(200, max(60, (int) round($hours * fake()->numberBetween(8, 14))));
                    $kmBase[$machine->id] = ($kmBase[$machine->id] ?? fake()->numberBetween(5_000, 40_000))
                        + fake()->numberBetween(5, 40);

                    $fuelSeq++;
                    $receipt = FuelReceipt::query()->forceCreate([
                        'id' => (string) Str::uuid7($fuelAt),
                        'ticket_no' => 'YM'.$day->format('Ym').str_pad((string) $fuelSeq, 5, '0', STR_PAD_LEFT),
                        'vehicle_id' => $machine->id,
                        'party_id' => $ownerId,
                        'fuel_station_id' => $station->id,
                        'fuel_party_id' => $station->party_id,
                        'site_id' => $siteId,
                        'quantity' => $liters,
                        'unit_price' => $station->unit_price,
                        'issued_on' => $day->toDateString(),
                        'km' => $kmBase[$machine->id],
                        'received_by' => TurkishData::personName(),
                        'shift' => Shift::Day,
                        'haul_ticket_count' => 0,
                        'created_by' => $creatorId,
                        'created_at' => $fuelAt,
                        'updated_at' => $fuelAt,
                    ]);

                    $receipt->setRelation('fuelStation', $station);
                    $this->fuelLedger->sync($receipt);
                    $fuelDone++;
                }
            });

            if ($dayIndex % 7 === 0 || $dayIndex === count($days) - 1) {
                $this->progress->batch(min($worksDone, $estWorks), $estWorks, 'makine');
            }
        }

        $this->progress->done(sprintf(
            '%s makine fişi · %s yakıt (makine) — hedef ~%s / ~%s',
            number_format($worksDone),
            number_format($fuelDone),
            number_format($estWorks),
            number_format($estFuel),
        ));
    }

    private function dailyTrips(Carbon $day): int
    {
        $weekend = $day->isWeekend();

        // Idle / maintenance / rain.
        if (fake()->boolean($weekend ? 55 : 12)) {
            return 0;
        }

        return (int) fake()->randomElement($weekend ? self::WEEKEND_TRIPS : self::WEEKDAY_TRIPS);
    }

    private function dailyMachineHours(Carbon $day): float
    {
        $weekend = $day->isWeekend();

        if (fake()->boolean($weekend ? 60 : 25)) {
            return 0;
        }

        return (float) fake()->randomElement($weekend ? self::WEEKEND_MACHINE_HOURS : self::WEEKDAY_MACHINE_HOURS);
    }

    private function shouldRefuelHaul(int $trips): bool
    {
        if ($trips >= 7) {
            return true;
        }

        if ($trips >= 5) {
            return fake()->boolean(80);
        }

        return fake()->boolean(55);
    }

    private function shouldRefuelMachine(float $hours): bool
    {
        if ($hours >= 9) {
            return fake()->boolean(85);
        }

        return fake()->boolean(50);
    }
}
